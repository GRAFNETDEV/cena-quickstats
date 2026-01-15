<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class ResultatsService
{
    private StatsService $statsService;

    public function __construct(StatsService $statsService)
    {
        $this->statsService = $statsService;
    }

    public function getResultatsParCirconscription(int $electionId): array
    {
        $circonscriptions = DB::table('circonscriptions_electorales')
            ->select('id', 'nom', 'numero', 'nombre_sieges_total', 'nombre_sieges_femmes')
            ->where('numero', '<=', 24)
            ->orderBy('numero')
            ->get();

        $entites = DB::table('candidatures as c')
            ->join('entites_politiques as ep', 'ep.id', '=', 'c.entite_politique_id')
            ->where('c.election_id', $electionId)
            ->where('c.statut', 'validee')
            ->select('ep.id', 'ep.nom', 'ep.sigle', DB::raw('MIN(c.numero_liste) as numero_liste'))
            ->groupBy('ep.id', 'ep.nom', 'ep.sigle')
            ->orderBy('numero_liste')
            ->get();

        $matrice = [];
        $totauxParEntite = [];
        $totauxParCirconscription = [];

        foreach ($entites as $entite) {
            $totauxParEntite[$entite->id] = [
                'voix' => 0,
                'pourcentage_moyen' => 0,
            ];
        }

        foreach ($circonscriptions as $circ) {
            $matrice[$circ->id] = [
                'info' => $circ,
                'resultats' => [],
                'total_voix' => 0,
            ];

            $voixCirc = $this->getVoixParEntiteDansCirconscription($electionId, $circ->id);

            $totalVoixCirc = array_sum($voixCirc);
            $matrice[$circ->id]['total_voix'] = $totalVoixCirc;
            $totauxParCirconscription[$circ->id] = $totalVoixCirc;

            foreach ($entites as $entite) {
                $voix = $voixCirc[$entite->id] ?? 0;
                $pourcentage = $totalVoixCirc > 0 ? ($voix / $totalVoixCirc) * 100 : 0;

                $matrice[$circ->id]['resultats'][$entite->id] = [
                    'voix' => $voix,
                    'pourcentage' => $pourcentage,
                ];

                $totauxParEntite[$entite->id]['voix'] += $voix;
            }
        }

        $totalVoixNational = array_sum($totauxParCirconscription);
        foreach ($entites as $entite) {
            $totauxParEntite[$entite->id]['pourcentage_moyen'] =
                $totalVoixNational > 0
                    ? ($totauxParEntite[$entite->id]['voix'] / $totalVoixNational) * 100
                    : 0;
        }

        return [
            'circonscriptions' => $circonscriptions,
            'entites' => $entites,
            'matrice' => $matrice,
            'totaux_par_entite' => $totauxParEntite,
            'total_voix_national' => $totalVoixNational,
        ];
    }

    private function getVoixParEntiteDansCirconscription(int $electionId, int $circonscriptionId): array
    {
        $derniersPV = DB::table('proces_verbaux as pv')
            ->join('arrondissements as a', 'a.id', '=', DB::raw('pv.niveau_id::int'))
            ->where('pv.niveau', 'arrondissement')
            ->whereIn('pv.statut', ['valide', 'publie'])
            ->where('pv.election_id', $electionId)
            ->where('a.circonscription_id', $circonscriptionId)
            ->select(
                'pv.id as proces_verbal_id',
                DB::raw('pv.niveau_id::int as pv_arrondissement_id'),
                'pv.created_at',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY pv.niveau_id ORDER BY pv.created_at DESC, pv.id DESC) as rn')
            )
            ->get()
            ->where('rn', 1)
            ->pluck('proces_verbal_id');

        if ($derniersPV->isEmpty()) return [];

        $dernieresLignes = DB::table('pv_lignes as pl')
            ->whereIn('pl.proces_verbal_id', $derniersPV)
            ->select(
                'pl.id as pv_ligne_id',
                'pl.village_quartier_id',
                'pl.created_at',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY pl.village_quartier_id ORDER BY pl.created_at DESC, pl.id DESC) as rn')
            )
            ->get()
            ->where('rn', 1)
            ->pluck('pv_ligne_id');

        if ($dernieresLignes->isEmpty()) return [];

        $results = DB::table('pv_ligne_resultats as plr')
            ->join('candidatures as ca', 'ca.id', '=', 'plr.candidature_id')
            ->whereIn('plr.pv_ligne_id', $dernieresLignes)
            ->select('ca.entite_politique_id', DB::raw('SUM(COALESCE(plr.nombre_voix, 0)) as total_voix'))
            ->groupBy('ca.entite_politique_id')
            ->get();

        $voix = [];
        foreach ($results as $result) {
            $voix[$result->entite_politique_id] = (int) $result->total_voix;
        }

        return $voix;
    }

    public function verifierEligibilite(int $electionId): array
    {
        $data = $this->getResultatsParCirconscription($electionId);
        $eligibilite = [];

        foreach ($data['entites'] as $entite) {
            $eligible = true;
            $details = [];
            $circonscriptionsNonQualifiees = [];
            $nbCirconscriptionsQualifiees = 0;

            foreach ($data['circonscriptions'] as $circ) {
                $pourcentage = $data['matrice'][$circ->id]['resultats'][$entite->id]['pourcentage'] ?? 0;

                $qualifie = $pourcentage >= 20;
                $details[$circ->id] = [
                    'pourcentage' => $pourcentage,
                    'qualifie' => $qualifie,
                    'color' => $this->getColorClass($pourcentage),
                ];

                if ($qualifie) {
                    $nbCirconscriptionsQualifiees++;
                } else {
                    $eligible = false;
                    $circonscriptionsNonQualifiees[] = $this->getCirconscriptionNomOrdinal($circ);
                }
            }

            $eligibilite[$entite->id] = [
                'entite' => $entite,
                'eligible' => $eligible,
                'pourcentage_national' => $data['totaux_par_entite'][$entite->id]['pourcentage_moyen'],
                'total_voix' => $data['totaux_par_entite'][$entite->id]['voix'],
                'details_par_circonscription' => $details,
                'circonscriptions_non_qualifiees' => $circonscriptionsNonQualifiees,
                'nb_circonscriptions_qualifiees' => $nbCirconscriptionsQualifiees,
            ];
        }

        return [
            'eligibilite' => $eligibilite,
            'data' => $data,
        ];
    }

    /**
     * ✅ ÉTAPES 2 & 3 : Répartir les sièges
     * Correction : si 1 seul éligible => il prend tout (ordinaires + femmes)
     */
    public function repartirSieges(int $electionId): array
    {
        $eligibiliteData = $this->verifierEligibilite($electionId);
        $eligibilite = $eligibiliteData['eligibilite'];
        $data = $eligibiliteData['data'];

        $entitesEligibles = array_filter($eligibilite, fn($e) => $e['eligible']);
        $nbEligibles = count($entitesEligibles);
        $seulEligibleId = ($nbEligibles === 1) ? array_key_first($entitesEligibles) : null;

        $repartition = [];
        $siegesTotauxParEntite = [];

        foreach ($data['entites'] as $entite) {
            $siegesTotauxParEntite[$entite->id] = [
                'sieges_ordinaires' => 0,
                'sieges_femmes' => 0,
                'total_sieges' => 0,
            ];
        }

        foreach ($data['circonscriptions'] as $circ) {
            $siegesOrdinaires = $circ->nombre_sieges_total - $circ->nombre_sieges_femmes;
            $totalVoixCirc = $data['matrice'][$circ->id]['total_voix'];

            if ($totalVoixCirc == 0 || $siegesOrdinaires == 0 || $nbEligibles === 0) {
                // nbEligibles === 0 : personne ne reçoit de sièges
                $repartition[$circ->id] = [
                    'info' => $circ,
                    'sieges_ordinaires' => [],
                    'siege_femme' => null,
                    'quotient_electoral' => 0,
                ];
                continue;
            }

            // ✅ ÉTAPE 2 : Sièges ordinaires
            $siegesAttribues = [];
            $quotientElectoral = $siegesOrdinaires > 0 ? ($totalVoixCirc / $siegesOrdinaires) : 0;

            if ($nbEligibles === 1) {
                // ✅ CAS SPÉCIAL : un seul éligible => il prend TOUS les sièges ordinaires
                $siegesAttribues[$seulEligibleId] = (int) $siegesOrdinaires;
                $siegesTotauxParEntite[$seulEligibleId]['sieges_ordinaires'] += (int) $siegesOrdinaires;
            } else {
                // ✅ CAS NORMAL : quotient + plus forts restes
                $restesVoix = [];

                foreach ($entitesEligibles as $entiteId => $eligData) {
                    $voix = $data['matrice'][$circ->id]['resultats'][$entiteId]['voix'] ?? 0;
                    $sieges = ($quotientElectoral > 0) ? floor($voix / $quotientElectoral) : 0;

                    $siegesAttribues[$entiteId] = (int) $sieges;
                    $restesVoix[$entiteId] = $voix - ($sieges * $quotientElectoral);
                }

                $siegesRestants = $siegesOrdinaires - array_sum($siegesAttribues);

                if ($siegesRestants > 0) {
                    arsort($restesVoix);
                    $compteur = 0;
                    foreach ($restesVoix as $entiteId => $reste) {
                        if ($compteur >= $siegesRestants) break;
                        $siegesAttribues[$entiteId]++;
                        $compteur++;
                    }
                }

                foreach ($siegesAttribues as $entiteId => $nbSieges) {
                    $siegesTotauxParEntite[$entiteId]['sieges_ordinaires'] += (int) $nbSieges;
                }
            }

            // ✅ ÉTAPE 3 : Siège(s) femme(s)
            $siegeFemme = null;
            if ($circ->nombre_sieges_femmes > 0) {
                if ($nbEligibles === 1) {
                    // ✅ CAS SPÉCIAL : un seul éligible => il prend TOUS les sièges femmes
                    $voixSeul = $data['matrice'][$circ->id]['resultats'][$seulEligibleId]['voix'] ?? 0;

                    $siegeFemme = [
                        'entite_id' => $seulEligibleId,
                        'entite_nom' => $eligibilite[$seulEligibleId]['entite']->nom,
                        'entite_sigle' => $eligibilite[$seulEligibleId]['entite']->sigle,
                        'voix' => $voixSeul,
                    ];

                    $siegesTotauxParEntite[$seulEligibleId]['sieges_femmes'] += (int) $circ->nombre_sieges_femmes;
                } else {
                    // ✅ CAS NORMAL : siège femme à l’éligible ayant le plus de voix
                    $maxVoix = -1;
                    $gagnantId = null;

                    foreach ($entitesEligibles as $entiteId => $eligData) {
                        $voix = $data['matrice'][$circ->id]['resultats'][$entiteId]['voix'] ?? 0;
                        if ($voix > $maxVoix) {
                            $maxVoix = $voix;
                            $gagnantId = $entiteId;
                        }
                    }

                    if ($gagnantId !== null) {
                        $siegeFemme = [
                            'entite_id' => $gagnantId,
                            'entite_nom' => $eligibilite[$gagnantId]['entite']->nom,
                            'entite_sigle' => $eligibilite[$gagnantId]['entite']->sigle,
                            'voix' => $maxVoix,
                        ];
                        $siegesTotauxParEntite[$gagnantId]['sieges_femmes'] += (int) $circ->nombre_sieges_femmes;
                    }
                }
            }

            $repartition[$circ->id] = [
                'info' => $circ,
                'sieges_ordinaires' => $siegesAttribues,
                'siege_femme' => $siegeFemme,
                'quotient_electoral' => $quotientElectoral,
            ];
        }

        foreach ($siegesTotauxParEntite as $entiteId => &$totaux) {
            $totaux['total_sieges'] = (int) $totaux['sieges_ordinaires'] + (int) $totaux['sieges_femmes'];
        }

        return [
            'eligibilite' => $eligibilite,
            'repartition' => $repartition,
            'sieges_totaux' => $siegesTotauxParEntite,
            'data' => $data,
        ];
    }

    private function getColorClass(float $pourcentage): string
    {
        if ($pourcentage >= 20) return 'green';
        elseif ($pourcentage >= 10) return 'yellow';
        else return 'red';
    }

    private function getCirconscriptionNomOrdinal($circ): string
    {
        return $circ->numero == 1 ? "1ère circonscription" : "{$circ->numero}ème circonscription";
    }

    // ... exporterResultatsCSV, exporterSiegesCSV, getResume inchangés ...

    public function exporterResultatsCSV(int $electionId): string
    {
        $data = $this->getResultatsParCirconscription($electionId);
        $csv = chr(0xEF).chr(0xBB).chr(0xBF);

        $csv .= "Circonscription";
        foreach ($data['entites'] as $entite) {
            $nom = $entite->sigle ?: $entite->nom;
            $csv .= ";{$nom} (Voix);{$nom} (%)";
        }
        $csv .= ";Total Voix\n";

        foreach ($data['circonscriptions'] as $circ) {
            $nomCirc = $this->getCirconscriptionNomOrdinal($circ);
            $csv .= $nomCirc;
            foreach ($data['entites'] as $entite) {
                $voix = $data['matrice'][$circ->id]['resultats'][$entite->id]['voix'];
                $pct = $data['matrice'][$circ->id]['resultats'][$entite->id]['pourcentage'];
                $csv .= ";{$voix};" . number_format($pct, 2, ',', '');
            }
            $csv .= ";" . $data['matrice'][$circ->id]['total_voix'] . "\n";
        }

        $csv .= "TOTAL NATIONAL";
        foreach ($data['entites'] as $entite) {
            $totalVoix = $data['totaux_par_entite'][$entite->id]['voix'];
            $pctMoyen = $data['totaux_par_entite'][$entite->id]['pourcentage_moyen'];
            $csv .= ";{$totalVoix};" . number_format($pctMoyen, 2, ',', '');
        }
        $csv .= ";" . $data['total_voix_national'] . "\n";

        return $csv;
    }

    public function exporterSiegesCSV(int $electionId): string
    {
        $result = $this->repartirSieges($electionId);
        $csv = chr(0xEF).chr(0xBB).chr(0xBF);

        $csv .= "Entité Politique;Sigle;Sièges Ordinaires;Sièges Femmes;Total Sièges;% National\n";

        foreach ($result['sieges_totaux'] as $entiteId => $sieges) {
            $entite = collect($result['data']['entites'])->firstWhere('id', $entiteId);
            if ($entite && $sieges['total_sieges'] > 0) {
                $pctNational = $result['data']['totaux_par_entite'][$entiteId]['pourcentage_moyen'];
                $csv .= "{$entite->nom};{$entite->sigle};{$sieges['sieges_ordinaires']};";
                $csv .= "{$sieges['sieges_femmes']};{$sieges['total_sieges']};";
                $csv .= number_format($pctNational, 2, ',', '') . "\n";
            }
        }

        return $csv;
    }

    public function getResume(int $electionId): array
    {
        $compilation = $this->repartirSieges($electionId);

        $nbEntitesEligibles = count(array_filter($compilation['eligibilite'], fn($e) => $e['eligible']));
        $nbEntitesTotal = count($compilation['data']['entites']);

        $totalSieges = array_sum(array_column($compilation['sieges_totaux'], 'total_sieges'));
        $totalSiegesOrdinaires = array_sum(array_column($compilation['sieges_totaux'], 'sieges_ordinaires'));
        $totalSiegesFemmes = array_sum(array_column($compilation['sieges_totaux'], 'sieges_femmes'));

        return [
            'nb_entites_total' => $nbEntitesTotal,
            'nb_entites_eligibles' => $nbEntitesEligibles,
            'nb_circonscriptions' => count($compilation['data']['circonscriptions']),
            'total_voix_national' => $compilation['data']['total_voix_national'],
            'total_sieges' => $totalSieges,
            'total_sieges_ordinaires' => $totalSiegesOrdinaires,
            'total_sieges_femmes' => $totalSiegesFemmes,
        ];
    }
}
