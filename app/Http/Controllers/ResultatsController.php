<?php

namespace App\Http\Controllers;

use App\Services\ResultatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ResultatsController extends Controller
{
    private ResultatsService $resultatsService;

    public function __construct(ResultatsService $resultatsService)
    {
        $this->resultatsService = $resultatsService;
    }

    /**
     * Récupérer l'élection active
     * 
     * @return object|null
     */
    private function electionActive()
    {
        $electionId = session('election_active');
        
        // Vérifier si l'élection en session existe toujours
        if ($electionId) {
            $e = DB::table('elections')->where('id', $electionId)->first();
            if ($e) {
                return $e;
            }
        }

        // Sinon, chercher une élection active
        $e = DB::table('elections')
            ->where('statut', 'active')
            ->orderByDesc('id')
            ->first();
        
        // Si aucune élection active, prendre la plus récente
        if (!$e) {
            $e = DB::table('elections')
                ->orderByDesc('date_scrutin')
                ->first();
        }
        
        // Sauvegarder en session
        if ($e) {
            session(['election_active' => $e->id]);
        }
        
        return $e;
    }

    /**
     * Page principale des résultats
     * 
     * @param Request $request
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function index(Request $request)
    {
        // Gérer le changement d'élection
        if ($request->has('election_id')) {
            $electionId = (int) $request->get('election_id');
            session(['election_active' => $electionId]);
        }

        $election = $this->electionActive();
        if (!$election) {
            return redirect()->route('dashboard')->with('error', 'Aucune élection trouvée');
        }

        // Récupérer les données de base (matrice des résultats)
        $data = $this->resultatsService->getResultatsParCirconscription($election->id);

        // Vérifier si une compilation est demandée
        $compilation = null;
        if ($request->has('compiler') || $request->get('compiler') === '1') {
            // Utiliser le cache pour éviter de recalculer à chaque rafraîchissement
            $cacheKey = "compilation_resultats_{$election->id}";
            $compilation = Cache::remember($cacheKey, 300, function () use ($election) {
                return $this->resultatsService->repartirSieges($election->id);
            });
        }

        // Liste de toutes les élections pour le sélecteur
        $elections = DB::table('elections')
            ->orderBy('date_scrutin', 'desc')
            ->get();

        return view('resultats.index', [
            'election' => $election,
            'elections' => $elections,
            'data' => $data,
            'compilation' => $compilation,
        ]);
    }

    /**
     * Vérifier l'éligibilité (AJAX)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifierEligibilite(Request $request)
    {
        $election = $this->electionActive();
        if (!$election) {
            return response()->json([
                'error' => 'Aucune élection trouvée'
            ], 404);
        }

        try {
            $result = $this->resultatsService->verifierEligibilite($election->id);
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la vérification d\'éligibilité',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Compiler les résultats (AJAX)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function compiler(Request $request)
    {
        $election = $this->electionActive();
        if (!$election) {
            return response()->json([
                'error' => 'Aucune élection trouvée'
            ], 404);
        }

        try {
            $result = $this->resultatsService->repartirSieges($election->id);
            
            // Invalider le cache
            Cache::forget("compilation_resultats_{$election->id}");
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la compilation',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exporter la matrice des résultats en CSV
     * 
     * @return \Illuminate\Http\Response
     */
    public function exportCsv()
    {
        $election = $this->electionActive();
        if (!$election) {
            abort(404, 'Aucune élection trouvée');
        }

        try {
            $csv = $this->resultatsService->exporterResultatsCSV($election->id);
            
            $filename = 'resultats_' . str_replace(' ', '_', $election->nom) . '_' . date('Y-m-d_His') . '.csv';
            
            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erreur lors de l\'export : ' . $e->getMessage());
        }
    }

    /**
     * Exporter les sièges en CSV
     * 
     * @return \Illuminate\Http\Response
     */
    public function exportSiegesCsv()
    {
        $election = $this->electionActive();
        if (!$election) {
            abort(404, 'Aucune élection trouvée');
        }

        try {
            $csv = $this->resultatsService->exporterSiegesCSV($election->id);
            
            $filename = 'sieges_' . str_replace(' ', '_', $election->nom) . '_' . date('Y-m-d_His') . '.csv';
            
            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erreur lors de l\'export : ' . $e->getMessage());
        }
    }

    /**
     * Obtenir le résumé des résultats (API)
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function resume()
    {
        $election = $this->electionActive();
        if (!$election) {
            return response()->json([
                'error' => 'Aucune élection trouvée'
            ], 404);
        }

        try {
            $resume = $this->resultatsService->getResume($election->id);
            
            return response()->json([
                'success' => true,
                'election' => [
                    'id' => $election->id,
                    'nom' => $election->nom,
                    'date_scrutin' => $election->date_scrutin,
                ],
                'resume' => $resume
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la génération du résumé',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Réinitialiser le cache de compilation
     * 
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reinitialiserCache()
    {
        $election = $this->electionActive();
        if (!$election) {
            return redirect()->back()->with('error', 'Aucune élection trouvée');
        }

        Cache::forget("compilation_resultats_{$election->id}");
        
        return redirect()->back()->with('success', 'Cache réinitialisé avec succès');
    }

    /**
     * Afficher les détails d'une circonscription
     * 
     * @param int $circonscriptionId
     * @return \Illuminate\View\View
     */
    public function detailsCirconscription(int $circonscriptionId)
    {
        $election = $this->electionActive();
        if (!$election) {
            return redirect()->route('dashboard')->with('error', 'Aucune élection trouvée');
        }

        $circonscription = DB::table('circonscriptions_electorales')
            ->where('id', $circonscriptionId)
            ->first();

        if (!$circonscription) {
            abort(404, 'Circonscription introuvable');
        }

        $compilation = $this->resultatsService->repartirSieges($election->id);
        
        return view('resultats.circonscription', [
            'election' => $election,
            'circonscription' => $circonscription,
            'repartition' => $compilation['repartition'][$circonscriptionId] ?? null,
            'data' => $compilation['data'],
        ]);
    }
}