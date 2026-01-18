<?php

namespace App\Http\Controllers;

use App\Services\StatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    private StatsService $stats;

    public function __construct(StatsService $stats)
    {
        $this->stats = $stats;
    }

    /**
     * ✅ CORRECTION : Récupérer l'élection active depuis la session
     */
    private function electionActive()
    {
        // 1️⃣ D'abord vérifier la session
        $electionId = session('election_active');
        
        if ($electionId) {
            $e = DB::table('elections')->where('id', $electionId)->first();
            if ($e) {
                return $e;
            }
        }

        // 2️⃣ Sinon, prendre l'élection active par défaut
        $e = DB::table('elections')->where('statut', 'active')->orderByDesc('id')->first();
        
        // 3️⃣ Sinon, prendre la plus récente
        if (!$e) {
            $e = DB::table('elections')->orderByDesc('id')->first();
        }

        // 4️⃣ Sauvegarder en session pour les prochaines requêtes
        if ($e) {
            session(['election_active' => $e->id]);
        }

        return $e;
    }

    public function national(Request $request)
    {
        // ✅ Accepter election_id en paramètre GET
        if ($request->has('election_id')) {
            $electionId = (int) $request->get('election_id');
            session(['election_active' => $electionId]);
        }

        $election = $this->electionActive();
        abort_if(!$election, 404, "Aucune élection trouvée");

        $data = $this->stats->national($election->id);

        // ✅ S'assurer que tous les départements ont les bonnes clés
        $parDepartement = collect($data['par_departement'])->map(function($dept) {
            return [
                'id' => $dept['id'] ?? 0,
                'nom' => $dept['nom'] ?? '',
                'code' => $dept['code'] ?? '',
                'nombre_pv_valides' => $dept['nombre_pv_valides'] ?? 0,
                'inscrits_cena' => $dept['inscrits_cena'] ?? 0,
                'inscrits_comptabilises' => $dept['inscrits_comptabilises'] ?? 0,
                'couverture_saisie' => $dept['couverture_saisie'] ?? 0,
                'nombre_votants' => $dept['nombre_votants'] ?? 0,
                'nombre_suffrages_exprimes' => $dept['nombre_suffrages_exprimes'] ?? 0,
                'nombre_bulletins_nuls' => $dept['nombre_bulletins_nuls'] ?? 0,
                'taux_participation_global' => $dept['taux_participation_global'] ?? 0,
                'taux_participation_bureaux_comptabilises' => $dept['taux_participation_bureaux_comptabilises'] ?? 0,
            ];
        })->toArray();

        return view('stats.national', [
            'stats' => [
                'election' => $election,
                'totaux' => $data['totaux'],
                'progression' => $data['progression'],
                'par_departement' => $parDepartement,
            ]
        ]);
    }

    public function departement(Request $request)
    {
        // ✅ Accepter election_id en paramètre GET
        if ($request->has('election_id')) {
            $electionId = (int) $request->get('election_id');
            session(['election_active' => $electionId]);
        }

        $election = $this->electionActive();
        abort_if(!$election, 404, "Aucune élection trouvée");

        // ✅ Exclure le département Diaspora (id = 13)
        $departements = DB::table('departements')
            ->select('id', 'nom', 'code')
            ->where('id', '<>', 13) // Exclure Diaspora
            ->orderBy('nom')
            ->get();
            
        $departementId = (int) ($request->get('departement_id') ?: ($departements->first()->id ?? 0));

        if ($departementId) {
            $data = $this->stats->departement($election->id, $departementId);
        } else {
            $data = [
                'totaux' => [],
                'par_commune' => [],
                'par_circonscription' => [],
                'circonscriptions' => [],
            ];
        }

        return view('stats.departement', [
            'election' => $election,
            'departements' => $departements,
            'departementId' => $departementId,
            'stats' => [
                'election' => $election,
                'totaux' => $data['totaux'],
                'par_commune' => $data['par_commune'],
                'par_circonscription' => $data['par_circonscription'] ?? [],
                'circonscriptions' => $data['circonscriptions'] ?? [],
            ],
        ]);
    }

    public function circonscription(Request $request)
    {
        // ✅ Accepter election_id en paramètre GET
        if ($request->has('election_id')) {
            $electionId = (int) $request->get('election_id');
            session(['election_active' => $electionId]);
        }

        $election = $this->electionActive();
        abort_if(!$election, 404, "Aucune élection trouvée");

        $circonscriptions = DB::table('circonscriptions_electorales')->select('id', 'nom', 'numero as numero_ordre')->where('numero', '<=', 24)->orderBy('nom')->get();
        $circonscriptionId = (int) ($request->get('circonscription_id') ?: ($circonscriptions->first()->id ?? 0));

        if ($circonscriptionId) {
            $data = $this->stats->circonscription($election->id, $circonscriptionId);
        } else {
            $data = [
                'totaux' => [],
                'nombre_sieges' => 0,
                'par_commune' => [],
                'entites' => [],
            ];
        }

        return view('stats.circonscription', [
            'election' => $election,
            'circonscriptions' => $circonscriptions,
            'circonscriptionId' => $circonscriptionId,
            'stats' => [
                'election' => $election,
                'totaux' => $data['totaux'],
                'nombre_sieges' => $data['nombre_sieges'],
                'par_commune' => $data['par_commune'],
                'entites' => $data['entites'],
            ],
        ]);
    }

    public function commune(Request $request)
    {
        // ✅ Accepter election_id en paramètre GET
        if ($request->has('election_id')) {
            $electionId = (int) $request->get('election_id');
            session(['election_active' => $electionId]);
        }

        $election = $this->electionActive();
        abort_if(!$election, 404, "Aucune élection trouvée");

        $communes = DB::table('communes')->select('id', 'nom')->orderBy('nom')->get();
        $communeId = (int) ($request->get('commune_id') ?: ($communes->first()->id ?? 0));
        $commune = DB::table('communes')->find($communeId);

        if ($communeId) {
            $data = $this->stats->commune($election->id, $communeId);
        } else {
            $data = ['totaux' => [], 'par_arrondissement' => [], 'arrondissements' => []];
        }

        return view('stats.commune', [
            'election' => $election,
            'communes' => $communes,
            'commune' => $commune,
            'communeId' => $communeId,
            'stats' => [
                'election' => $election,
                'totaux' => $data['totaux'],
                'par_arrondissement' => $data['par_arrondissement'] ?? $data['arrondissements'] ?? [],
            ],
        ]);
    }

    public function arrondissement(Request $request)
    {
        // ✅ Accepter election_id en paramètre GET
        if ($request->has('election_id')) {
            $electionId = (int) $request->get('election_id');
            session(['election_active' => $electionId]);
        }

        $election = $this->electionActive();
        abort_if(!$election, 404, "Aucune élection trouvée");

        $arrondissements = DB::table('arrondissements')->select('id', 'nom')->orderBy('nom')->get();
        $arrondissementId = (int) ($request->get('arrondissement_id') ?: ($arrondissements->first()->id ?? 0));

        if ($arrondissementId) {
            $data = $this->stats->arrondissement($election->id, $arrondissementId);
        } else {
            $data = ['totaux' => [], 'villages' => [], 'arrondissements' => [], 'selected' => null];
        }

        $selected = DB::table('arrondissements')->find($arrondissementId);

        return view('stats.arrondissement', [
            'election' => $election,
            'arrondissementId' => $arrondissementId,
            'stats' => [
                'election' => $election,
                'totaux' => $data['totaux'],
                'villages' => $data['villages'],
                'arrondissements' => $arrondissements,
                'selected' => $selected,
            ],
        ]);
    }

    /**
     * ✅ NOUVELLE VUE VILLAGE : Statistiques globales + liste complète des villages
     */
    public function village(Request $request)
    {
        // ✅ Accepter election_id en paramètre GET
        if ($request->has('election_id')) {
            $electionId = (int) $request->get('election_id');
            session(['election_active' => $electionId]);
        }

        $election = $this->electionActive();
        abort_if(!$election, 404, "Aucune élection trouvée");

        // ========================================
        // STATISTIQUES GLOBALES
        // ========================================
        
        // 1️⃣ Nombre de villages inscrits (hors diaspora)
        $nombreVillagesInscrits = DB::table('villages_quartiers as vq')
            ->join('arrondissements as a', 'a.id', '=', 'vq.arrondissement_id')
            ->join('communes as com', 'com.id', '=', 'a.commune_id')
            ->join('departements as dep', 'dep.id', '=', 'com.departement_id')
            ->where('dep.nom', 'NOT ILIKE', '%diaspora%')
            ->where('com.nom', 'NOT ILIKE', '%diaspora%')
            ->where('a.nom', 'NOT ILIKE', '%diaspora%')
            ->where('vq.nom', 'NOT ILIKE', '%diaspora%')
            ->count();

        // 2️⃣ Nombre de villages saisis dans les PV (même logique que le listing)
        $nombreVillagesSaisis = DB::select("
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
            lignes_retenues AS (
                SELECT * FROM dedup_lignes WHERE rn = 1
            )
            SELECT COUNT(DISTINCT village_quartier_id) as total
            FROM lignes_retenues
        ", [$election->id]);

        $nombreVillagesSaisis = $nombreVillagesSaisis[0]->total ?? 0;

        // ========================================
        // LISTE COMPLÈTE DES VILLAGES AVEC VOIX
        // ========================================
        
        $villagesAvecVoix = DB::select("
            WITH lignes_avec_pv AS (
                SELECT
                    pv.niveau_id::int AS arrondissement_id,
                    pv.id AS proces_verbal_id,
                    pv.created_at AS pv_created_at,
                    pl.id AS pv_ligne_id,
                    pl.village_quartier_id,
                    pl.created_at AS ligne_created_at,
                    COALESCE(pl.bulletins_nuls,0) AS bulletins_nuls
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
            lignes_retenues AS (
                SELECT * FROM dedup_lignes WHERE rn = 1
            ),
            voix_par_entite AS (
                SELECT
                    dep.id AS departement_id,
                    dep.nom AS departement_nom,
                    com.id AS commune_id,
                    com.nom AS commune_nom,
                    ce.id AS circonscription_id,
                    ce.nom AS circonscription_nom,
                    a.id AS arrondissement_id,
                    a.nom AS arrondissement_nom,
                    vq.id AS village_quartier_id,
                    vq.nom AS village_quartier_nom,
                    ep.id AS entite_id,
                    ep.sigle AS entite_sigle,
                    ep.nom AS entite_nom,
                    COALESCE(SUM(plr.nombre_voix),0) AS total_voix,
                    0 AS ordre_ligne
                FROM lignes_retenues lr
                JOIN public.arrondissements a ON a.id = lr.arrondissement_id
                JOIN public.communes com ON com.id = a.commune_id
                LEFT JOIN public.departements dep ON dep.id = com.departement_id
                LEFT JOIN public.circonscriptions_electorales ce ON ce.id = a.circonscription_id
                JOIN public.villages_quartiers vq ON vq.id = lr.village_quartier_id
                JOIN public.pv_ligne_resultats plr ON plr.pv_ligne_id = lr.pv_ligne_id
                JOIN public.candidatures ca ON ca.id = plr.candidature_id
                JOIN public.entites_politiques ep ON ep.id = ca.entite_politique_id
                GROUP BY
                    dep.id, dep.nom,
                    com.id, com.nom,
                    ce.id, ce.nom,
                    a.id, a.nom,
                    vq.id, vq.nom,
                    ep.id, ep.sigle, ep.nom
            ),
            ligne_bulletins_nuls AS (
                SELECT
                    dep.id AS departement_id,
                    dep.nom AS departement_nom,
                    com.id AS commune_id,
                    com.nom AS commune_nom,
                    ce.id AS circonscription_id,
                    ce.nom AS circonscription_nom,
                    a.id AS arrondissement_id,
                    a.nom AS arrondissement_nom,
                    vq.id AS village_quartier_id,
                    vq.nom AS village_quartier_nom,
                    NULL::int AS entite_id,
                    NULL::text AS entite_sigle,
                    'Bulletin nul'::text AS entite_nom,
                    lr.bulletins_nuls AS total_voix,
                    1 AS ordre_ligne
                FROM lignes_retenues lr
                JOIN public.arrondissements a ON a.id = lr.arrondissement_id
                JOIN public.communes com ON com.id = a.commune_id
                LEFT JOIN public.departements dep ON dep.id = com.departement_id
                LEFT JOIN public.circonscriptions_electorales ce ON ce.id = a.circonscription_id
                JOIN public.villages_quartiers vq ON vq.id = lr.village_quartier_id
            )
            SELECT
                departement_id, departement_nom,
                commune_id, commune_nom,
                circonscription_id, circonscription_nom,
                arrondissement_id, arrondissement_nom,
                village_quartier_id, village_quartier_nom,
                entite_id, entite_sigle, entite_nom,
                total_voix
            FROM (
                SELECT * FROM voix_par_entite
                UNION ALL
                SELECT * FROM ligne_bulletins_nuls
            ) x
            ORDER BY
                departement_nom, commune_nom, arrondissement_nom, village_quartier_nom,
                ordre_ligne ASC,
                total_voix DESC,
                entite_nom
        ", [$election->id]);

        // ========================================
        // VILLAGES NON SAISIS
        // ========================================
        
        $villagesNonSaisis = DB::select("
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
            )
            SELECT
                r.departement_id, r.departement_nom,
                r.commune_id, r.commune_nom,
                r.arrondissement_id, r.arrondissement_nom,
                r.village_quartier_id, r.village_quartier_nom
            FROM referentiel r
            LEFT JOIN villages_saisis vs ON vs.village_quartier_id = r.village_quartier_id
            WHERE vs.village_quartier_id IS NULL
            ORDER BY r.departement_nom, r.commune_nom, r.arrondissement_nom, r.village_quartier_nom
        ", [$election->id]);

        // ========================================
        // GROUPER LES DONNÉES PAR VILLAGE
        // ========================================
        
        $villagesGroupes = [];
        foreach ($villagesAvecVoix as $row) {
            $villageId = $row->village_quartier_id;
            
            if (!isset($villagesGroupes[$villageId])) {
                $villagesGroupes[$villageId] = [
                    'village_quartier_id' => $villageId,
                    'village_quartier_nom' => $row->village_quartier_nom,
                    'arrondissement_id' => $row->arrondissement_id,
                    'arrondissement_nom' => $row->arrondissement_nom,
                    'commune_id' => $row->commune_id,
                    'commune_nom' => $row->commune_nom,
                    'departement_id' => $row->departement_id,
                    'departement_nom' => $row->departement_nom,
                    'circonscription_id' => $row->circonscription_id,
                    'circonscription_nom' => $row->circonscription_nom,
                    'entites' => [],
                    'total_voix' => 0,
                    'bulletins_nuls' => 0,
                ];
            }
            
            if ($row->entite_nom === 'Bulletin nul') {
                $villagesGroupes[$villageId]['bulletins_nuls'] = $row->total_voix;
            } else {
                $villagesGroupes[$villageId]['entites'][] = [
                    'entite_id' => $row->entite_id,
                    'entite_sigle' => $row->entite_sigle,
                    'entite_nom' => $row->entite_nom,
                    'voix' => $row->total_voix,
                ];
                $villagesGroupes[$villageId]['total_voix'] += $row->total_voix;
            }
        }

        return view('stats.village', [
            'election' => $election,
            'stats' => [
                'election' => $election,
                'nombre_villages_inscrits' => $nombreVillagesInscrits,
                'nombre_villages_saisis' => $nombreVillagesSaisis,
                'villages_avec_voix' => array_values($villagesGroupes),
                'villages_non_saisis' => $villagesNonSaisis,
            ],
        ]);
    }

    /**
     * ✅ NOUVEAU : Changer l'élection active
     */
    public function changeElection(Request $request)
    {
        $electionId = $request->input('election_id');
        
        if ($electionId) {
            session(['election_active' => $electionId]);
        }

        return redirect()->back()->with('success', 'Élection changée avec succès');
    }
}