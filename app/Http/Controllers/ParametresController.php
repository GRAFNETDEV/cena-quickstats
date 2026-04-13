<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ParametresController extends Controller
{
    /**
     * Récupérer l'élection active depuis la session
     */
    private function electionActive()
    {
        $electionId = session('election_active');
        
        if ($electionId) {
            $e = DB::table('elections')->where('id', $electionId)->first();
            if ($e) return $e;
        }

        $e = DB::table('elections')->where('statut', 'active')->orderByDesc('id')->first();
        if (!$e) $e = DB::table('elections')->orderByDesc('id')->first();
        
        if ($e) session(['election_active' => $e->id]);
        
        return $e;
    }

    /**
     * Page principale des paramètres
     */
    public function index(Request $request)
    {
        $election = $this->electionActive();
        abort_if(!$election, 404, "Aucune élection trouvée");

        // Onglet actif (par défaut : statistiques)
        $tab = $request->get('tab', 'stats');

        return view('parametres.index', [
            'election' => $election,
            'tab' => $tab,
        ]);
    }

    /**
     * TOP UTILISATEURS - Qui saisit le plus
     */
    public function topUtilisateurs(Request $request)
    {
        $election = $this->electionActive();
        abort_if(!$election, 404, "Aucune élection trouvée");

        $limit = $request->get('limit', 20);

        $topUsers = DB::select("
            SELECT
                pv.saisi_par_user_id,
                CONCAT(COALESCE(u.nom,''), ' ', COALESCE(u.prenom,'')) AS user_nom,
                u.email AS user_email,
                COUNT(*) AS nb_pv_saisis,
                COUNT(CASE WHEN pv.statut = 'valide' THEN 1 END) AS nb_pv_valides,
                COUNT(CASE WHEN pv.statut = 'publie' THEN 1 END) AS nb_pv_publies,
                MIN(pv.created_at) AS premiere_saisie,
                MAX(pv.created_at) AS derniere_saisie
            FROM public.proces_verbaux pv
            LEFT JOIN public.users u ON u.id = pv.saisi_par_user_id
            WHERE pv.election_id = ?
                AND pv.niveau = 'arrondissement'
                AND pv.saisi_par_user_id IS NOT NULL
            GROUP BY pv.saisi_par_user_id, user_nom, user_email
            ORDER BY nb_pv_saisis DESC
            LIMIT ?
        ", [$election->id, $limit]);

        return response()->json([
            'success' => true,
            'data' => $topUsers,
        ]);
    }

    /**
     * ✅ RECHERCHER UN PV
     */
    public function rechercherPv(Request $request)
    {
        $election = $this->electionActive();
        abort_if(!$election, 404, "Aucune élection trouvée");

        $type = $request->get('type');
        $valeur = trim((string) $request->get('valeur', ''));
        $statut = trim((string) $request->get('statut', ''));
        $limit = (int) $request->get('limit', 50);
        $limit = max(10, min(200, $limit));

        if ($type === null || $valeur === '') {
            return response()->json([
                'success' => false,
                'message' => 'Type et valeur requis',
            ], 400);
        }

        if (!in_array($type, ['code', 'village', 'arrondissement', 'commune', 'departement'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Type de recherche invalide',
            ], 400);
        }

        $query = DB::table('proces_verbaux as pv')
            ->select([
                'pv.id',
                'pv.code',
                'pv.numero_pv',
                'pv.statut',
                'pv.niveau',
                'pv.niveau_id',
                'pv.election_id',
                'pv.created_at',
                'pv.updated_at',
                'pv.observations',
                DB::raw("CONCAT(COALESCE(u.nom,''), ' ', COALESCE(u.prenom,'')) as saisi_par"),
                DB::raw("(
                    SELECT a.nom
                    FROM public.arrondissements a
                    WHERE a.id = pv.niveau_id::int
                    LIMIT 1
                ) as arrondissement_nom"),
                DB::raw("(
                    SELECT c.nom
                    FROM public.arrondissements a
                    JOIN public.communes c ON c.id = a.commune_id
                    WHERE a.id = pv.niveau_id::int
                    LIMIT 1
                ) as commune_nom"),
                DB::raw("(
                    SELECT d.nom
                    FROM public.arrondissements a
                    JOIN public.communes c ON c.id = a.commune_id
                    JOIN public.departements d ON d.id = c.departement_id
                    WHERE a.id = pv.niveau_id::int
                    LIMIT 1
                ) as departement_nom"),
                DB::raw("(
                    SELECT COUNT(*)
                    FROM public.pv_lignes pl
                    WHERE pl.proces_verbal_id = pv.id
                ) as nb_lignes"),
            ])
            ->leftJoin('users as u', 'u.id', '=', 'pv.saisi_par_user_id')
            ->where('pv.election_id', $election->id);

        switch ($type) {
            case 'code':
                $query->where(function ($q) use ($valeur) {
                    $q->where('pv.code', 'ILIKE', "%{$valeur}%")
                        ->orWhere('pv.numero_pv', 'ILIKE', "%{$valeur}%");
                });
                break;

            case 'village':
                $query->whereExists(function ($sq) use ($valeur) {
                    $sq->select(DB::raw(1))
                        ->from('pv_lignes as pl')
                        ->join('villages_quartiers as vq', 'vq.id', '=', 'pl.village_quartier_id')
                        ->whereColumn('pl.proces_verbal_id', 'pv.id')
                        ->where('vq.nom', 'ILIKE', "%{$valeur}%");
                });
                break;

            case 'arrondissement':
                $query->whereExists(function ($sq) use ($valeur) {
                    $sq->select(DB::raw(1))
                        ->from('arrondissements as a')
                        ->whereRaw('a.id = pv.niveau_id::int')
                        ->where('a.nom', 'ILIKE', "%{$valeur}%");
                })->where('pv.niveau', 'arrondissement');
                break;

            case 'commune':
                $query->whereExists(function ($sq) use ($valeur) {
                    $sq->select(DB::raw(1))
                        ->from('arrondissements as a')
                        ->join('communes as c', 'c.id', '=', 'a.commune_id')
                        ->whereRaw('a.id = pv.niveau_id::int')
                        ->where('c.nom', 'ILIKE', "%{$valeur}%");
                })->where('pv.niveau', 'arrondissement');
                break;

            case 'departement':
                $query->whereExists(function ($sq) use ($valeur) {
                    $sq->select(DB::raw(1))
                        ->from('arrondissements as a')
                        ->join('communes as c', 'c.id', '=', 'a.commune_id')
                        ->join('departements as d', 'd.id', '=', 'c.departement_id')
                        ->whereRaw('a.id = pv.niveau_id::int')
                        ->where('d.nom', 'ILIKE', "%{$valeur}%");
                })->where('pv.niveau', 'arrondissement');
                break;
        }

        if ($statut !== '' && $statut !== 'all') {
            $query->where('pv.statut', $statut);
        }

        $countsByStatus = (clone $query)
            ->select('pv.statut', DB::raw('COUNT(*) as total'))
            ->groupBy('pv.statut')
            ->pluck('total', 'pv.statut')
            ->toArray();

        $resultats = $query
            ->orderByDesc('pv.updated_at')
            ->orderByDesc('pv.created_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'count' => $resultats->count(),
            'data' => $resultats,
            'meta' => [
                'type' => $type,
                'valeur' => $valeur,
                'statut' => $statut === '' ? 'all' : $statut,
                'limit' => $limit,
                'total_matching' => array_sum($countsByStatus),
                'counts_by_status' => $countsByStatus,
            ],
        ]);
    }

    /**
     * ✅ DÉTAILS D'UN PV
     */
    public function detailsPv($id)
    {
        $pv = DB::table('proces_verbaux as pv')
            ->select([
                'pv.*',
                DB::raw("CONCAT(COALESCE(u.nom,''), ' ', COALESCE(u.prenom,'')) as saisi_par_nom"),
                'u.email as saisi_par_email',
                DB::raw("(
                    SELECT a.nom
                    FROM public.arrondissements a
                    WHERE a.id = pv.niveau_id::int
                    LIMIT 1
                ) as arrondissement_nom"),
                DB::raw("(
                    SELECT c.nom
                    FROM public.arrondissements a
                    JOIN public.communes c ON c.id = a.commune_id
                    WHERE a.id = pv.niveau_id::int
                    LIMIT 1
                ) as commune_nom"),
                DB::raw("(
                    SELECT d.nom
                    FROM public.arrondissements a
                    JOIN public.communes c ON c.id = a.commune_id
                    JOIN public.departements d ON d.id = c.departement_id
                    WHERE a.id = pv.niveau_id::int
                    LIMIT 1
                ) as departement_nom"),
            ])
            ->leftJoin('users as u', 'u.id', '=', 'pv.saisi_par_user_id')
            ->where('pv.id', $id)
            ->first();

        if (!$pv) {
            return response()->json([
                'success' => false,
                'message' => 'PV non trouvé',
            ], 404);
        }

        $lignes = DB::table('pv_lignes as pl')
            ->select([
                'pl.*',
                'vq.nom as village_nom',
            ])
            ->leftJoin('villages_quartiers as vq', 'vq.id', '=', 'pl.village_quartier_id')
            ->where('pl.proces_verbal_id', $id)
            ->get();

        $resume = DB::table('pv_lignes as pl')
            ->leftJoin('pv_ligne_resultats as plr', 'plr.pv_ligne_id', '=', 'pl.id')
            ->where('pl.proces_verbal_id', $id)
            ->selectRaw('COUNT(DISTINCT pl.id) as nb_lignes')
            ->selectRaw('COUNT(plr.id) as nb_resultats')
            ->selectRaw('COALESCE(SUM(plr.nombre_voix), 0) as total_voix')
            ->selectRaw('COALESCE(SUM(pl.bulletins_nuls), 0) as bulletins_nuls')
            ->first();

        $niveau = null;
        if ($pv->niveau === 'arrondissement' && $pv->niveau_id) {
            $niveau = DB::table('arrondissements')->find($pv->niveau_id);
        }

        return response()->json([
            'success' => true,
            'pv' => $pv,
            'lignes' => $lignes,
            'resume' => $resume,
            'niveau' => $niveau,
        ]);
    }

    /**
     * ✅ ANNULER UN PV
     */
    public function annulerPv(Request $request, $id)
    {
        $request->validate([
            'motif' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();
        $motif = $request->get('motif', 'Annulation manuelle');

        try {
            DB::beginTransaction();

            $pv = DB::table('proces_verbaux')->where('id', $id)->first();

            if (!$pv) {
                return response()->json([
                    'success' => false,
                    'message' => 'PV non trouvé',
                ], 404);
            }

            if ($pv->statut === 'annule') {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce PV est déjà annulé',
                ], 400);
            }

            // Mise à jour du PV
            DB::table('proces_verbaux')
                ->where('id', $id)
                ->update([
                    'statut' => 'annule',
                    'updated_at' => now(),
                    'observations' => DB::raw("CONCAT(COALESCE(observations,''), ' | PV annulé le " . now() . " par " . $user->nom . " - Motif: " . $motif . "')"),
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'PV annulé avec succès',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ SUPPRIMER DÉFINITIVEMENT UN PV (avec transaction)
     */
    public function supprimerPv(Request $request, $id)
    {
        $request->validate([
            'confirmation_code' => 'required|string|max:100',
            'motif' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            $pv = DB::table('proces_verbaux')->where('id', $id)->first();

            if (!$pv) {
                return response()->json([
                    'success' => false,
                    'message' => 'PV non trouvé',
                ], 404);
            }

            $confirmationCode = strtoupper(trim((string) $request->get('confirmation_code')));
            $codePv = strtoupper(trim((string) ($pv->code ?? '')));
            if ($confirmationCode === '' || $confirmationCode !== $codePv) {
                return response()->json([
                    'success' => false,
                    'message' => 'Code de confirmation invalide. Veuillez saisir le code PV exact.',
                ], 422);
            }

            $motif = trim((string) $request->get('motif', ''));
            $user = Auth::user();

            $lignesIds = DB::table('pv_lignes')
                ->where('proces_verbal_id', $id)
                ->pluck('id')
                ->toArray();

            $nbLignes = count($lignesIds);
            $nbResultats = 0;
            if (!empty($lignesIds)) {
                $nbResultats = (int) DB::table('pv_ligne_resultats')
                    ->whereIn('pv_ligne_id', $lignesIds)
                    ->count();
            }

            DB::table('traces')->insert([
                'user_id' => $user?->id,
                'pv_id' => $id,
                'action' => 'suppression_definitive_pv',
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'donnees_pv' => json_encode([
                    'pv_id' => $pv->id,
                    'code' => $pv->code,
                    'numero_pv' => $pv->numero_pv,
                    'statut' => $pv->statut,
                    'niveau' => $pv->niveau,
                    'niveau_id' => $pv->niveau_id,
                    'election_id' => $pv->election_id,
                    'created_at' => $pv->created_at,
                    'updated_at' => $pv->updated_at,
                ]),
                'metadata' => json_encode([
                    'motif' => $motif,
                    'nb_lignes' => $nbLignes,
                    'nb_resultats' => $nbResultats,
                    'confirmation_code' => $confirmationCode,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::statement("
                DELETE FROM public.pv_ligne_resultats r
                WHERE r.pv_ligne_id IN (
                    SELECT l.id
                    FROM public.pv_lignes l
                    WHERE l.proces_verbal_id = ?
                )
            ", [$id]);

            DB::table('pv_lignes')->where('proces_verbal_id', $id)->delete();
            DB::table('proces_verbaux')->where('id', $id)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'PV supprimé définitivement avec succès',
                'deleted' => [
                    'pv_id' => (int) $id,
                    'code' => $pv->code,
                    'nb_lignes' => $nbLignes,
                    'nb_resultats' => $nbResultats,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ LISTE DES UTILISATEURS
     */
    public function utilisateurs()
    {
        $users = DB::table('users')
            ->select([
                'id',
                'nom',
                'prenom',
                'email',
                'role',
                'created_at',
                DB::raw("(SELECT COUNT(*) FROM proces_verbaux WHERE saisi_par_user_id = users.id) as nb_pv_saisis"),
            ])
            ->orderBy('nom')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * AUTOCOMPLETE pour la recherche
     */
    public function autocomplete(Request $request)
    {
        $type = $request->get('type');
        $query = $request->get('query', '');

        if (!$type || strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $results = [];

        switch ($type) {
            case 'village':
                $results = DB::table('villages_quartiers')
                    ->select('id', 'nom as label')
                    ->where('nom', 'ILIKE', "%{$query}%")
                    ->orderBy('nom')
                    ->limit(20)
                    ->get();
                break;

            case 'arrondissement':
                $results = DB::table('arrondissements')
                    ->select('id', 'nom as label')
                    ->where('nom', 'ILIKE', "%{$query}%")
                    ->orderBy('nom')
                    ->limit(20)
                    ->get();
                break;

            case 'commune':
                $results = DB::table('communes')
                    ->select('id', 'nom as label')
                    ->where('nom', 'ILIKE', "%{$query}%")
                    ->orderBy('nom')
                    ->limit(20)
                    ->get();
                break;

            case 'departement':
                $results = DB::table('departements')
                    ->select('id', 'nom as label')
                    ->where('nom', 'ILIKE', "%{$query}%")
                    ->orderBy('nom')
                    ->limit(20)
                    ->get();
                break;
        }

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }
}
