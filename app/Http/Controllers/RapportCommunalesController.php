<?php

namespace App\Http\Controllers;

use App\Services\ResultatsCommunalesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class RapportCommunalesController extends Controller
{
    private ResultatsCommunalesService $communales;

    public function __construct(ResultatsCommunalesService $communales)
    {
        $this->communales = $communales;
    }

    private function electionActiveId(Request $request): int
    {
        $electionId = (int)($request->get('election_id') ?: session('election_active'));
        if ($electionId) return $electionId;

        $e = DB::table('elections')->where('statut', 'active')->orderByDesc('id')->first();
        if (!$e) $e = DB::table('elections')->orderByDesc('id')->first();
        if (!$e) abort(404, "Aucune élection trouvée");

        session(['election_active' => $e->id]);
        return (int)$e->id;
    }

    public function index(Request $request)
    {
        $electionId = $this->electionActiveId($request);

        $niveau = $request->get('niveau', 'national'); // national|departement|commune|arrondissement
        $departementId = (int)$request->get('departement_id');
        $communeId = (int)$request->get('commune_id');
        $arrondissementId = (int)$request->get('arrondissement_id');

        $report = $this->buildReport($electionId, $niveau, $departementId, $communeId, $arrondissementId);

        return view('rapports.communales.index', $report);
    }

    public function pdf(Request $request)
    {
        $electionId = $this->electionActiveId($request);

        $niveau = $request->get('niveau', 'national');
        $departementId = (int)$request->get('departement_id');
        $communeId = (int)$request->get('commune_id');
        $arrondissementId = (int)$request->get('arrondissement_id');

        // ✅ Important: PDF national peut être lourd
        set_time_limit(300); // 5 minutes

        $report = $this->buildReport($electionId, $niveau, $departementId, $communeId, $arrondissementId);

        $pdf = Pdf::loadView('rapports.communales.pdf', $report)
            ->setPaper('A4', 'portrait')
            // ✅ options dompdf (stabilité + perf)
            ->setOption('dpi', 96)
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isRemoteEnabled', false)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isPhpEnabled', true);

        $filename = "rapport_communales_{$niveau}_" . date('Y-m-d_His') . ".pdf";

        return $pdf->download($filename);
    }

    private function buildReport(int $electionId, string $niveau, int $departementId, int $communeId, int $arrondissementId): array
    {
        $election = DB::table('elections')->find($electionId);
        if (!$election) abort(404, "Élection introuvable");

        // ⚠️ repartirSieges est probablement coûteux : on le garde 1 seule fois
        $compilation = $this->communales->repartirSieges($electionId);
        $data = $compilation['data'];

        // référentiels (pour filtres UI)
        $departements = DB::table('departements')->where('id', '<>', 13)->orderBy('nom')->get();
        $communesRef = DB::table('communes')->orderBy('nom')->get();
        $arrondissementsRef = DB::table('arrondissements')->orderBy('nom')->get();

        // Déterminer les communes incluses dans le scope
        $communesScope = collect($data['communes'] ?? []);

        if ($niveau === 'departement' && $departementId) {
            $communesScope = $communesScope->where('departement_id', $departementId)->values();
        } elseif ($niveau === 'commune' && $communeId) {
            $communesScope = $communesScope->where('id', $communeId)->values();
        } elseif ($niveau === 'arrondissement' && $arrondissementId) {
            $arr = DB::table('arrondissements')->find($arrondissementId);
            if (!$arr) abort(404, "Arrondissement introuvable");
            $communesScope = $communesScope->where('id', (int)$arr->commune_id)->values();
        }

        // ✅ Optim : map entités par id (évite collect()->firstWhere dans les boucles profondes)
        $entites = collect($data['entites'] ?? []);
        $entitesById = $entites->keyBy('id');

        // Construire les blocs (commune -> arrondissements)
        // ✅ Optim : réduire le HTML en gardant seulement les listes utiles (sieges > 0)
        $communesBlocs = [];
        foreach ($communesScope as $c) {
            $repCommune = $compilation['repartition'][$c->id] ?? null;
            if (!$repCommune) continue;

            $arrs = $repCommune['repartition_arrondissements'] ?? [];

            // Si niveau arrondissement : filtrer sur l'arrondissement
            if ($niveau === 'arrondissement' && $arrondissementId) {
                $arrs = array_values(array_filter($arrs, fn ($a) => (int)$a['arrondissement_id'] === (int)$arrondissementId));
            }

            // ✅ Réduction : ne garder que les listes sièges>0 (sinon DomPDF explose au national)
            // ✅ Conserver aussi methode_attribution pour affichage
            foreach ($arrs as &$arrData) {
                if (!empty($arrData['listes']) && is_array($arrData['listes'])) {
                    $filtered = [];
                    foreach ($arrData['listes'] as $entiteId => $liste) {
                        if ((int)($liste['sieges'] ?? 0) > 0) {
                            $filtered[$entiteId] = $liste;
                        }
                    }
                    $arrData['listes'] = $filtered;
                }
                
                // ✅ S'assurer que methode_attribution est présent
                if (!isset($arrData['methode_attribution'])) {
                    $arrData['methode_attribution'] = [
                        'type' => 'inconnu',
                        'description' => 'Méthode non déterminée',
                        'details' => '',
                    ];
                }
            }
            unset($arrData);

            $communesBlocs[] = [
                'commune' => $repCommune['info'],
                'population' => (int)($repCommune['population'] ?? 0),
                'sieges' => (int)($repCommune['nombre_sieges'] ?? 0),
                'quotient_communal' => (float)($repCommune['quotient_communal'] ?? 0),
                'arrondissements' => $arrs,
            ];
        }

        // Agrégats scope (voix/sieges) à partir des arrondissements inclus
        $totVoixScope = [];
        $totSiegesScope = [];
        foreach ($entites as $e) {
            $totVoixScope[$e->id] = 0;
            $totSiegesScope[$e->id] = 0;
        }

        foreach ($communesBlocs as $cb) {
            foreach (($cb['arrondissements'] ?? []) as $arrData) {
                foreach (($arrData['listes'] ?? []) as $entiteId => $listeData) {
                    $entiteId = (int)$entiteId;
                    $totVoixScope[$entiteId] = ($totVoixScope[$entiteId] ?? 0) + (int)($listeData['voix'] ?? 0);
                    $totSiegesScope[$entiteId] = ($totSiegesScope[$entiteId] ?? 0) + (int)($listeData['sieges'] ?? 0);
                }
            }
        }

        $totalVoixScope = array_sum($totVoixScope);

        // ✅ TableScope triée: sièges desc puis voix desc (lecture + clair)
        $tableScope = [];
        foreach ($entites as $e) {
            $voix = (int)($totVoixScope[$e->id] ?? 0);
            $pct = $totalVoixScope > 0 ? ($voix / $totalVoixScope) * 100 : 0;

            $tableScope[] = [
                'entite' => $e,
                'voix' => $voix,
                'pct' => (float)$pct,
                'eligible_national' => (bool)($compilation['eligibilite'][$e->id]['eligible'] ?? false),
                'pct_national' => (float)($compilation['eligibilite'][$e->id]['pourcentage_national'] ?? 0),
                'sieges' => (int)($totSiegesScope[$e->id] ?? 0),
            ];
        }

        usort($tableScope, function ($a, $b) {
            $s = ($b['sieges'] <=> $a['sieges']);
            if ($s !== 0) return $s;
            return ($b['voix'] <=> $a['voix']);
        });

        // Villages non saisis selon scope
        $villagesNonSaisis = $this->getVillagesNonSaisis($electionId, $niveau, $departementId, $communeId, $arrondissementId);

        // Titre report
        $titre = $this->buildTitre($niveau, $departementId, $communeId, $arrondissementId);

        return [
            'election' => $election,
            'niveau' => $niveau,
            'titre' => $titre,
            'filters' => [
                'departement_id' => $departementId,
                'commune_id' => $communeId,
                'arrondissement_id' => $arrondissementId,
            ],
            'departements' => $departements,
            'communesRef' => $communesRef,
            'arrondissementsRef' => $arrondissementsRef,

            'eligibilite' => $compilation['eligibilite'],
            'entites' => $data['entites'],
            'entitesById' => $entitesById, // ✅ utile si tu veux l'utiliser côté blade
            'tableScope' => $tableScope,
            'totalVoixScope' => $totalVoixScope,

            'communesBlocs' => $communesBlocs,
            'villagesNonSaisis' => $villagesNonSaisis,

            'mention_provisoire' => 'VERSION PROVISOIRE – Issue de la plateforme de compilation de GRAFNET',
        ];
    }

    private function buildTitre(string $niveau, int $departementId, int $communeId, int $arrondissementId): string
    {
        if ($niveau === 'departement' && $departementId) {
            $d = DB::table('departements')->find($departementId);
            return $d ? "Département de {$d->nom}" : "Département";
        }
        if ($niveau === 'commune' && $communeId) {
            $c = DB::table('communes')->find($communeId);
            return $c ? "Commune de {$c->nom}" : "Commune";
        }
        if ($niveau === 'arrondissement' && $arrondissementId) {
            $a = DB::table('arrondissements')->find($arrondissementId);
            return $a ? "Arrondissement de {$a->nom}" : "Arrondissement";
        }
        return "National";
    }

    /**
     * Villages non saisis / non pris en compte (dédup PV) filtrés par scope
     */
    private function getVillagesNonSaisis(int $electionId, string $niveau, int $departementId, int $communeId, int $arrondissementId): array
    {
        $where = "";
        $params = [$electionId];

        if ($niveau === 'departement' && $departementId) {
            $where = " AND dep.id = ? ";
            $params[] = $departementId;
        } elseif ($niveau === 'commune' && $communeId) {
            $where = " AND com.id = ? ";
            $params[] = $communeId;
        } elseif ($niveau === 'arrondissement' && $arrondissementId) {
            $where = " AND a.id = ? ";
            $params[] = $arrondissementId;
        }

        $sql = "
            WITH lignes_avec_pv AS (
                SELECT
                    pv.niveau_id::int AS arrondissement_id,
                    pv.id AS proces_verbal_id,
                    pv.created_at AS pv_created_at,
                    pl.id AS pv_ligne_id,
                    pl.village_quartier_id,
                    pl.created_at AS ligne_created_at
                FROM public.proces_verbaux pv
                JOIN public.pv_lignes pl ON pl.proces_verbal_id = pv.id
                WHERE pv.niveau = 'arrondissement'
                    AND pv.statut IN ('valide','publie')
                    AND pv.election_id = ?
                    AND pl.village_quartier_id IS NOT NULL
            ),
            dedup_lignes AS (
                SELECT
                    l.*,
                    ROW_NUMBER() OVER (
                        PARTITION BY l.arrondissement_id, l.village_quartier_id
                        ORDER BY l.pv_created_at DESC, l.ligne_created_at DESC, l.pv_ligne_id DESC
                    ) AS rn
                FROM lignes_avec_pv l
            ),
            villages_saisis AS (
                SELECT DISTINCT village_quartier_id
                FROM dedup_lignes
                WHERE rn = 1
            ),
            referentiel AS (
                SELECT
                    dep.id AS departement_id,
                    dep.nom AS departement_nom,
                    com.id AS commune_id,
                    com.nom AS commune_nom,
                    a.id AS arrondissement_id,
                    a.nom AS arrondissement_nom,
                    vq.id AS village_quartier_id,
                    vq.nom AS village_quartier_nom
                FROM public.villages_quartiers vq
                JOIN public.arrondissements a ON a.id = vq.arrondissement_id
                JOIN public.communes com ON com.id = a.commune_id
                JOIN public.departements dep ON dep.id = com.departement_id
                WHERE
                    dep.nom NOT ILIKE '%diaspora%'
                    AND com.nom NOT ILIKE '%diaspora%'
                    AND a.nom NOT ILIKE '%diaspora%'
                    AND vq.nom NOT ILIKE '%diaspora%'
                    {$where}
            )
            SELECT
                departement_nom,
                commune_nom,
                arrondissement_nom,
                village_quartier_nom
            FROM referentiel r
            LEFT JOIN villages_saisis vs ON vs.village_quartier_id = r.village_quartier_id
            WHERE vs.village_quartier_id IS NULL
            ORDER BY departement_nom, commune_nom, arrondissement_nom, village_quartier_nom
        ";

        return DB::select($sql, $params);
    }
}