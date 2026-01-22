<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * ✅ COMMUNALES — VERSION "SIÈGES OFFICIELS PAR ARRONDISSEMENT"
 *
 * - On calcule le quotient communal (info) : population / sièges (somme des sièges d'arrondissements)
 * - MAIS la répartition des sièges aux arrondissements = valeurs OFFICIELLES arrondissements.siege
 * - Ensuite, on attribue les sièges aux listes dans chaque arrondissement (Article 187)
 * - Dédup : on ne retient qu'une ligne (arrondissement, village_quartier) selon PV/ligne les plus récents
 */
class ResultatsCommunalesService
{
    private StatsService $statsService;

    public function __construct(StatsService $statsService)
    {
        $this->statsService = $statsService;
    }

    /**
     * Obtenir les résultats par commune (données brutes)
     */
    public function getResultatsParCommune(int $electionId): array
    {
        // Communes (exclure Diaspora via departement_id=13)
        $communes = DB::table('communes as c')
            ->join('departements as d', 'd.id', '=', 'c.departement_id')
            ->select('c.id', 'c.nom', 'c.code', 'd.nom as departement_nom', 'd.id as departement_id')
            ->where('d.id', '<>', 13)
            ->orderBy('c.nom')
            ->get();

        // Entités politiques candidates (validées)
        $entites = DB::table('candidatures as ca')
            ->join('entites_politiques as ep', 'ep.id', '=', 'ca.entite_politique_id')
            ->where('ca.election_id', $electionId)
            ->where('ca.statut', 'validee')
            ->select('ep.id', 'ep.nom', 'ep.sigle', DB::raw('MIN(ca.numero_liste) as numero_liste'))
            ->groupBy('ep.id', 'ep.nom', 'ep.sigle')
            ->orderBy('numero_liste')
            ->get();

        $matrice = [];
        $totauxParEntite = [];
        $totauxParCommune = [];

        foreach ($entites as $entite) {
            $totauxParEntite[$entite->id] = [
                'voix' => 0,
                'pourcentage_national' => 0,
            ];
        }

        foreach ($communes as $commune) {
            // Arrondissements de la commune
            $arrondissements = DB::table('arrondissements')
                ->where('commune_id', $commune->id)
                ->orderBy('nom')
                ->get();

            // ✅ SIÈGES OFFICIELS
            $nombreSieges = (int) $arrondissements->sum('siege');
            $population = (int) $arrondissements->sum('population');

            $matrice[$commune->id] = [
                'info' => $commune,
                'nombre_sieges' => $nombreSieges,
                'population' => $population,
                'arrondissements' => $arrondissements,
                'resultats' => [],
                'total_voix' => 0,
            ];

            // Voix par entité dans la commune (dédup par arrondissement + village)
            $voixCommune = $this->getVoixParEntiteDansCommune($electionId, $commune->id);

            $totalVoixCommune = array_sum($voixCommune);
            $matrice[$commune->id]['total_voix'] = $totalVoixCommune;
            $totauxParCommune[$commune->id] = $totalVoixCommune;

            foreach ($entites as $entite) {
                $voix = $voixCommune[$entite->id] ?? 0;
                $pourcentage = $totalVoixCommune > 0 ? ($voix / $totalVoixCommune) * 100 : 0;

                $matrice[$commune->id]['resultats'][$entite->id] = [
                    'voix' => (int) $voix,
                    'pourcentage' => $pourcentage,
                ];

                $totauxParEntite[$entite->id]['voix'] += (int) $voix;
            }
        }

        // Pourcentage national
        $totalVoixNational = array_sum($totauxParCommune);
        foreach ($entites as $entite) {
            $totauxParEntite[$entite->id]['pourcentage_national'] =
                $totalVoixNational > 0
                    ? ($totauxParEntite[$entite->id]['voix'] / $totalVoixNational) * 100
                    : 0;
        }

        return [
            'communes' => $communes,
            'entites' => $entites,
            'matrice' => $matrice,
            'totaux_par_entite' => $totauxParEntite,
            'total_voix_national' => $totalVoixNational,
        ];
    }

    /**
     * Récupérer les voix par entité dans une commune (dédup par arrondissement + village)
     */
    private function getVoixParEntiteDansCommune(int $electionId, int $communeId): array
    {
        $lignesAvecPv = DB::table('proces_verbaux as pv')
            ->join('pv_lignes as pl', 'pl.proces_verbal_id', '=', 'pv.id')
            ->join('arrondissements as a', 'a.id', '=', DB::raw('pv.niveau_id::int'))
            ->where('pv.niveau', 'arrondissement')
            ->whereIn('pv.statut', ['valide', 'publie'])
            ->where('pv.election_id', $electionId)
            ->where('a.commune_id', $communeId)
            ->whereNotNull('pl.village_quartier_id')
            ->selectRaw("
                a.id AS arrondissement_id,
                pv.id AS proces_verbal_id,
                pv.created_at AS pv_created_at,
                pl.id AS pv_ligne_id,
                pl.village_quartier_id,
                pl.created_at AS ligne_created_at
            ");

        $dedup = DB::query()->fromSub($lignesAvecPv, 'l')
            ->select('l.*')
            ->selectRaw("
                ROW_NUMBER() OVER (
                    PARTITION BY l.arrondissement_id, l.village_quartier_id
                    ORDER BY l.pv_created_at DESC, l.ligne_created_at DESC, l.pv_ligne_id DESC
                ) AS rn
            ");

        $lignesRetenues = DB::query()->fromSub($dedup, 'd')->where('d.rn', 1);

        $results = DB::query()->fromSub($lignesRetenues, 'lr')
            ->join('pv_ligne_resultats as plr', 'plr.pv_ligne_id', '=', 'lr.pv_ligne_id')
            ->join('candidatures as ca', 'ca.id', '=', 'plr.candidature_id')
            ->select('ca.entite_politique_id', DB::raw('SUM(COALESCE(plr.nombre_voix, 0)) as total_voix'))
            ->groupBy('ca.entite_politique_id')
            ->get();

        $voix = [];
        foreach ($results as $result) {
            $voix[$result->entite_politique_id] = (int) $result->total_voix;
        }

        return $voix;
    }

    /**
     * ✅ ÉTAPE 1 : Vérifier éligibilité nationale (Seuil 10%)
     */
    public function verifierEligibiliteNationale(int $electionId): array
    {
        $data = $this->getResultatsParCommune($electionId);
        $eligibilite = [];

        foreach ($data['entites'] as $entite) {
            $pourcentageNational = $data['totaux_par_entite'][$entite->id]['pourcentage_national'];
            $eligible = $pourcentageNational >= 10;

            $eligibilite[$entite->id] = [
                'entite' => $entite,
                'eligible' => $eligible,
                'pourcentage_national' => $pourcentageNational,
                'total_voix' => $data['totaux_par_entite'][$entite->id]['voix'],
            ];
        }

        return [
            'eligibilite' => $eligibilite,
            'data' => $data,
        ];
    }

    /**
     * ✅ COMPILATION COMMUNALES (sièges officiels par arrondissement)
     */
    public function repartirSieges(int $electionId): array
    {
        $eligibiliteData = $this->verifierEligibiliteNationale($electionId);
        $eligibilite = $eligibiliteData['eligibilite'];
        $data = $eligibiliteData['data'];

        // Entités éligibles nationalement (>=10%)
        $entitesEligibles = array_filter($eligibilite, fn($e) => $e['eligible']);

        $repartition = [];
        $siegesTotauxParEntite = [];

        foreach ($data['entites'] as $entite) {
            $siegesTotauxParEntite[$entite->id] = [
                'sieges_total' => 0,
                'details_par_commune' => [],
            ];
        }

        foreach ($data['communes'] as $commune) {
            $communeData = $data['matrice'][$commune->id];
            $arrondissements = $communeData['arrondissements'];

            $nombreSiegesCommune = (int) $communeData['nombre_sieges'];   // somme des sièges officiels
            $populationCommune = (int) $communeData['population'];

            if ($nombreSiegesCommune <= 0 || $populationCommune <= 0) {
                $repartition[$commune->id] = [
                    'info' => $commune,
                    'nombre_sieges' => $nombreSiegesCommune,
                    'population' => $populationCommune,
                    'quotient_communal' => 0,
                    'repartition_arrondissements' => [],
                ];
                continue;
            }

            // Quotient communal (info)
            $quotientCommunal = $populationCommune / $nombreSiegesCommune;

            // ✅ SIÈGES PAR ARRONDISSEMENT = OFFICIEL arrondissements.siege
            $siegesParArrondissement = [];
            foreach ($arrondissements as $arr) {
                $siegesParArrondissement[$arr->id] = [
                    'arrondissement' => $arr,
                    'sieges_total' => (int) ($arr->siege ?? 0),
                    'sieges_quotient' => 0,
                    'sieges_reste' => 0,
                    'reste' => 0,
                ];
            }

            // Attribution sièges aux listes DANS chaque arrondissement
            $repartitionArrondissements = $this->attribuerSiegesAuxListesParArrondissement(
                $electionId,
                $commune->id,
                $siegesParArrondissement,
                $entitesEligibles
            );

            // Somme sièges par entité
            foreach ($repartitionArrondissements as $arrData) {
                foreach ($arrData['listes'] as $entiteId => $listeData) {
                    $siegesTotauxParEntite[$entiteId]['sieges_total'] += (int) ($listeData['sieges'] ?? 0);

                    if (!isset($siegesTotauxParEntite[$entiteId]['details_par_commune'][$commune->id])) {
                        $siegesTotauxParEntite[$entiteId]['details_par_commune'][$commune->id] = [
                            'commune_nom' => $commune->nom,
                            'sieges' => 0,
                        ];
                    }

                    $siegesTotauxParEntite[$entiteId]['details_par_commune'][$commune->id]['sieges'] += (int) ($listeData['sieges'] ?? 0);
                }
            }

            $repartition[$commune->id] = [
                'info' => $commune,
                'nombre_sieges' => $nombreSiegesCommune,
                'population' => $populationCommune,
                'quotient_communal' => $quotientCommunal,
                'repartition_arrondissements' => $repartitionArrondissements,
            ];
        }

        return [
            'eligibilite' => $eligibilite,
            'repartition' => $repartition,
            'sieges_totaux' => $siegesTotauxParEntite,
            'data' => $data,
        ];
    }

    /**
     * ✅ Attribuer les sièges aux listes DANS chaque arrondissement
     */
    private function attribuerSiegesAuxListesParArrondissement(
        int $electionId,
        int $communeId,
        array $siegesParArrondissement,
        array $entitesEligibles
    ): array {
        $repartitionArrondissements = [];

        foreach ($siegesParArrondissement as $arrId => $arrData) {
            $arrondissement = $arrData['arrondissement'];
            $siegesArrondissement = (int) ($arrData['sieges_total'] ?? 0);

            if ($siegesArrondissement <= 0) {
                continue;
            }

            // Voix par entité dans cet arrondissement (dédup par village)
            $voixParEntite = $this->getVoixParEntiteDansArrondissement($electionId, $arrId);
            $totalVoixArrondissement = array_sum($voixParEntite);

            // Si aucun vote, on garde l'arrondissement mais listes à 0
            $resultatsListes = [];
            foreach ($entitesEligibles as $entiteId => $eligData) {
                $voix = $voixParEntite[$entiteId] ?? 0;
                $pourcentage = $totalVoixArrondissement > 0 ? ($voix / $totalVoixArrondissement) * 100 : 0;

                $resultatsListes[$entiteId] = [
                    'entite_id' => $entiteId,
                    'voix' => (int) $voix,
                    'pourcentage' => $pourcentage,
                    'sieges' => 0,
                    'candidats' => [],
                ];
            }

            if ($totalVoixArrondissement > 0) {
                // 1 siège => uninominal majoritaire
                if ($siegesArrondissement === 1) {
                    $gagnant = $this->scrutinUninominalMajoritaire($resultatsListes);
                    if ($gagnant) {
                        $resultatsListes[$gagnant['entite_id']]['sieges'] = 1;
                    }
                } else {
                    // Plusieurs sièges => Article 187 (majorité + proportionnelle)
                    $resultatsListes = $this->attribuerSiegesSelonArticle187($resultatsListes, $siegesArrondissement);
                }

                // Candidats élus
                foreach ($resultatsListes as $entiteId => &$listeData) {
                    if (($listeData['sieges'] ?? 0) > 0) {
                        $listeData['candidats'] = $this->getCandidatsElus(
                            $electionId,
                            $entiteId,
                            $arrId,
                            (int) $listeData['sieges']
                        );
                    }
                }
                unset($listeData);
            }

            $repartitionArrondissements[$arrId] = [
                'arrondissement_id' => $arrId,
                'arrondissement_nom' => $arrondissement->nom,
                'sieges_arrondissement' => $siegesArrondissement,
                'sieges_attribues' => array_sum(array_column($resultatsListes, 'sieges')),
                'total_voix' => (int) $totalVoixArrondissement,
                'listes' => $resultatsListes,
                'details_repartition' => $arrData,
            ];
        }

        return $repartitionArrondissements;
    }

    /**
     * 1 siège : gagnant = plus grand nombre de voix
     */
    private function scrutinUninominalMajoritaire(array $resultatsListes): ?array
    {
        $maxVoix = -1;
        $gagnant = null;

        foreach ($resultatsListes as $listeData) {
            if ($listeData['voix'] > $maxVoix) {
                $maxVoix = $listeData['voix'];
                $gagnant = $listeData;
            }
        }

        return $gagnant;
    }

    /**
     * ✅ Article 187 : majorité + proportionnelle (plus forte moyenne)
     */
    private function attribuerSiegesSelonArticle187(array $resultatsListes, int $nombreSieges): array
    {
        $majoriteSieges = (int) ceil($nombreSieges / 2);

        // Trier par pourcentage décroissant
        uasort($resultatsListes, fn($a, $b) => $b['pourcentage'] <=> $a['pourcentage']);

        $listePremiere = reset($resultatsListes);
        $listeDeuxieme = next($resultatsListes);

        // >= 50% => majorité
        if (($listePremiere['pourcentage'] ?? 0) >= 50) {
            $resultatsListes[$listePremiere['entite_id']]['sieges'] = $majoriteSieges;

            $siegesRestants = $nombreSieges - $majoriteSieges;
            return $this->repartirSiegesRestants($resultatsListes, $listePremiere['entite_id'], $siegesRestants);
        }

        // >= 40% => majorité (et si 2 listes >=40, la plus forte garde la majorité)
        if (($listePremiere['pourcentage'] ?? 0) >= 40) {
            $resultatsListes[$listePremiere['entite_id']]['sieges'] = $majoriteSieges;

            $siegesRestants = $nombreSieges - $majoriteSieges;
            return $this->repartirSiegesRestants($resultatsListes, $listePremiere['entite_id'], $siegesRestants);
        }

        // Sinon proportionnelle (D'Hondt) en excluant <10% (local)
        $listesEligibles = array_filter($resultatsListes, fn($l) => ($l['pourcentage'] ?? 0) >= 10);
        if (empty($listesEligibles)) {
            $listesEligibles = $resultatsListes;
        }

        return $this->repartitionPlusForteMoyenne($listesEligibles, $nombreSieges, $resultatsListes);
    }

    /**
     * Répartir les sièges restants après attribution de la majorité
     */
    private function repartirSiegesRestants(array $resultatsListes, int $entiteMajorite, int $siegesRestants): array
    {
        if ($siegesRestants <= 0) {
            return $resultatsListes;
        }

        $listesRestantes = array_filter($resultatsListes, fn($l) => $l['entite_id'] != $entiteMajorite);

        $listesEligibles = array_filter($listesRestantes, fn($l) => ($l['pourcentage'] ?? 0) >= 10);
        if (empty($listesEligibles)) {
            $listesEligibles = $listesRestantes;
        }

        return $this->repartitionPlusForteMoyenne($listesEligibles, $siegesRestants, $resultatsListes);
    }

    /**
     * Plus forte moyenne (D'Hondt)
     */
    private function repartitionPlusForteMoyenne(array $listesEligibles, int $nombreSieges, array $tousResultats): array
    {
        $siegesActuels = [];
        foreach ($listesEligibles as $entiteId => $data) {
            $siegesActuels[$entiteId] = (int) ($tousResultats[$entiteId]['sieges'] ?? 0);
        }

        for ($i = 0; $i < $nombreSieges; $i++) {
            $maxMoyenne = -1;
            $gagnantId = null;

            foreach ($listesEligibles as $entiteId => $data) {
                $voix = (int) ($data['voix'] ?? 0);
                $sieges = (int) ($siegesActuels[$entiteId] ?? 0);

                $moyenne = $sieges >= 0 ? ($voix / ($sieges + 1)) : 0;

                // En cas d'égalité, plus grand nombre de suffrages
                $voixGagnant = $gagnantId ? (int) ($listesEligibles[$gagnantId]['voix'] ?? 0) : -1;

                if ($moyenne > $maxMoyenne || ($moyenne == $maxMoyenne && $voix > $voixGagnant)) {
                    $maxMoyenne = $moyenne;
                    $gagnantId = $entiteId;
                }
            }

            if ($gagnantId !== null) {
                $siegesActuels[$gagnantId]++;
                $tousResultats[$gagnantId]['sieges'] = (int) ($tousResultats[$gagnantId]['sieges'] ?? 0) + 1;
            }
        }

        return $tousResultats;
    }

    /**
     * Voix par entité dans un arrondissement (dédup par village)
     */
    private function getVoixParEntiteDansArrondissement(int $electionId, int $arrondissementId): array
    {
        $lignesAvecPv = DB::table('proces_verbaux as pv')
            ->join('pv_lignes as pl', 'pl.proces_verbal_id', '=', 'pv.id')
            ->where('pv.niveau', 'arrondissement')
            ->whereIn('pv.statut', ['valide', 'publie'])
            ->where('pv.election_id', $electionId)
            ->where('pv.niveau_id', $arrondissementId)
            ->whereNotNull('pl.village_quartier_id')
            ->selectRaw("
                pv.id AS proces_verbal_id,
                pv.created_at AS pv_created_at,
                pl.id AS pv_ligne_id,
                pl.village_quartier_id,
                pl.created_at AS ligne_created_at
            ");

        $dedup = DB::query()->fromSub($lignesAvecPv, 'l')
            ->select('l.*')
            ->selectRaw("
                ROW_NUMBER() OVER (
                    PARTITION BY l.village_quartier_id
                    ORDER BY l.pv_created_at DESC, l.ligne_created_at DESC, l.pv_ligne_id DESC
                ) AS rn
            ");

        $lignesRetenues = DB::query()->fromSub($dedup, 'd')->where('d.rn', 1);

        $results = DB::query()->fromSub($lignesRetenues, 'lr')
            ->join('pv_ligne_resultats as plr', 'plr.pv_ligne_id', '=', 'lr.pv_ligne_id')
            ->join('candidatures as ca', 'ca.id', '=', 'plr.candidature_id')
            ->select('ca.entite_politique_id', DB::raw('SUM(COALESCE(plr.nombre_voix, 0)) as total_voix'))
            ->groupBy('ca.entite_politique_id')
            ->get();

        $voix = [];
        foreach ($results as $result) {
            $voix[$result->entite_politique_id] = (int) $result->total_voix;
        }

        return $voix;
    }

    /**
     * Candidats élus (Article 187.7-187.8)
     */
    private function getCandidatsElus(int $electionId, int $entiteId, int $arrondissementId, int $nombreSieges): array
    {
        $candidature = DB::table('candidatures')
            ->where('election_id', $electionId)
            ->where('entite_politique_id', $entiteId)
            ->where('statut', 'validee')
            ->where(function ($query) use ($arrondissementId) {
                $query->where('arrondissement_id', $arrondissementId)
                    ->orWhereRaw("data::jsonb @> ?", [json_encode(['arrondissement_id' => $arrondissementId])]);
            })
            ->first();

        if (!$candidature) {
            $arrondissement = DB::table('arrondissements')->find($arrondissementId);
            if ($arrondissement) {
                $candidature = DB::table('candidatures')
                    ->where('election_id', $electionId)
                    ->where('entite_politique_id', $entiteId)
                    ->where('statut', 'validee')
                    ->whereRaw("data::jsonb->>'arrondissement' = ?", [$arrondissement->nom])
                    ->first();
            }
        }

        $candidats = [];

        if ($candidature && $candidature->data) {
            $data = json_decode($candidature->data, true);

            if (isset($data['candidats']) && is_array($data['candidats'])) {
                usort($data['candidats'], fn($a, $b) => ($a['position'] ?? 999) <=> ($b['position'] ?? 999));
                $candidatsElus = array_slice($data['candidats'], 0, $nombreSieges);

                foreach ($candidatsElus as $candidat) {
                    $candidats[] = [
                        'position' => $candidat['position'] ?? null,
                        'titulaire' => $candidat['titulaire'] ?? null,
                        'suppleant' => $candidat['suppleant'] ?? null,
                        'no' => $candidat['no'] ?? null,
                    ];
                }
            }
        }

        if (empty($candidats) && $candidature && $candidature->tete_liste) {
            $candidats[] = [
                'position' => 1,
                'titulaire' => $candidature->tete_liste,
                'suppleant' => null,
                'no' => null,
            ];
        }

        return $candidats;
    }

    /**
     * Export CSV - Matrice des résultats
     */
    public function exporterResultatsCSV(int $electionId): string
    {
        $data = $this->getResultatsParCommune($electionId);
        $csv = chr(0xEF).chr(0xBB).chr(0xBF);

        $csv .= "Commune;Département;Population;Sièges";
        foreach ($data['entites'] as $entite) {
            $nom = $entite->sigle ?: $entite->nom;
            $csv .= ";{$nom} (Voix);{$nom} (%)";
        }
        $csv .= ";Total Voix\n";

        $totalPopulation = 0;
        $totalSieges = 0;

        foreach ($data['communes'] as $commune) {
            $population = $data['matrice'][$commune->id]['population'] ?? 0;
            $sieges = $data['matrice'][$commune->id]['nombre_sieges'] ?? 0;
            $totalPopulation += (int) $population;
            $totalSieges += (int) $sieges;

            $csv .= "{$commune->nom};{$commune->departement_nom};{$population};{$sieges}";

            foreach ($data['entites'] as $entite) {
                $voix = $data['matrice'][$commune->id]['resultats'][$entite->id]['voix'] ?? 0;
                $pct = $data['matrice'][$commune->id]['resultats'][$entite->id]['pourcentage'] ?? 0;
                $csv .= ";{$voix};" . number_format($pct, 2, ',', '');
            }

            $csv .= ";" . ($data['matrice'][$commune->id]['total_voix'] ?? 0) . "\n";
        }

        $csv .= "\nTOTAL NATIONAL;;{$totalPopulation};{$totalSieges}";
        foreach ($data['entites'] as $entite) {
            $totalVoix = $data['totaux_par_entite'][$entite->id]['voix'] ?? 0;
            $pctNational = $data['totaux_par_entite'][$entite->id]['pourcentage_national'] ?? 0;
            $csv .= ";{$totalVoix};" . number_format($pctNational, 2, ',', '');
        }
        $csv .= ";" . ($data['total_voix_national'] ?? 0) . "\n";

        return $csv;
    }

    /**
     * Export CSV - Sièges par parti
     */
    public function exporterSiegesCSV(int $electionId): string
    {
        $result = $this->repartirSieges($electionId);
        $csv = chr(0xEF).chr(0xBB).chr(0xBF);

        $csv .= "Entité Politique;Sigle;Total Sièges;% National;Détails par Commune\n";

        foreach ($result['sieges_totaux'] as $entiteId => $sieges) {
            $entite = collect($result['data']['entites'])->firstWhere('id', $entiteId);

            if ($entite && ($sieges['sieges_total'] ?? 0) > 0) {
                $pctNational = $result['data']['totaux_par_entite'][$entiteId]['pourcentage_national'] ?? 0;

                $detailsCommunes = [];
                foreach (($sieges['details_par_commune'] ?? []) as $communeData) {
                    $detailsCommunes[] = "{$communeData['commune_nom']} ({$communeData['sieges']})";
                }

                $csv .= "{$entite->nom};{$entite->sigle};{$sieges['sieges_total']};";
                $csv .= number_format($pctNational, 2, ',', '') . ";";
                $csv .= implode(', ', $detailsCommunes) . "\n";
            }
        }

        return $csv;
    }

    /**
     * ✅ Export CSV - Détails par commune (INTÈGRE les arrondissements)
     * Format "par arrondissement + parti" + si besoin tu peux filtrer les lignes à sièges>0.
     */
    public function exporterDetailsParCommune(int $electionId): string
    {
        $result = $this->repartirSieges($electionId);
        $csv = chr(0xEF).chr(0xBB).chr(0xBF);

        $csv .= "Département;Commune;Population;Sièges Commune;Quotient Communal;Arrondissement;Population Arr.;Sièges Arr.;";
        $csv .= "Parti;Voix;% Arr.;Sièges;Mode Attribution\n";

        foreach ($result['repartition'] as $communeId => $rep) {
            $commune = $rep['info'];

            if (empty($rep['repartition_arrondissements'])) {
                continue;
            }

            foreach ($rep['repartition_arrondissements'] as $arrId => $arrData) {
                $details = $arrData['details_repartition'];
                $arrObj = $details['arrondissement'] ?? null;

                if (!$arrObj) continue;

                if (empty($arrData['listes'])) {
                    // même si aucun vote : on peut écrire une ligne "Aucun vote"
                    $csv .= "{$commune->departement_nom};{$commune->nom};{$rep['population']};{$rep['nombre_sieges']};";
                    $csv .= number_format($rep['quotient_communal'], 2, ',', '') . ";";
                    $csv .= "{$arrData['arrondissement_nom']};{$arrObj->population};{$arrData['sieges_arrondissement']};";
                    $csv .= "Aucun vote;0;0;0;-\n";
                    continue;
                }

                // Pour chaque parti (éligible) on sort une ligne (tu peux filtrer si tu veux: if ($listeData['sieges']==0) continue;)
                foreach ($arrData['listes'] as $entiteId => $listeData) {
                    $entite = collect($result['data']['entites'])->firstWhere('id', $entiteId);

                    $modeAttribution = '';
                    if ($arrData['sieges_arrondissement'] == 1 && ($listeData['sieges'] ?? 0) > 0) {
                        $modeAttribution = 'Uninominal';
                    } elseif (($listeData['pourcentage'] ?? 0) >= 50 && ($listeData['sieges'] ?? 0) > 0) {
                        $modeAttribution = 'Majorité 50%+';
                    } elseif (($listeData['pourcentage'] ?? 0) >= 40 && ($listeData['sieges'] ?? 0) > 0) {
                        $modeAttribution = 'Majorité 40%+';
                    } elseif (($listeData['sieges'] ?? 0) > 0) {
                        $modeAttribution = 'Proportionnelle';
                    } else {
                        $modeAttribution = '';
                    }

                    $csv .= "{$commune->departement_nom};{$commune->nom};{$rep['population']};{$rep['nombre_sieges']};";
                    $csv .= number_format($rep['quotient_communal'], 2, ',', '') . ";";
                    $csv .= "{$arrData['arrondissement_nom']};{$arrObj->population};{$arrData['sieges_arrondissement']};";
                    $csv .= ($entite ? ($entite->sigle ?: $entite->nom) : $entiteId) . ";";
                    $csv .= ($listeData['voix'] ?? 0) . ";";
                    $csv .= number_format(($listeData['pourcentage'] ?? 0), 2, ',', '') . ";";
                    $csv .= ($listeData['sieges'] ?? 0) . ";{$modeAttribution}\n";
                }
            }
        }

        return $csv;
    }

    /**
     * Export CSV - Détails par arrondissement avec candidats
     */
    public function exporterDetailsParArrondissement(int $electionId): string
    {
        $result = $this->repartirSieges($electionId);
        $csv = chr(0xEF).chr(0xBB).chr(0xBF);

        $csv .= "Commune;Arrondissement;Population;Sièges Arr.;Quotient Communal;";
        $csv .= "Parti;Voix;% Arr.;Sièges;Mode Attribution;Candidats Élus\n";

        foreach ($result['repartition'] as $communeId => $rep) {
            $commune = $rep['info'];

            if (empty($rep['repartition_arrondissements'])) {
                continue;
            }

            foreach ($rep['repartition_arrondissements'] as $arrId => $arrData) {
                $details = $arrData['details_repartition'];

                if (empty($arrData['listes'])) {
                    continue;
                }

                foreach ($arrData['listes'] as $entiteId => $listeData) {
                    if (($listeData['sieges'] ?? 0) == 0) continue;

                    $entite = collect($result['data']['entites'])->firstWhere('id', $entiteId);

                    $modeAttribution = '';
                    if ($arrData['sieges_arrondissement'] == 1) {
                        $modeAttribution = 'Uninominal';
                    } elseif (($listeData['pourcentage'] ?? 0) >= 50) {
                        $modeAttribution = 'Majorité 50%+';
                    } elseif (($listeData['pourcentage'] ?? 0) >= 40) {
                        $modeAttribution = 'Majorité 40%+';
                    } else {
                        $modeAttribution = 'Proportionnelle';
                    }

                    $candidatsStr = '';
                    if (!empty($listeData['candidats'])) {
                        $candidatsNoms = [];
                        foreach ($listeData['candidats'] as $candidat) {
                            $nom = $candidat['titulaire'] ?? 'Inconnu';
                            $candidatsNoms[] = "{$candidat['position']}. {$nom}";
                        }
                        $candidatsStr = implode(' | ', $candidatsNoms);
                    }

                    $csv .= "{$commune->nom};{$arrData['arrondissement_nom']};";
                    $csv .= ($details['arrondissement']->population ?? 0) . ";{$arrData['sieges_arrondissement']};";
                    $csv .= number_format($rep['quotient_communal'], 2, ',', '') . ";";
                    $csv .= ($entite ? ($entite->sigle ?: $entite->nom) : $entiteId) . ";";
                    $csv .= ($listeData['voix'] ?? 0) . ";" . number_format(($listeData['pourcentage'] ?? 0), 2, ',', '') . ";";
                    $csv .= ($listeData['sieges'] ?? 0) . ";{$modeAttribution};";
                    $csv .= "\"{$candidatsStr}\"\n";
                }
            }
        }

        return $csv;
    }

    /**
     * Résumé
     */
    public function getResume(int $electionId): array
    {
        $compilation = $this->repartirSieges($electionId);

        $nbEntitesEligibles = count(array_filter($compilation['eligibilite'], fn($e) => $e['eligible']));
        $nbEntitesTotal = count($compilation['data']['entites']);
        $totalSieges = array_sum(array_column($compilation['sieges_totaux'], 'sieges_total'));

        return [
            'nb_entites_total' => $nbEntitesTotal,
            'nb_entites_eligibles' => $nbEntitesEligibles,
            'nb_communes' => count($compilation['data']['communes']),
            'total_voix_national' => $compilation['data']['total_voix_national'],
            'total_sieges' => $totalSieges,
        ];
    }
}
