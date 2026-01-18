<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class ResultatsCommunalesService
{
    private StatsService $statsService;

    public function __construct(StatsService $statsService)
    {
        $this->statsService = $statsService;
    }

    /**
     * Obtenir les résultats par commune pour une élection communale
     * ✅ Logique : TOUS les PV arrondissement + dédup par (arrondissement, village) via created_at
     */
    public function getResultatsParCommune(int $electionId): array
    {
        // ✅ Récupérer toutes les communes (exclure Diaspora si nécessaire)
        $communes = DB::table('communes as c')
            ->join('departements as d', 'd.id', '=', 'c.departement_id')
            ->select('c.id', 'c.nom', 'c.code', 'd.nom as departement_nom')
            ->where('d.id', '<>', 13) // Exclure département Diaspora
            ->orderBy('c.nom')
            ->get();

        // ✅ Entités politiques candidates
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
            // Récupérer le nombre de sièges pour cette commune
            $nombreSieges = (int) DB::table('arrondissements')
                ->where('commune_id', $commune->id)
                ->sum('siege');

            // Récupérer la population de la commune
            $population = (int) DB::table('arrondissements')
                ->where('commune_id', $commune->id)
                ->sum('population');

            $matrice[$commune->id] = [
                'info' => $commune,
                'nombre_sieges' => $nombreSieges,
                'population' => $population,
                'resultats' => [],
                'total_voix' => 0,
            ];

            $voixCommune = $this->getVoixParEntiteDansCommune($electionId, $commune->id);

            $totalVoixCommune = array_sum($voixCommune);
            $matrice[$commune->id]['total_voix'] = $totalVoixCommune;
            $totauxParCommune[$commune->id] = $totalVoixCommune;

            foreach ($entites as $entite) {
                $voix = $voixCommune[$entite->id] ?? 0;
                $pourcentage = $totalVoixCommune > 0 ? ($voix / $totalVoixCommune) * 100 : 0;

                $matrice[$commune->id]['resultats'][$entite->id] = [
                    'voix' => $voix,
                    'pourcentage' => $pourcentage,
                ];

                $totauxParEntite[$entite->id]['voix'] += $voix;
            }
        }

        // Calcul du pourcentage national
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
     * ✅ Récupérer les voix par entité dans une commune
     * Logique : Dédupliquer les PV arrondissement par (arrondissement_id, village_quartier_id)
     */
    private function getVoixParEntiteDansCommune(int $electionId, int $communeId): array
    {
        // 1) Base : toutes les lignes de PV arrondissement valides/publiés dans la commune
        $lignesAvecPv = DB::table('proces_verbaux as pv')
            ->join('pv_lignes as pl', 'pl.proces_verbal_id', '=', 'pv.id')
            ->join('arrondissements as a', 'a.id', '=', DB::raw('pv.niveau_id::int'))
            ->where('pv.niveau', 'arrondissement')
            ->whereIn('pv.statut', ['valide', 'publie'])
            ->where('pv.election_id', $electionId)
            ->where('a.commune_id', $communeId)
            ->whereNotNull('pl.village_quartier_id')
            ->selectRaw("
                a.id                 AS arrondissement_id,
                pv.id                AS proces_verbal_id,
                pv.created_at        AS pv_created_at,
                pl.id                AS pv_ligne_id,
                pl.village_quartier_id,
                pl.created_at        AS ligne_created_at
            ");

        // 2) Dédup : garder la dernière ligne par (arrondissement, village)
        $dedup = DB::query()->fromSub($lignesAvecPv, 'l')
            ->select('l.*')
            ->selectRaw("
                ROW_NUMBER() OVER (
                    PARTITION BY l.arrondissement_id, l.village_quartier_id
                    ORDER BY l.pv_created_at DESC, l.ligne_created_at DESC, l.pv_ligne_id DESC
                ) AS rn
            ");

        $lignesRetenues = DB::query()->fromSub($dedup, 'd')
            ->where('d.rn', 1);

        // 3) Somme des voix par entité politique
        $results = DB::query()->fromSub($lignesRetenues, 'lr')
            ->join('pv_ligne_resultats as plr', 'plr.pv_ligne_id', '=', 'lr.pv_ligne_id')
            ->join('candidatures as ca', 'ca.id', '=', 'plr.candidature_id')
            ->select(
                'ca.entite_politique_id',
                DB::raw('SUM(COALESCE(plr.nombre_voix, 0)) as total_voix')
            )
            ->groupBy('ca.entite_politique_id')
            ->get();

        $voix = [];
        foreach ($results as $result) {
            $voix[$result->entite_politique_id] = (int) $result->total_voix;
        }

        return $voix;
    }

    /**
     * ÉTAPE 1 : Vérifier l'éligibilité au niveau national
     * Seuil : ≥ 10% des suffrages exprimés au plan national
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
     * Répartir les sièges dans toutes les communes
     * 
     * ÉTAPE 2 : Quotient Électoral par commune
     * ÉTAPE 3 : Attribution au quotient
     * ÉTAPE 4 : Attribution au plus fort reste
     * ÉTAPE 5 : Répartition interne par arrondissement
     */
    public function repartirSieges(int $electionId): array
    {
        // Vérifier l'éligibilité nationale
        $eligibiliteData = $this->verifierEligibiliteNationale($electionId);
        $eligibilite = $eligibiliteData['eligibilite'];
        $data = $eligibiliteData['data'];

        // Filtrer les entités éligibles (≥ 10% au national)
        $entitesEligibles = array_filter($eligibilite, fn($e) => $e['eligible']);

        $repartition = [];
        $siegesTotauxParEntite = [];

        // Initialiser les totaux
        foreach ($data['entites'] as $entite) {
            $siegesTotauxParEntite[$entite->id] = [
                'sieges_total' => 0,
                'details_par_commune' => [],
            ];
        }

        // Répartir les sièges commune par commune
        foreach ($data['communes'] as $commune) {
            $nombreSieges = $data['matrice'][$commune->id]['nombre_sieges'];
            $totalVoixCommune = $data['matrice'][$commune->id]['total_voix'];

            // Si pas de sièges ou pas de voix, passer
            if ($nombreSieges == 0 || $totalVoixCommune == 0) {
                $repartition[$commune->id] = [
                    'info' => $commune,
                    'nombre_sieges' => $nombreSieges,
                    'population' => $data['matrice'][$commune->id]['population'],
                    'sieges_attribues' => [],
                    'quotient_electoral' => 0,
                    'details' => [],
                    'repartition_arrondissements' => [],
                ];
                continue;
            }

            // ÉTAPE 2 : Calcul du Quotient Électoral
            $quotientElectoral = $totalVoixCommune / $nombreSieges;

            $siegesAttribues = [];
            $restesVoix = [];
            $details = [];

            // ÉTAPE 3 : Attribution au quotient
            foreach ($entitesEligibles as $entiteId => $eligData) {
                $voix = $data['matrice'][$commune->id]['resultats'][$entiteId]['voix'] ?? 0;
                
                if ($voix == 0) {
                    $siegesAttribues[$entiteId] = 0;
                    continue;
                }

                // Division entière
                $siegesQuotient = floor($voix / $quotientElectoral);
                $reste = $voix - ($siegesQuotient * $quotientElectoral);

                $siegesAttribues[$entiteId] = (int) $siegesQuotient;
                $restesVoix[$entiteId] = $reste;

                $details[$entiteId] = [
                    'voix' => $voix,
                    'sieges_quotient' => (int) $siegesQuotient,
                    'reste' => $reste,
                    'sieges_reste' => 0,
                    'sieges_total' => (int) $siegesQuotient,
                ];
            }

            // ÉTAPE 4 : Attribution au plus fort reste
            $siegesRestants = $nombreSieges - array_sum($siegesAttribues);

            if ($siegesRestants > 0) {
                // Trier par reste décroissant
                arsort($restesVoix);

                foreach ($restesVoix as $entiteId => $reste) {
                    if ($siegesRestants <= 0) break;

                    $siegesAttribues[$entiteId]++;
                    $details[$entiteId]['sieges_reste']++;
                    $details[$entiteId]['sieges_total']++;
                    $siegesRestants--;
                }
            }

            // Mise à jour des totaux
            foreach ($siegesAttribues as $entiteId => $nbSieges) {
                if ($nbSieges > 0) {
                    $siegesTotauxParEntite[$entiteId]['sieges_total'] += $nbSieges;
                    $siegesTotauxParEntite[$entiteId]['details_par_commune'][$commune->id] = [
                        'commune_nom' => $commune->nom,
                        'sieges' => $nbSieges,
                    ];
                }
            }

            // ÉTAPE 5 : Répartition interne par arrondissement (✅ CORRIGÉE)
            $repartitionArrondissements = $this->repartirSiegesParArrondissement(
                $electionId,
                $commune->id,
                $siegesAttribues,
                $entitesEligibles
            );

            $repartition[$commune->id] = [
                'info' => $commune,
                'nombre_sieges' => $nombreSieges,
                'population' => $data['matrice'][$commune->id]['population'],
                'sieges_attribues' => $siegesAttribues,
                'quotient_electoral' => $quotientElectoral,
                'details' => $details,
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
     * ✅ ÉTAPE 5 CORRIGÉE : Projeter les sièges gagnés au niveau communal sur les arrondissements
     * Conforme à la loi béninoise : privilégier les arrondissements où le parti a obtenu le plus de voix
     */
    private function repartirSiegesParArrondissement(
        int $electionId,
        int $communeId,
        array $siegesParEntite,  // Sièges DÉJÀ attribués au niveau communal
        array $entitesEligibles
    ): array {
        $repartition = [];

        // Récupérer les arrondissements de la commune avec leurs sièges
        $arrondissements = DB::table('arrondissements')
            ->where('commune_id', $communeId)
            ->orderBy('nom')
            ->get();

        // Créer une structure indexée par arrondissement
        $repartitionParArrondissement = [];
        
        foreach ($arrondissements as $arr) {
            $repartitionParArrondissement[$arr->id] = [
                'arrondissement_id' => $arr->id,
                'arrondissement_nom' => $arr->nom,
                'sieges_arrondissement' => (int) $arr->siege,
                'sieges_attribues' => 0,
                'partis' => [],
            ];
        }

        // Pour chaque entité ayant gagné des sièges dans la commune
        foreach ($siegesParEntite as $entiteId => $siegesCommunaux) {
            if ($siegesCommunaux == 0) {
                continue;
            }

            // Récupérer les voix de cette entité dans chaque arrondissement
            $voixParArrondissement = [];
            
            foreach ($arrondissements as $arr) {
                $voix = $this->getVoixEntiteDansArrondissement($electionId, $arr->id, $entiteId);
                
                if ($voix > 0) {
                    $voixParArrondissement[] = [
                        'arrondissement_id' => $arr->id,
                        'arrondissement_nom' => $arr->nom,
                        'voix' => $voix,
                        'sieges_disponibles' => (int) $arr->siege,
                    ];
                }
            }

            // Trier les arrondissements par nombre de voix (décroissant)
            usort($voixParArrondissement, fn($a, $b) => $b['voix'] <=> $a['voix']);

            // Distribuer les sièges du parti dans les arrondissements
            $siegesRestants = $siegesCommunaux;

            foreach ($voixParArrondissement as $arr) {
                if ($siegesRestants <= 0) {
                    break;
                }

                $arrId = $arr['arrondissement_id'];
                $siegesDisponibles = $arr['sieges_disponibles'];

                // Vérifier combien de sièges ont déjà été attribués dans cet arrondissement
                $siegesDejaAttribues = $repartitionParArrondissement[$arrId]['sieges_attribues'];
                $capaciteRestante = $siegesDisponibles - $siegesDejaAttribues;

                if ($capaciteRestante <= 0) {
                    continue; // Arrondissement plein
                }

                // Calculer combien de sièges on peut attribuer
                $siegesAAttribuer = min($siegesRestants, $capaciteRestante);

                if ($siegesAAttribuer > 0) {
                    // ✅ NOUVEAU : Récupérer les candidats pour cet arrondissement
                    $candidats = $this->getCandidatsElus($electionId, $entiteId, $arrId, $siegesAAttribuer);

                    // Ajouter à la répartition par arrondissement
                    $repartitionParArrondissement[$arrId]['partis'][$entiteId] = [
                        'entite_id' => $entiteId,
                        'sieges' => $siegesAAttribuer,
                        'voix' => $arr['voix'],
                        'candidats' => $candidats, // ✅ AJOUT DES CANDIDATS
                    ];

                    $repartitionParArrondissement[$arrId]['sieges_attribues'] += $siegesAAttribuer;
                    $siegesRestants -= $siegesAAttribuer;
                }
            }

            // ⚠️ ALERTE : Si des sièges restent non attribués
            if ($siegesRestants > 0) {
                \Log::warning("Entité {$entiteId} : {$siegesRestants} siège(s) non attribué(s) dans la commune {$communeId}");
            }
        }

        return $repartitionParArrondissement;
    }

    /**
     * ✅ NOUVELLE MÉTHODE : Récupérer les candidats élus dans un arrondissement
     */
    private function getCandidatsElus(int $electionId, int $entiteId, int $arrondissementId, int $nombreSieges): array
    {
        // Récupérer la candidature pour cette entité et cet arrondissement
        $candidature = DB::table('candidatures')
            ->where('election_id', $electionId)
            ->where('entite_politique_id', $entiteId)
            ->where('statut', 'validee')
            ->where(function($query) use ($arrondissementId) {
                // Chercher d'abord avec arrondissement_id
                $query->where('arrondissement_id', $arrondissementId)
                      // Sinon chercher dans le JSON data
                      ->orWhereRaw("data::jsonb @> ?", [json_encode(['arrondissement_id' => $arrondissementId])]);
            })
            ->first();

        if (!$candidature) {
            // Essayer de trouver via le nom de l'arrondissement dans le JSON
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
                // Trier par position
                usort($data['candidats'], fn($a, $b) => ($a['position'] ?? 999) <=> ($b['position'] ?? 999));
                
                // Prendre les N premiers candidats selon le nombre de sièges
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

        // Si on n'a pas trouvé de candidats dans data, utiliser tete_liste
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
     * Récupérer les voix d'une entité dans un arrondissement
     */
    private function getVoixEntiteDansArrondissement(int $electionId, int $arrondissementId, int $entiteId): int
    {
        // 1) Base : lignes de PV arrondissement
        $lignesAvecPv = DB::table('proces_verbaux as pv')
            ->join('pv_lignes as pl', 'pl.proces_verbal_id', '=', 'pv.id')
            ->where('pv.niveau', 'arrondissement')
            ->whereIn('pv.statut', ['valide', 'publie'])
            ->where('pv.election_id', $electionId)
            ->where('pv.niveau_id', $arrondissementId)
            ->whereNotNull('pl.village_quartier_id')
            ->selectRaw("
                pv.id                AS proces_verbal_id,
                pv.created_at        AS pv_created_at,
                pl.id                AS pv_ligne_id,
                pl.village_quartier_id,
                pl.created_at        AS ligne_created_at
            ");

        // 2) Dédup par village
        $dedup = DB::query()->fromSub($lignesAvecPv, 'l')
            ->select('l.*')
            ->selectRaw("
                ROW_NUMBER() OVER (
                    PARTITION BY l.village_quartier_id
                    ORDER BY l.pv_created_at DESC, l.ligne_created_at DESC, l.pv_ligne_id DESC
                ) AS rn
            ");

        $lignesRetenues = DB::query()->fromSub($dedup, 'd')
            ->where('d.rn', 1);

        // 3) Somme des voix pour cette entité
        $total = DB::query()->fromSub($lignesRetenues, 'lr')
            ->join('pv_ligne_resultats as plr', 'plr.pv_ligne_id', '=', 'lr.pv_ligne_id')
            ->join('candidatures as ca', 'ca.id', '=', 'plr.candidature_id')
            ->where('ca.entite_politique_id', $entiteId)
            ->sum('plr.nombre_voix');

        return (int) ($total ?? 0);
    }

    /**
     * Exporter la matrice des résultats en CSV
     */
    public function exporterResultatsCSV(int $electionId): string
    {
        $data = $this->getResultatsParCommune($electionId);
        $csv = chr(0xEF).chr(0xBB).chr(0xBF); // UTF-8 BOM

        // En-têtes
        $csv .= "Commune;Département;Population;Sièges";
        foreach ($data['entites'] as $entite) {
            $nom = $entite->sigle ?: $entite->nom;
            $csv .= ";{$nom} (Voix);{$nom} (%)";
        }
        $csv .= ";Total Voix\n";

        // Lignes par commune
        $totalPopulation = 0;
        $totalSieges = 0;
        
        foreach ($data['communes'] as $commune) {
            $population = $data['matrice'][$commune->id]['population'] ?? 0;
            $sieges = $data['matrice'][$commune->id]['nombre_sieges'];
            $totalPopulation += $population;
            $totalSieges += $sieges;
            
            $csv .= "{$commune->nom};{$commune->departement_nom};{$population};{$sieges}";
            
            foreach ($data['entites'] as $entite) {
                $voix = $data['matrice'][$commune->id]['resultats'][$entite->id]['voix'] ?? 0;
                $pct = $data['matrice'][$commune->id]['resultats'][$entite->id]['pourcentage'] ?? 0;
                $csv .= ";{$voix};" . number_format($pct, 2, ',', '');
            }
            
            $csv .= ";" . ($data['matrice'][$commune->id]['total_voix'] ?? 0) . "\n";
        }

        // Totaux nationaux
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
     * Exporter les sièges en CSV
     */
    public function exporterSiegesCSV(int $electionId): string
    {
        $result = $this->repartirSieges($electionId);
        $csv = chr(0xEF).chr(0xBB).chr(0xBF); // UTF-8 BOM

        // En-tête
        $csv .= "Entité Politique;Sigle;Total Sièges;% National;Détails par Commune\n";

        foreach ($result['sieges_totaux'] as $entiteId => $sieges) {
            $entite = collect($result['data']['entites'])->firstWhere('id', $entiteId);
            
            if ($entite && ($sieges['sieges_total'] ?? 0) > 0) {
                $pctNational = $result['data']['totaux_par_entite'][$entiteId]['pourcentage_national'] ?? 0;
                
                $detailsCommunes = [];
                foreach ($sieges['details_par_commune'] as $communeData) {
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
     * Exporter les détails par commune en CSV
     */
    public function exporterDetailsParCommune(int $electionId): string
    {
        $result = $this->repartirSieges($electionId);
        $csv = chr(0xEF).chr(0xBB).chr(0xBF); // UTF-8 BOM

        // En-tête
        $csv .= "Commune;Département;Population;Sièges à Pourvoir;Quotient Electoral;";
        
        $entites = $result['data']['entites'];
        foreach ($entites as $entite) {
            $nom = $entite->sigle ?: $entite->nom;
            $csv .= "{$nom} (Voix);{$nom} (% Commune);{$nom} (Sièges Q);{$nom} (Sièges R);{$nom} (Total);";
        }
        $csv .= "Total Attribués;Vérification\n";

        // Lignes par commune
        foreach ($result['repartition'] as $communeId => $rep) {
            $commune = $rep['info'];
            
            $csv .= "{$commune->nom};{$commune->departement_nom};{$rep['population']};{$rep['nombre_sieges']};";
            $csv .= number_format($rep['quotient_electoral'], 2, ',', '') . ";";

            $totalVoixCommune = $result['data']['matrice'][$communeId]['total_voix'];
            $totalSiegesAttribues = 0;
            
            foreach ($entites as $entite) {
                $detail = $rep['details'][$entite->id] ?? null;
                
                if ($detail) {
                    $pctCommune = $totalVoixCommune > 0 ? ($detail['voix'] / $totalVoixCommune) * 100 : 0;
                    $csv .= "{$detail['voix']};" . number_format($pctCommune, 2, ',', '') . ";";
                    $csv .= "{$detail['sieges_quotient']};{$detail['sieges_reste']};{$detail['sieges_total']};";
                    $totalSiegesAttribues += $detail['sieges_total'];
                } else {
                    $csv .= "0;0,00;0;0;0;";
                }
            }
            
            // Vérification
            $verif = ($totalSiegesAttribues == $rep['nombre_sieges']) ? 'OK' : 'ERREUR';
            $csv .= "{$totalSiegesAttribues};{$verif}\n";
        }

        return $csv;
    }

    /**
     * ✅ Exporter les détails par arrondissement en CSV (AVEC CANDIDATS)
     */
    public function exporterDetailsParArrondissement(int $electionId): string
    {
        $result = $this->repartirSieges($electionId);
        $csv = chr(0xEF).chr(0xBB).chr(0xBF); // UTF-8 BOM

        // En-tête
        $csv .= "Commune;Arrondissement;Sièges Arrondissement;Parti;Sièges Attribués;Voix;Candidats Élus\n";

        // Lignes par commune/arrondissement
        foreach ($result['repartition'] as $communeId => $rep) {
            $commune = $rep['info'];
            
            if (empty($rep['repartition_arrondissements'])) {
                continue;
            }

            foreach ($rep['repartition_arrondissements'] as $arrId => $arrData) {
                if (empty($arrData['partis'])) {
                    continue;
                }

                foreach ($arrData['partis'] as $entiteId => $partiData) {
                    $entite = collect($result['data']['entites'])->firstWhere('id', $entiteId);
                    
                    // Formater les candidats
                    $candidatsStr = '';
                    if (!empty($partiData['candidats'])) {
                        $candidatsNoms = [];
                        foreach ($partiData['candidats'] as $candidat) {
                            $nom = $candidat['titulaire'] ?? 'Inconnu';
                            $candidatsNoms[] = "{$candidat['position']}. {$nom}";
                        }
                        $candidatsStr = implode(' | ', $candidatsNoms);
                    }
                    
                    $csv .= "{$commune->nom};{$arrData['arrondissement_nom']};{$arrData['sieges_arrondissement']};";
                    $csv .= ($entite->sigle ?: $entite->nom) . ";";
                    $csv .= "{$partiData['sieges']};{$partiData['voix']};";
                    $csv .= "\"{$candidatsStr}\"\n";
                }
            }
        }

        return $csv;
    }

    /**
     * Obtenir un résumé des résultats
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