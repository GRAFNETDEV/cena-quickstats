<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ResultatsPresidentielleService
{
    /**
     * Données présidentielles par département + totaux nationaux.
     */
    public function getResultatsParDepartement(int $electionId): array
    {
        $departements = DB::table('departements')
            ->select('id', 'nom', 'code')
            ->orderBy('nom')
            ->get();

        $communes = DB::table('communes as c')
            ->join('departements as d', 'd.id', '=', 'c.departement_id')
            ->select('c.id', 'c.nom', 'c.code', 'c.departement_id', 'd.nom as departement_nom')
            ->orderBy('d.nom')
            ->orderBy('c.nom')
            ->get();

        $entites = $this->getEntitesPresidentielles($electionId);
        $voixParDepartementEtEntite = $this->getVoixParDepartementEtEntite($electionId);
        $voixParCommuneEtEntite = $this->getVoixParCommuneEtEntite($electionId);

        $matriceDepartements = [];
        $matriceCommunes = [];
        $totauxParEntite = [];
        $totauxParDepartement = [];

        foreach ($entites as $entite) {
            $totauxParEntite[$entite->id] = [
                'voix' => 0,
                'pourcentage_national' => 0.0,
            ];
        }

        foreach ($departements as $departement) {
            $resultatsDepartement = $voixParDepartementEtEntite[$departement->id] ?? [];
            $totalVoixDepartement = array_sum($resultatsDepartement);

            $matriceDepartements[$departement->id] = [
                'info' => $departement,
                'resultats' => [],
                'total_voix' => $totalVoixDepartement,
            ];

            $totauxParDepartement[$departement->id] = $totalVoixDepartement;

            foreach ($entites as $entite) {
                $voix = (int) ($resultatsDepartement[$entite->id] ?? 0);
                $pourcentage = $totalVoixDepartement > 0
                    ? ($voix / $totalVoixDepartement) * 100
                    : 0.0;

                $matriceDepartements[$departement->id]['resultats'][$entite->id] = [
                    'voix' => $voix,
                    'pourcentage' => $pourcentage,
                ];

                $totauxParEntite[$entite->id]['voix'] += $voix;
            }
        }

        foreach ($communes as $commune) {
            $resultatsCommune = $voixParCommuneEtEntite[$commune->id] ?? [];
            $totalVoixCommune = array_sum($resultatsCommune);

            $matriceCommunes[$commune->id] = [
                'info' => $commune,
                'resultats' => [],
                'total_voix' => $totalVoixCommune,
            ];

            foreach ($entites as $entite) {
                $voix = (int) ($resultatsCommune[$entite->id] ?? 0);
                $pourcentage = $totalVoixCommune > 0
                    ? ($voix / $totalVoixCommune) * 100
                    : 0.0;

                $matriceCommunes[$commune->id]['resultats'][$entite->id] = [
                    'voix' => $voix,
                    'pourcentage' => $pourcentage,
                ];
            }
        }

        $totalVoixNational = array_sum($totauxParDepartement);
        foreach ($entites as $entite) {
            $totauxParEntite[$entite->id]['pourcentage_national'] = $totalVoixNational > 0
                ? ($totauxParEntite[$entite->id]['voix'] / $totalVoixNational) * 100
                : 0.0;
        }

        $regles = $this->getReglesElection($electionId);

        return [
            'departements' => $departements,
            'communes' => $communes,
            'entites' => $entites,
            'matrice' => $matriceDepartements,
            'matrice_departements' => $matriceDepartements,
            'matrice_communes' => $matriceCommunes,
            'totaux_par_entite' => $totauxParEntite,
            'total_voix_national' => $totalVoixNational,
            'classement_national' => $this->buildClassementNational($entites, $totauxParEntite),
            'regles' => $regles,
        ];
    }

    /**
     * Évaluation du résultat présidentiel (1er tour ou qualification 2e tour).
     */
    public function compilerResultats(int $electionId): array
    {
        $data = $this->getResultatsParDepartement($electionId);
        $classement = $data['classement_national'];
        $totalVoix = (int) ($data['total_voix_national'] ?? 0);
        $regles = $data['regles'] ?? [];

        $majoriteAbsolueVoix = $totalVoix > 0 ? intdiv($totalVoix, 2) + 1 : 0;
        $premier = $classement[0] ?? null;

        $eluPremierTour = $premier !== null
            && $majoriteAbsolueVoix > 0
            && ((int) $premier['voix']) >= $majoriteAbsolueVoix;

        $nbCandidatsSecondTour = (int) ($regles['nombre_candidats_second_tour'] ?? 2);
        if ($nbCandidatsSecondTour < 2) {
            $nbCandidatsSecondTour = 2;
        }

        $qualifiesSecondTour = $eluPremierTour
            ? []
            : array_slice($classement, 0, $nbCandidatsSecondTour);

        $qualifiesIds = array_column($qualifiesSecondTour, 'entite_id');
        foreach ($classement as &$ligne) {
            if ($eluPremierTour && $premier && $ligne['entite_id'] === $premier['entite_id']) {
                $ligne['statut'] = 'elu_premier_tour';
            } elseif (!$eluPremierTour && in_array($ligne['entite_id'], $qualifiesIds, true)) {
                $ligne['statut'] = 'qualifie_second_tour';
            } else {
                $ligne['statut'] = 'non_qualifie';
            }
        }
        unset($ligne);

        $egaliteLimiteSecondTour = false;
        $entitesExAequo = [];
        if (!$eluPremierTour && count($classement) > $nbCandidatsSecondTour) {
            $voixLimite = (int) ($classement[$nbCandidatsSecondTour - 1]['voix'] ?? -1);
            $strictementSuperieur = array_filter($classement, static function (array $row) use ($voixLimite): bool {
                return ((int) $row['voix']) > $voixLimite;
            });
            $exAequo = array_filter($classement, static function (array $row) use ($voixLimite): bool {
                return ((int) $row['voix']) === $voixLimite;
            });

            if (count($strictementSuperieur) < $nbCandidatsSecondTour && count($exAequo) > 1) {
                $egaliteLimiteSecondTour = true;
                $entitesExAequo = array_values($exAequo);
            }
        }

        return [
            'data' => $data,
            'classement_national' => $classement,
            'premier' => $premier,
            'elu_premier_tour' => $eluPremierTour,
            'majorite_absolue_voix' => $majoriteAbsolueVoix,
            'seuil_premier_tour_pourcent' => (float) ($regles['seuil_premier_tour_pourcent'] ?? 50),
            'qualifies_second_tour' => $qualifiesSecondTour,
            'second_tour_requis' => !$eluPremierTour && count($classement) > 1,
            'nb_candidats_second_tour' => $nbCandidatsSecondTour,
            'egalite_limite_second_tour' => $egaliteLimiteSecondTour,
            'entites_ex_aequo_limite' => $entitesExAequo,
            'decision_label' => $eluPremierTour
                ? 'Élection acquise au premier tour'
                : 'Second tour requis',
        ];
    }

    /**
     * Format de réponse proche des autres services pour compatibilité AJAX.
     */
    public function verifierEligibilite(int $electionId): array
    {
        $compilation = $this->compilerResultats($electionId);
        $eligibilite = [];

        foreach ($compilation['classement_national'] as $row) {
            $eligibilite[$row['entite_id']] = [
                'entite' => (object) [
                    'id' => $row['entite_id'],
                    'nom' => $row['nom'],
                    'sigle' => $row['sigle'],
                    'ticket' => $row['ticket'],
                    'titulaire' => $row['titulaire'],
                    'colistier' => $row['colistier'],
                ],
                'eligible' => in_array($row['statut'], ['elu_premier_tour', 'qualifie_second_tour'], true),
                'statut' => $row['statut'],
                'rang' => $row['rang'],
                'pourcentage_national' => $row['pourcentage'],
                'total_voix' => $row['voix'],
            ];
        }

        return [
            'eligibilite' => $eligibilite,
            'data' => $compilation['data'],
            'compilation' => $compilation,
        ];
    }

    public function getResume(int $electionId): array
    {
        $compilation = $this->compilerResultats($electionId);

        return [
            'nb_duos' => count($compilation['classement_national']),
            'total_voix_national' => $compilation['data']['total_voix_national'] ?? 0,
            'majorite_absolue_voix' => $compilation['majorite_absolue_voix'],
            'second_tour_requis' => $compilation['second_tour_requis'],
            'duo_en_tete' => $compilation['premier']['ticket'] ?? null,
            'duo_elu_premier_tour' => $compilation['elu_premier_tour']
                ? ($compilation['premier']['ticket'] ?? null)
                : null,
        ];
    }

    public function exporterMatriceCSV(int $electionId): string
    {
        $data = $this->getResultatsParDepartement($electionId);
        $csv = chr(0xEF) . chr(0xBB) . chr(0xBF);

        $csv .= "Département;Commune";
        foreach ($data['entites'] as $entite) {
            $nom = $entite->sigle ?: $entite->nom;
            $csv .= ";{$nom} (Voix);{$nom} (%)";
        }
        $csv .= ";Total Voix\n";

        foreach ($data['communes'] as $commune) {
            $csv .= $commune->departement_nom . ';' . $commune->nom;
            foreach ($data['entites'] as $entite) {
                $resultat = $data['matrice_communes'][$commune->id]['resultats'][$entite->id] ?? [
                    'voix' => 0,
                    'pourcentage' => 0,
                ];
                $csv .= ';' . $resultat['voix'] . ';' . number_format($resultat['pourcentage'], 2, ',', '');
            }
            $csv .= ';' . ($data['matrice_communes'][$commune->id]['total_voix'] ?? 0) . "\n";
        }

        $csv .= "TOTAL NATIONAL;";
        foreach ($data['entites'] as $entite) {
            $totaux = $data['totaux_par_entite'][$entite->id] ?? ['voix' => 0, 'pourcentage_national' => 0];
            $csv .= ';' . $totaux['voix'] . ';' . number_format($totaux['pourcentage_national'], 2, ',', '');
        }
        $csv .= ';' . ($data['total_voix_national'] ?? 0) . "\n";

        return $csv;
    }

    public function exporterDetailsCSV(int $electionId): string
    {
        $compilation = $this->compilerResultats($electionId);
        $csv = chr(0xEF) . chr(0xBB) . chr(0xBF);

        $csv .= "Indicateur;Valeur\n";
        $csv .= "Décision;" . $compilation['decision_label'] . "\n";
        $csv .= "Total voix exprimées;" . ($compilation['data']['total_voix_national'] ?? 0) . "\n";
        $csv .= "Majorité absolue (voix);" . ($compilation['majorite_absolue_voix'] ?? 0) . "\n";
        $csv .= "Seuil premier tour (%);" . number_format((float) ($compilation['seuil_premier_tour_pourcent'] ?? 50), 2, ',', '') . "\n";
        $csv .= "Second tour requis;" . ($compilation['second_tour_requis'] ? 'oui' : 'non') . "\n";
        $csv .= "\n";

        $csv .= "Rang;Duo;Titulaire;Colistier;Sigle;Voix;Pourcentage;Statut\n";
        foreach ($compilation['classement_national'] as $ligne) {
            $csv .= $ligne['rang'] . ';';
            $csv .= $ligne['ticket'] . ';';
            $csv .= $ligne['titulaire'] . ';';
            $csv .= $ligne['colistier'] . ';';
            $csv .= $ligne['sigle'] . ';';
            $csv .= $ligne['voix'] . ';';
            $csv .= number_format((float) $ligne['pourcentage'], 2, ',', '') . ';';
            $csv .= $this->labelStatut($ligne['statut']) . "\n";
        }

        return $csv;
    }

    public function exporterClassementCSV(int $electionId): string
    {
        return $this->exporterDetailsCSV($electionId);
    }

    private function getEntitesPresidentielles(int $electionId)
    {
        $entites = DB::table('candidatures as c')
            ->join('entites_politiques as ep', 'ep.id', '=', 'c.entite_politique_id')
            ->where('c.election_id', $electionId)
            ->where('c.statut', 'validee')
            ->select(
                'ep.id',
                'ep.nom',
                'ep.sigle',
                'ep.couleur',
                'ep.data as entite_data',
                'c.numero_liste',
                'c.tete_liste',
                'c.data as candidature_data'
            )
            ->orderBy('c.numero_liste')
            ->orderBy('ep.id')
            ->get();

        foreach ($entites as $entite) {
            $candidatureData = $this->decodeJsonField($entite->candidature_data ?? null);
            $entiteData = $this->decodeJsonField($entite->entite_data ?? null);

            $titulaire = $candidatureData['duo']['titulaire']
                ?? $entiteData['candidat']
                ?? $entite->tete_liste
                ?? $entite->nom;
            $colistier = $candidatureData['duo']['colistier']
                ?? $entiteData['colistier']
                ?? '';

            $ticket = trim($titulaire . ' / ' . $colistier, ' /');
            if ($ticket === '') {
                $ticket = $entite->sigle ?: $entite->nom;
            }

            $entite->titulaire = $titulaire;
            $entite->colistier = $colistier;
            $entite->ticket = $ticket;
        }

        return $entites;
    }

    private function getVoixParDepartementEtEntite(int $electionId): array
    {
        $lignesRetenues = $this->lignesRetenuesSousRequete($electionId);

        $rows = DB::query()
            ->fromSub($lignesRetenues, 'lr')
            ->join('pv_ligne_resultats as plr', 'plr.pv_ligne_id', '=', 'lr.pv_ligne_id')
            ->join('candidatures as c', function ($join) use ($electionId) {
                $join->on('c.id', '=', 'plr.candidature_id')
                    ->where('c.election_id', '=', $electionId)
                    ->where('c.statut', '=', 'validee');
            })
            ->select(
                'lr.departement_id',
                'c.entite_politique_id',
                DB::raw('SUM(COALESCE(plr.nombre_voix, 0)) as total_voix')
            )
            ->groupBy('lr.departement_id', 'c.entite_politique_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $departementId = (int) $row->departement_id;
            $entiteId = (int) $row->entite_politique_id;
            $result[$departementId][$entiteId] = (int) $row->total_voix;
        }

        return $result;
    }

    private function getVoixParCommuneEtEntite(int $electionId): array
    {
        $lignesRetenues = $this->lignesRetenuesSousRequete($electionId);

        $rows = DB::query()
            ->fromSub($lignesRetenues, 'lr')
            ->join('pv_ligne_resultats as plr', 'plr.pv_ligne_id', '=', 'lr.pv_ligne_id')
            ->join('candidatures as c', function ($join) use ($electionId) {
                $join->on('c.id', '=', 'plr.candidature_id')
                    ->where('c.election_id', '=', $electionId)
                    ->where('c.statut', '=', 'validee');
            })
            ->select(
                'lr.commune_id',
                'c.entite_politique_id',
                DB::raw('SUM(COALESCE(plr.nombre_voix, 0)) as total_voix')
            )
            ->groupBy('lr.commune_id', 'c.entite_politique_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $communeId = (int) $row->commune_id;
            $entiteId = (int) $row->entite_politique_id;
            $result[$communeId][$entiteId] = (int) $row->total_voix;
        }

        return $result;
    }

    private function lignesRetenuesSousRequete(int $electionId)
    {
        $lignesAvecPv = DB::table('proces_verbaux as pv')
            ->join('pv_lignes as pl', 'pl.proces_verbal_id', '=', 'pv.id')
            ->join('arrondissements as a', 'a.id', '=', DB::raw('pv.niveau_id::int'))
            ->join('communes as c', 'c.id', '=', 'a.commune_id')
            ->join('departements as d', 'd.id', '=', 'c.departement_id')
            ->where('pv.niveau', 'arrondissement')
            ->whereIn('pv.statut', ['valide', 'publie'])
            ->where('pv.election_id', $electionId)
            ->whereNotNull('pl.village_quartier_id')
            ->selectRaw('
                d.id AS departement_id,
                c.id AS commune_id,
                a.id AS arrondissement_id,
                pv.id AS proces_verbal_id,
                pv.created_at AS pv_created_at,
                pl.id AS pv_ligne_id,
                pl.village_quartier_id,
                pl.created_at AS ligne_created_at
            ');

        $dedup = DB::query()
            ->fromSub($lignesAvecPv, 'l')
            ->select('l.*')
            ->selectRaw('
                ROW_NUMBER() OVER (
                    PARTITION BY l.arrondissement_id, l.village_quartier_id
                    ORDER BY l.pv_created_at DESC, l.ligne_created_at DESC, l.pv_ligne_id DESC
                ) AS rn
            ');

        return DB::query()->fromSub($dedup, 'd')->where('d.rn', 1);
    }

    private function buildClassementNational($entites, array $totauxParEntite): array
    {
        $classement = [];

        foreach ($entites as $entite) {
            $totaux = $totauxParEntite[$entite->id] ?? ['voix' => 0, 'pourcentage_national' => 0];

            $classement[] = [
                'entite_id' => (int) $entite->id,
                'nom' => (string) $entite->nom,
                'sigle' => (string) ($entite->sigle ?? ''),
                'ticket' => (string) ($entite->ticket ?? ($entite->sigle ?: $entite->nom)),
                'titulaire' => (string) ($entite->titulaire ?? ''),
                'colistier' => (string) ($entite->colistier ?? ''),
                'numero_liste' => (int) ($entite->numero_liste ?? 9999),
                'voix' => (int) ($totaux['voix'] ?? 0),
                'pourcentage' => (float) ($totaux['pourcentage_national'] ?? 0),
            ];
        }

        usort($classement, static function (array $a, array $b): int {
            if ($a['voix'] === $b['voix']) {
                if ($a['numero_liste'] === $b['numero_liste']) {
                    return $a['entite_id'] <=> $b['entite_id'];
                }

                return $a['numero_liste'] <=> $b['numero_liste'];
            }

            return $b['voix'] <=> $a['voix'];
        });

        foreach ($classement as $index => &$row) {
            $row['rang'] = $index + 1;
        }
        unset($row);

        return $classement;
    }

    private function getReglesElection(int $electionId): array
    {
        $reglesBrutes = DB::table('elections as e')
            ->leftJoin('types_election as te', 'te.id', '=', 'e.type_election_id')
            ->where('e.id', $electionId)
            ->value('te.regles');

        $regles = $this->decodeJsonField($reglesBrutes);

        return [
            'seuil_premier_tour_pourcent' => (float) ($regles['seuil_premier_tour_pourcent'] ?? 50),
            'nombre_candidats_second_tour' => (int) ($regles['nombre_candidats_second_tour'] ?? 2),
            'systeme' => (string) ($regles['systeme'] ?? 'majoritaire'),
            'tours' => (int) ($regles['tours'] ?? 2),
        ];
    }

    private function decodeJsonField($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function labelStatut(string $statut): string
    {
        return match ($statut) {
            'elu_premier_tour' => 'Élu au premier tour',
            'qualifie_second_tour' => 'Qualifié pour le second tour',
            default => 'Non qualifié',
        };
    }
}
