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
     * ✅ TOP UTILISATEURS - Qui saisit le plus
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

        $type = $request->get('type'); // 'code', 'village', 'arrondissement', 'commune', 'departement'
        $valeur = $request->get('valeur');

        if (!$type || !$valeur) {
            return response()->json([
                'success' => false,
                'message' => 'Type et valeur requis',
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
                DB::raw("CONCAT(COALESCE(u.nom,''), ' ', COALESCE(u.prenom,'')) as saisi_par"),
            ])
            ->leftJoin('users as u', 'u.id', '=', 'pv.saisi_par_user_id')
            ->where('pv.election_id', $election->id);

        switch ($type) {
            case 'code':
                $query->where('pv.code', 'ILIKE', "%{$valeur}%");
                break;

            case 'village':
                $query->join('pv_lignes as pl', 'pl.proces_verbal_id', '=', 'pv.id')
                    ->join('villages_quartiers as vq', 'vq.id', '=', 'pl.village_quartier_id')
                    ->where('vq.nom', 'ILIKE', "%{$valeur}%")
                    ->distinct();
                break;

            case 'arrondissement':
                $query->join('arrondissements as a', function($join) {
                    $join->on('a.id', '=', 'pv.niveau_id')
                        ->where('pv.niveau', '=', 'arrondissement');
                })
                ->where('a.nom', 'ILIKE', "%{$valeur}%");
                break;

            case 'commune':
                $query->join('arrondissements as a', function($join) {
                    $join->on('a.id', '=', 'pv.niveau_id')
                        ->where('pv.niveau', '=', 'arrondissement');
                })
                ->join('communes as c', 'c.id', '=', 'a.commune_id')
                ->where('c.nom', 'ILIKE', "%{$valeur}%");
                break;

            case 'departement':
                $query->join('arrondissements as a', function($join) {
                    $join->on('a.id', '=', 'pv.niveau_id')
                        ->where('pv.niveau', '=', 'arrondissement');
                })
                ->join('communes as c', 'c.id', '=', 'a.commune_id')
                ->join('departements as d', 'd.id', '=', 'c.departement_id')
                ->where('d.nom', 'ILIKE', "%{$valeur}%");
                break;

            default:
                return response()->json([
                    'success' => false,
                    'message' => 'Type de recherche invalide',
                ], 400);
        }

        $resultats = $query->orderBy('pv.created_at', 'desc')->limit(100)->get();

        return response()->json([
            'success' => true,
            'count' => $resultats->count(),
            'data' => $resultats,
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

        // Récupérer les lignes du PV
        $lignes = DB::table('pv_lignes as pl')
            ->select([
                'pl.*',
                'vq.nom as village_nom',
            ])
            ->leftJoin('villages_quartiers as vq', 'vq.id', '=', 'pl.village_quartier_id')
            ->where('pl.proces_verbal_id', $id)
            ->get();

        // Récupérer le niveau associé
        $niveau = null;
        if ($pv->niveau === 'arrondissement' && $pv->niveau_id) {
            $niveau = DB::table('arrondissements')->find($pv->niveau_id);
        }

        return response()->json([
            'success' => true,
            'pv' => $pv,
            'lignes' => $lignes,
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
    public function supprimerPv($id)
    {
        try {
            DB::beginTransaction();

            $pv = DB::table('proces_verbaux')->where('id', $id)->first();

            if (!$pv) {
                return response()->json([
                    'success' => false,
                    'message' => 'PV non trouvé',
                ], 404);
            }

            // 1) Supprimer les résultats liés aux lignes du PV
            DB::statement("
                DELETE FROM public.pv_ligne_resultats r
                WHERE r.pv_ligne_id IN (
                    SELECT l.id
                    FROM public.pv_lignes l
                    WHERE l.proces_verbal_id = ?
                )
            ", [$id]);

            // 2) Supprimer les lignes du PV
            DB::table('pv_lignes')->where('proces_verbal_id', $id)->delete();

            // 3) Supprimer le PV
            DB::table('proces_verbaux')->where('id', $id)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'PV supprimé définitivement avec succès',
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
     * ✅ AUTOCOMPLETE pour la recherche
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
