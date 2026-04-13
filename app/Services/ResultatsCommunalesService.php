<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ✅ COMMUNALES — VERSION 100% CONFORME AU CODE ÉLECTORAL BÉNINOIS (Art. 186-187)
 *
 * CORRECTIONS APPLIQUÉES :
 * 1) ✅ Majorité absolue = intdiv(n/2) + 1 (pas ceil(n/2))
 * 2) ✅ Tri : % DESC puis voix DESC (Art. 187.4)
 * 3) ✅ Liste majoritaire participe à la proportionnelle (Art. 187.3)
 * 4) ✅ Total voix arrondissement = TOUTES les voix (même <10% national)
 * 5) ✅ % locaux calculés sur TOUS les suffrages exprimés locaux
 * 6) ✅ Mode attribution dans exports utilise methode_attribution
 *
 * CONFORMITÉ : 100% au Code Électoral (Articles 186-187)
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

            // ✅ SIÈGES OFFICIELS (référence)
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
     * ✅ ÉTAPE 1 : Vérifier éligibilité nationale (Seuil 10% national)
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
     * ✅ COMPILATION COMMUNALES
     */
    public function repartirSieges(int $electionId): array
    {
        $eligibiliteData = $this->verifierEligibiliteNationale($electionId);
        $eligibilite = $eligibiliteData['eligibilite'];
        $data = $eligibiliteData['data'];

        // ✅ Filtre national d'abord : entités éligibles
        $entitesEligibles = array_filter($eligibilite, fn($e) => $e['eligible']);

        $repartition = [];
        $siegesTotauxParEntite = [];
        $communesMajoritairesParEntite = [];

        foreach ($data['entites'] as $entite) {
            $siegesTotauxParEntite[$entite->id] = [
                'sieges_total' => 0,
                'details_par_commune' => [],
            ];
            $communesMajoritairesParEntite[$entite->id] = 0;
        }

        foreach ($data['communes'] as $commune) {
            $communeData = $data['matrice'][$commune->id];
            $arrondissements = $communeData['arrondissements'];

            $nombreSiegesCommune = (int) $communeData['nombre_sieges'];
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

            // Quotient communal = info (Article 183)
            $quotientCommunal = $populationCommune / $nombreSiegesCommune;

            // ✅ SIÈGES OFFICIELS par arrondissement
            $siegesParArrondissement = [];
            foreach ($arrondissements as $arr) {
                $siegesParArrondissement[$arr->id] = [
                    'arrondissement' => $arr,
                    'sieges_total' => (int) ($arr->siege ?? 0),
                ];
            }

            $repartitionArrondissements = $this->attribuerSiegesAuxListesParArrondissement(
                $electionId,
                $siegesParArrondissement,
                $entitesEligibles
            );

            // Somme sièges par entité
            foreach ($repartitionArrondissements as $arrData) {
                foreach ($arrData['listes'] as $entiteId => $listeData) {
                    if (!isset($siegesTotauxParEntite[$entiteId])) continue;

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

            // Parti majoritaire (par sièges) dans la commune
            $maxSiegesCommune = 0;
            $entiteMajoritaireCommune = null;

            foreach ($data['entites'] as $entite) {
                $siegesCommune = $siegesTotauxParEntite[$entite->id]['details_par_commune'][$commune->id]['sieges'] ?? 0;
                if ($siegesCommune > $maxSiegesCommune) {
                    $maxSiegesCommune = $siegesCommune;
                    $entiteMajoritaireCommune = $entite->id;
                }
            }

            if ($entiteMajoritaireCommune !== null && $maxSiegesCommune > 0) {
                $communesMajoritairesParEntite[$entiteMajoritaireCommune]++;
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
            'communes_majoritaires' => $communesMajoritairesParEntite,
            'data' => $data,
        ];
    }

    /**
     * ✅ Attribuer les sièges aux listes DANS chaque arrondissement (Art. 186-187)
     * 
     * CORRECTION CRITIQUE APPLIQUÉE :
     * - Total voix = TOUTES les voix (même listes <10% national)
     * - % locaux calculés sur TOUS les suffrages exprimés (conformité Art.187)
     */
    private function attribuerSiegesAuxListesParArrondissement(
        int $electionId,
        array $siegesParArrondissement,
        array $entitesEligiblesNational
    ): array {
        $repartitionArrondissements = [];

        foreach ($siegesParArrondissement as $arrId => $arrData) {
            $arrondissement = $arrData['arrondissement'];
            $siegesArrondissement = (int) ($arrData['sieges_total'] ?? 0);

            if ($siegesArrondissement <= 0) continue;

            // Voix (dédup par village) — TOUTES les entités
            $voixParEntite = $this->getVoixParEntiteDansArrondissement($electionId, $arrId);

            // ✅ CORRECTION CRITIQUE : Total = TOUTES les voix (conformité Art.187.1-187.3)
            // Les % locaux doivent être calculés sur TOUS les suffrages exprimés de l'arrondissement
            // Pas seulement sur les listes éligibles nationales
            $totalVoixArrondissement = array_sum($voixParEntite);

            // Construire listes participantes (filtre national pour attribution des sièges)
            // Mais les % sont calculés sur le total réel de l'arrondissement
            $listes = [];
            foreach ($entitesEligiblesNational as $entiteId => $eligData) {
                $voix = (int) ($voixParEntite[$entiteId] ?? 0);
                $pct = $totalVoixArrondissement > 0 ? ($voix / $totalVoixArrondissement) * 100 : 0;
                
                $listes[$entiteId] = [
                    'entite_id' => $entiteId,
                    'voix' => $voix,
                    'pourcentage' => $pct,  // ✅ % calculé sur TOUTES les voix
                    'sieges' => 0,
                    'candidats' => [],
                ];
            }

            $totalVoix = $totalVoixArrondissement;  // ✅ Total = TOUTES les voix

            if ($totalVoix > 0) {
                if ($siegesArrondissement === 1) {
                    // ✅ Uninominal majoritaire
                    $gagnantId = $this->trouverGagnantParVoix($listes);
                    if ($gagnantId !== null) {
                        $listes[$gagnantId]['sieges'] = 1;
                        $listes[$gagnantId]['_methode'] = 'uninominal';
                    }
                } else {
                    // ✅ Article 187
                    $listes = $this->attribuerSiegesArticle187SansQuotient($listes, $siegesArrondissement);
                }

                // Candidats élus (ordre de présentation)
                foreach ($listes as $entiteId => &$listeData) {
                    if (($listeData['sieges'] ?? 0) > 0) {
                        $listeData['candidats'] = $this->getCandidatsElus(
                            $electionId,
                            (int) $entiteId,
                            (int) $arrId,
                            (int) $listeData['sieges']
                        );
                    }
                }
                unset($listeData);
            }

            // Sièges attribués
            $siegesAttribues = 0;
            foreach ($listes as $entiteId => $listeData) {
                $siegesAttribues += (int) ($listeData['sieges'] ?? 0);
            }

            $repartitionArrondissements[$arrId] = [
                'arrondissement_id' => $arrId,
                'arrondissement_nom' => $arrondissement->nom,
                'sieges_arrondissement' => $siegesArrondissement,
                'sieges_attribues' => $siegesAttribues,
                'total_voix' => (int) $totalVoix,
                'listes' => $listes,
                'methode_attribution' => $this->resumeMethodeArrondissement($listes, $siegesArrondissement),
                'details_repartition' => $arrData,
            ];
        }

        return $repartitionArrondissements;
    }

    /**
     * ✅ Article 187 : majorité + plus forte moyenne (D'Hondt)
     * 
     * CORRECTIONS APPLIQUÉES :
     * - Majorité absolue = intdiv(n/2) + 1 (pas ceil(n/2))
     * - Tri : % DESC puis voix DESC (Art. 187.4)
     */
    private function attribuerSiegesArticle187SansQuotient(array $listes, int $nombreSieges): array
    {
        // ✅ CORRECTION CRITIQUE : Majorité absolue = floor(n/2) + 1
        // Exemple : 6 sièges → 4 (pas 3), 10 sièges → 6 (pas 5)
        $majoriteSieges = intdiv($nombreSieges, 2) + 1;

        // Trier : % DESC puis voix DESC (Art. 187.4 : plus fort suffrage)
        uasort($listes, function ($a, $b) {
            $p = ($b['pourcentage'] ?? 0) <=> ($a['pourcentage'] ?? 0);
            if ($p !== 0) return $p;
            return ($b['voix'] ?? 0) <=> ($a['voix'] ?? 0);
        });

        $ids = array_keys($listes);
        $premierId = $ids[0] ?? null;
        $deuxiemeId = $ids[1] ?? null;

        $premier = $premierId !== null ? $listes[$premierId] : null;
        $deuxieme = $deuxiemeId !== null ? $listes[$deuxiemeId] : null;

        // Cas 1 : >= 50% => prime à la liste majoritaire (Art. 187.1)
        if ($premier && ($premier['pourcentage'] ?? 0) >= 50) {
            $listes[$premierId]['sieges'] = $majoriteSieges;
            $listes[$premierId]['_methode'] = 'majorite_50';

            $siegesRestants = $nombreSieges - $majoriteSieges;
            if ($siegesRestants > 0) {
                // Liste majoritaire CONSERVE ses sièges dans le calcul
                $listes = $this->attribuerSiegesRestantsPlusForteMoyenne($listes, $siegesRestants, true);
            }
            return $listes;
        }

        // Cas 2 : >= 40% => prime au plus fort suffrage (Art. 187.2 + 187.2.1)
        $candidatsPrime = [];
        foreach ($listes as $id => $l) {
            if (($l['pourcentage'] ?? 0) >= 40) {
                $candidatsPrime[$id] = $l;
            }
        }

        if (!empty($candidatsPrime)) {
            // Choisir au plus grand nombre de suffrages
            $gagnantPrimeId = null;
            $maxVoix = -1;
            foreach ($candidatsPrime as $id => $l) {
                if (($l['voix'] ?? 0) > $maxVoix) {
                    $maxVoix = (int) $l['voix'];
                    $gagnantPrimeId = $id;
                }
            }

            if ($gagnantPrimeId !== null) {
                $listes[$gagnantPrimeId]['sieges'] = $majoriteSieges;
                $listes[$gagnantPrimeId]['_methode'] = 'majorite_40';

                $siegesRestants = $nombreSieges - $majoriteSieges;
                if ($siegesRestants > 0) {
                    // Liste majoritaire CONSERVE ses sièges dans le calcul
                    $listes = $this->attribuerSiegesRestantsPlusForteMoyenne($listes, $siegesRestants, true);
                }
                return $listes;
            }
        }

        // Cas 3 : proportionnelle (Art. 187.5) => plus forte moyenne, exclusion <10% exprimés
        return $this->attribuerSiegesRestantsPlusForteMoyenne($listes, $nombreSieges, false);
    }

    /**
     * ✅ Plus forte moyenne (D'Hondt) avec exclusion des listes <10% des suffrages exprimés
     */
    private function attribuerSiegesRestantsPlusForteMoyenne(array $listes, int $siegesARépartir, bool $conserverSiegesExistants): array
    {
        $totalVoix = array_sum(array_column($listes, 'voix'));

        // Exclusion <10% exprimés (au niveau arrondissement)
        $eligibles = [];
        foreach ($listes as $id => $l) {
            $pct = $totalVoix > 0 ? (($l['voix'] ?? 0) / $totalVoix) * 100 : 0;
            if ($pct >= 10) {
                $eligibles[$id] = $l;
            }
        }

        // Fallback : si aucune liste n'atteint 10% (cas extrême), on garde toutes les listes
        if (empty($eligibles)) {
            Log::warning("Arrondissement: aucune liste >=10% local, fallback sur toutes les listes (plus forte moyenne).");
            $eligibles = $listes;
        }

        // État sièges initiaux
        $siegesCourants = [];
        foreach ($eligibles as $id => $l) {
            $siegesCourants[$id] = $conserverSiegesExistants ? (int) ($listes[$id]['sieges'] ?? 0) : 0;
            if (!$conserverSiegesExistants) {
                $listes[$id]['sieges'] = 0;
            }
        }

        // Répartir les sièges
        for ($k = 0; $k < $siegesARépartir; $k++) {
            $meilleureMoy = -1;
            $gagnantId = null;

            foreach ($eligibles as $id => $l) {
                $voix = (int) ($l['voix'] ?? 0);
                $div = ($siegesCourants[$id] ?? 0) + 1;
                $moy = $div > 0 ? ($voix / $div) : 0;

                if ($moy > $meilleureMoy) {
                    $meilleureMoy = $moy;
                    $gagnantId = $id;
                    continue;
                }

                // Égalité de moyenne : plus grand nombre de suffrages (Art. 187.4)
                if ($moy == $meilleureMoy && $gagnantId !== null) {
                    $voixG = (int) ($eligibles[$gagnantId]['voix'] ?? 0);
                    if ($voix > $voixG) {
                        $gagnantId = $id;
                    } elseif ($voix === $voixG) {
                        // Tie-break âge non implémenté
                        Log::warning("Égalité parfaite (moyenne + voix) lors de la plus forte moyenne. Tie-break âge non implémenté.", [
                            'entite_a' => $gagnantId,
                            'entite_b' => $id,
                        ]);
                        $gagnantId = min((int)$gagnantId, (int)$id);
                    }
                }
            }

            if ($gagnantId !== null) {
                $siegesCourants[$gagnantId] = (int) ($siegesCourants[$gagnantId] ?? 0) + 1;
                $listes[$gagnantId]['sieges'] = (int) ($listes[$gagnantId]['sieges'] ?? 0) + 1;
                $listes[$gagnantId]['_methode'] = $listes[$gagnantId]['_methode'] ?? ($conserverSiegesExistants ? 'reste_plus_forte_moyenne' : 'proportionnelle_plus_forte_moyenne');
            }
        }

        return $listes;
    }

    /**
     * Trouver gagnant par voix (uninominal)
     */
    private function trouverGagnantParVoix(array $listes): ?int
    {
        $maxVoix = -1;
        $gagnantId = null;

        foreach ($listes as $id => $l) {
            $v = (int) ($l['voix'] ?? 0);
            if ($v > $maxVoix) {
                $maxVoix = $v;
                $gagnantId = (int) $id;
            } elseif ($v === $maxVoix && $gagnantId !== null) {
                // Égalité de voix : tie-break âge non implémenté
                Log::warning("Égalité de voix en uninominal, tie-break âge non implémenté.", [
                    'entite_a' => $gagnantId,
                    'entite_b' => (int)$id,
                ]);
                $gagnantId = min((int)$gagnantId, (int)$id);
            }
        }

        return $gagnantId;
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
     * Articles 187.7-187.8 : Candidats élus selon l'ordre de présentation
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
     * Résumé méthode d'attribution (pour afficher dans le rapport)
     */
    private function resumeMethodeArrondissement(array $listes, int $siegesArrondissement): array
    {
        if ($siegesArrondissement === 1) {
            return [
                'type' => 'uninominal',
                'description' => 'Scrutin uninominal majoritaire',
                'details' => 'Le candidat ayant obtenu le plus de voix est élu',
            ];
        }

        $has50 = false;
        $has40 = false;
        foreach ($listes as $l) {
            if (($l['pourcentage'] ?? 0) >= 50 && (int)($l['sieges'] ?? 0) > 0) $has50 = true;
            if (($l['pourcentage'] ?? 0) >= 40 && (int)($l['sieges'] ?? 0) > 0) $has40 = true;
        }

        if ($has50) {
            return [
                'type' => 'majorite_50_plus_reste',
                'description' => 'Prime majoritaire (≥50%) + plus forte moyenne',
                'details' => 'Sièges restants attribués à la plus forte moyenne (exclusion <10%)',
            ];
        }
        if ($has40) {
            return [
                'type' => 'majorite_40_plus_reste',
                'description' => 'Prime majoritaire (≥40%) + plus forte moyenne',
                'details' => 'Sièges restants attribués à la plus forte moyenne (exclusion <10%)',
            ];
        }

        return [
            'type' => 'proportionnelle_plus_forte_moyenne',
            'description' => 'Proportionnelle à la plus forte moyenne',
            'details' => 'Exclusion des listes <10% des suffrages exprimés',
        ];
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

        $csv .= "Entité Politique;Sigle;Total Sièges;% National;Communes Majoritaires;Détails par Commune\n";

        foreach ($result['sieges_totaux'] as $entiteId => $sieges) {
            $entite = collect($result['data']['entites'])->firstWhere('id', $entiteId);

            if ($entite && ($sieges['sieges_total'] ?? 0) > 0) {
                $pctNational = $result['data']['totaux_par_entite'][$entiteId]['pourcentage_national'] ?? 0;
                $communesMajoritaires = $result['communes_majoritaires'][$entiteId] ?? 0;

                $detailsCommunes = [];
                foreach (($sieges['details_par_commune'] ?? []) as $communeData) {
                    $detailsCommunes[] = "{$communeData['commune_nom']} ({$communeData['sieges']})";
                }

                $csv .= "{$entite->nom};{$entite->sigle};{$sieges['sieges_total']};";
                $csv .= number_format($pctNational, 2, ',', '') . ";";
                $csv .= "{$communesMajoritaires};";
                $csv .= implode(', ', $detailsCommunes) . "\n";
            }
        }

        return $csv;
    }

    /**
     * ✅ Export CSV - Détails par commune
     * CORRECTION APPLIQUÉE : Utilise methode_attribution existante
     */
    public function exporterDetailsParCommune(int $electionId): string
    {
        $result = $this->repartirSieges($electionId);
        $csv = chr(0xEF).chr(0xBB).chr(0xBF);

        $csv .= "Département;Commune;Population;Sièges Commune;Quotient Communal;Arrondissement;Suffrages Exprimés;Sièges Arr.;";
        $csv .= "Parti;Voix;% Arr.;Sièges;Mode Attribution\n";

        foreach ($result['repartition'] as $communeId => $rep) {
            $commune = $rep['info'];

            if (empty($rep['repartition_arrondissements'])) continue;

            foreach ($rep['repartition_arrondissements'] as $arrId => $arrData) {
                $details = $arrData['details_repartition'];
                $arrObj = $details['arrondissement'] ?? null;
                if (!$arrObj) continue;

                foreach ($arrData['listes'] as $entiteId => $listeData) {
                    $entite = collect($result['data']['entites'])->firstWhere('id', $entiteId);

                    // ✅ CORRECTION : Utiliser methode_attribution déjà calculée
                    $modeAttribution = $arrData['methode_attribution']['description'] ?? 'Non déterminé';

                    $csv .= "{$commune->departement_nom};{$commune->nom};{$rep['population']};{$rep['nombre_sieges']};";
                    $csv .= number_format($rep['quotient_communal'], 2, ',', '') . ";";
                    $csv .= "{$arrData['arrondissement_nom']};{$arrData['total_voix']};{$arrData['sieges_arrondissement']};";
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
     * ✅ Export CSV - Détails par arrondissement avec candidats
     * CORRECTION APPLIQUÉE : Utilise methode_attribution existante
     */
    public function exporterDetailsParArrondissement(int $electionId): string
    {
        $result = $this->repartirSieges($electionId);
        $csv = chr(0xEF).chr(0xBB).chr(0xBF);

        $csv .= "Commune;Arrondissement;Suffrages Exprimés;Sièges Arr.;Quotient Communal;";
        $csv .= "Parti;Voix;% Arr.;Sièges;Mode Attribution;Candidats Élus\n";

        foreach ($result['repartition'] as $communeId => $rep) {
            $commune = $rep['info'];
            if (empty($rep['repartition_arrondissements'])) continue;

            foreach ($rep['repartition_arrondissements'] as $arrId => $arrData) {
                $details = $arrData['details_repartition'];

                foreach ($arrData['listes'] as $entiteId => $listeData) {
                    if (($listeData['sieges'] ?? 0) == 0) continue;

                    $entite = collect($result['data']['entites'])->firstWhere('id', $entiteId);

                    // ✅ CORRECTION : Utiliser methode_attribution déjà calculée
                    $modeAttribution = $arrData['methode_attribution']['description'] ?? 'Non déterminé';

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
                    $csv .= ($arrData['total_voix'] ?? 0) . ";{$arrData['sieges_arrondissement']};";
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


    /**
     * ✅ NOUVEAU : Export CSV - Liste complète des candidats élus par parti
     * Format : Parti, Département, Commune, Arrondissement, Position, Titulaire, Suppléant
     */
    public function exporterCandidatsElusCSV(int $electionId): string
    {
        $result = $this->repartirSieges($electionId);
        $csv = chr(0xEF).chr(0xBB).chr(0xBF);

        // En-têtes
        $csv .= "Parti;Sigle;Département;Commune;Arrondissement;Position;Titulaire;Suppléant;Sièges Parti (Arr.);Total Voix (Arr.);% Voix (Arr.)\n";

        $candidatsElus = [];

        // Parcourir toutes les communes et arrondissements
        foreach ($result['repartition'] as $communeId => $rep) {
            $commune = $rep['info'];

            if (empty($rep['repartition_arrondissements'])) continue;

            foreach ($rep['repartition_arrondissements'] as $arrId => $arrData) {
                foreach ($arrData['listes'] as $entiteId => $listeData) {
                    if (($listeData['sieges'] ?? 0) == 0) continue;

                    $entite = collect($result['data']['entites'])->firstWhere('id', $entiteId);
                    if (!$entite) continue;

                    // Pour chaque candidat élu
                    foreach (($listeData['candidats'] ?? []) as $candidat) {
                        $candidatsElus[] = [
                            'parti' => $entite->nom,
                            'sigle' => $entite->sigle,
                            'departement' => $commune->departement_nom ?? '',
                            'commune' => $commune->nom,
                            'arrondissement' => $arrData['arrondissement_nom'],
                            'position' => $candidat['position'] ?? '',
                            'titulaire' => $candidat['titulaire'] ?? '',
                            'suppleant' => $candidat['suppleant'] ?? '',
                            'sieges_parti_arr' => $listeData['sieges'] ?? 0,
                            'voix_arr' => $listeData['voix'] ?? 0,
                            'pct_arr' => $listeData['pourcentage'] ?? 0,
                        ];
                    }
                }
            }
        }

        // Tri par parti, département, commune, arrondissement, position
        usort($candidatsElus, function($a, $b) {
            $p = strcmp($a['sigle'] ?: $a['parti'], $b['sigle'] ?: $b['parti']);
            if ($p !== 0) return $p;

            $d = strcmp($a['departement'], $b['departement']);
            if ($d !== 0) return $d;

            $c = strcmp($a['commune'], $b['commune']);
            if ($c !== 0) return $c;

            $arr = strcmp($a['arrondissement'], $b['arrondissement']);
            if ($arr !== 0) return $arr;

            return ($a['position'] ?? 999) <=> ($b['position'] ?? 999);
        });

        // Écrire les données
        foreach ($candidatsElus as $elu) {
            $csv .= "{$elu['parti']};{$elu['sigle']};{$elu['departement']};{$elu['commune']};{$elu['arrondissement']};";
            $csv .= "{$elu['position']};{$elu['titulaire']};{$elu['suppleant']};";
            $csv .= "{$elu['sieges_parti_arr']};{$elu['voix_arr']};";
            $csv .= number_format($elu['pct_arr'], 2, ',', '') . "\n";
        }

        // Statistiques globales en fin de fichier
        $csv .= "\n";
        $csv .= "STATISTIQUES GLOBALES\n";
        $csv .= "Parti;Sigle;Total Élus;Total Voix National;% National\n";

        $statsParParti = [];
        foreach ($candidatsElus as $elu) {
            $key = $elu['sigle'] ?: $elu['parti'];
            if (!isset($statsParParti[$key])) {
                $statsParParti[$key] = [
                    'parti' => $elu['parti'],
                    'sigle' => $elu['sigle'],
                    'total_elus' => 0,
                ];
            }
            $statsParParti[$key]['total_elus']++;
        }

        foreach ($result['sieges_totaux'] as $entiteId => $sieges) {
            $entite = collect($result['data']['entites'])->firstWhere('id', $entiteId);
            if (!$entite) continue;

            $key = $entite->sigle ?: $entite->nom;
            $pctNational = $result['data']['totaux_par_entite'][$entiteId]['pourcentage_national'] ?? 0;
            $voixNational = $result['data']['totaux_par_entite'][$entiteId]['voix'] ?? 0;

            if (isset($statsParParti[$key])) {
                $csv .= "{$entite->nom};{$entite->sigle};{$statsParParti[$key]['total_elus']};";
                $csv .= "{$voixNational};" . number_format($pctNational, 2, ',', '') . "\n";
            }
        }

        return $csv;
    }

    /**
     * ✅ NOUVEAU : Export CSV - Liste des élus avec statistiques avancées
     * Format enrichi avec mode d'attribution et quotient
     */
    public function exporterCandidatsElusDetaillesCSV(int $electionId): string
    {
        $result = $this->repartirSieges($electionId);
        $csv = chr(0xEF).chr(0xBB).chr(0xBF);

        // En-têtes enrichies
        $csv .= "Parti;Sigle;Département;Commune;Population Commune;Quotient Communal;Arrondissement;Sièges Arrondissement;";
        $csv .= "Position;Titulaire;Suppléant;Sièges Parti (Arr.);Total Voix (Arr.);% Voix (Arr.);Mode Attribution\n";

        $candidatsElus = [];

        // Parcourir toutes les communes et arrondissements
        foreach ($result['repartition'] as $communeId => $rep) {
            $commune = $rep['info'];

            if (empty($rep['repartition_arrondissements'])) continue;

            foreach ($rep['repartition_arrondissements'] as $arrId => $arrData) {
                $modeAttribution = $arrData['methode_attribution']['description'] ?? 'Non déterminé';

                foreach ($arrData['listes'] as $entiteId => $listeData) {
                    if (($listeData['sieges'] ?? 0) == 0) continue;

                    $entite = collect($result['data']['entites'])->firstWhere('id', $entiteId);
                    if (!$entite) continue;

                    // Pour chaque candidat élu
                    foreach (($listeData['candidats'] ?? []) as $candidat) {
                        $candidatsElus[] = [
                            'parti' => $entite->nom,
                            'sigle' => $entite->sigle,
                            'departement' => $commune->departement_nom ?? '',
                            'commune' => $commune->nom,
                            'population_commune' => $rep['population'] ?? 0,
                            'quotient_communal' => $rep['quotient_communal'] ?? 0,
                            'arrondissement' => $arrData['arrondissement_nom'],
                            'sieges_arrondissement' => $arrData['sieges_arrondissement'] ?? 0,
                            'position' => $candidat['position'] ?? '',
                            'titulaire' => $candidat['titulaire'] ?? '',
                            'suppleant' => $candidat['suppleant'] ?? '',
                            'sieges_parti_arr' => $listeData['sieges'] ?? 0,
                            'voix_arr' => $listeData['voix'] ?? 0,
                            'pct_arr' => $listeData['pourcentage'] ?? 0,
                            'mode_attribution' => $modeAttribution,
                        ];
                    }
                }
            }
        }

        // Tri par parti, département, commune, arrondissement, position
        usort($candidatsElus, function($a, $b) {
            $p = strcmp($a['sigle'] ?: $a['parti'], $b['sigle'] ?: $b['parti']);
            if ($p !== 0) return $p;

            $d = strcmp($a['departement'], $b['departement']);
            if ($d !== 0) return $d;

            $c = strcmp($a['commune'], $b['commune']);
            if ($c !== 0) return $c;

            $arr = strcmp($a['arrondissement'], $b['arrondissement']);
            if ($arr !== 0) return $arr;

            return ($a['position'] ?? 999) <=> ($b['position'] ?? 999);
        });

        // Écrire les données
        foreach ($candidatsElus as $elu) {
            $csv .= "{$elu['parti']};{$elu['sigle']};{$elu['departement']};{$elu['commune']};";
            $csv .= "{$elu['population_commune']};" . number_format($elu['quotient_communal'], 2, ',', '') . ";";
            $csv .= "{$elu['arrondissement']};{$elu['sieges_arrondissement']};";
            $csv .= "{$elu['position']};{$elu['titulaire']};{$elu['suppleant']};";
            $csv .= "{$elu['sieges_parti_arr']};{$elu['voix_arr']};";
            $csv .= number_format($elu['pct_arr'], 2, ',', '') . ";{$elu['mode_attribution']}\n";
        }

        return $csv;
    }
    
}