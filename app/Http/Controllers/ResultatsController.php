<?php

namespace App\Http\Controllers;

use App\Services\ResultatsService;
use App\Services\ResultatsCommunalesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ResultatsController extends Controller
{
    private ResultatsService $resultatsService;
    private ResultatsCommunalesService $resultatsCommunalesService;

    public function __construct(
        ResultatsService $resultatsService,
        ResultatsCommunalesService $resultatsCommunalesService
    ) {
        $this->resultatsService = $resultatsService;
        $this->resultatsCommunalesService = $resultatsCommunalesService;
    }

    /**
     * Récupérer l'élection active
     */
    private function electionActive()
    {
        $electionId = session('election_active');
        
        if ($electionId) {
            $e = DB::table('elections')->where('id', $electionId)->first();
            if ($e) {
                return $e;
            }
        }

        $e = DB::table('elections')
            ->where('statut', 'active')
            ->orderByDesc('id')
            ->first();
        
        if (!$e) {
            $e = DB::table('elections')
                ->orderByDesc('date_scrutin')
                ->first();
        }
        
        if ($e) {
            session(['election_active' => $e->id]);
        }
        
        return $e;
    }

    /**
     * ✅ Page principale des résultats
     * Route UNIQUE qui détecte le type d'élection et affiche la bonne vue
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

        // ✅ DÉTECTION DU TYPE D'ÉLECTION
        $typeElection = strtolower($election->type ?? 'legislative');

        // Liste de toutes les élections pour le sélecteur
        $elections = DB::table('elections')
            ->orderBy('date_scrutin', 'desc')
            ->get();

        // ✅ ROUTER VERS LE BON SERVICE SELON LE TYPE
        if ($typeElection === 'communale') {
            return $this->indexCommunales($request, $election, $elections);
        } else {
            return $this->indexLegislatives($request, $election, $elections);
        }
    }

    /**
     * ✅ Résultats pour élections LÉGISLATIVES (par circonscription)
     */
    private function indexLegislatives(Request $request, $election, $elections)
    {
        // Récupérer les données de base (matrice des résultats)
        $data = $this->resultatsService->getResultatsParCirconscription($election->id);

        // Vérifier si une compilation est demandée
        $compilation = null;
        if ($request->has('compiler') || $request->get('compiler') === '1') {
            $cacheKey = "compilation_resultats_{$election->id}";
            $compilation = Cache::remember($cacheKey, 300, function () use ($election) {
                return $this->resultatsService->repartirSieges($election->id);
            });
        }

        return view('resultats.index', [
            'election' => $election,
            'elections' => $elections,
            'data' => $data,
            'compilation' => $compilation,
            'type' => 'legislative',
        ]);
    }

    /**
     * ✅ Résultats pour élections COMMUNALES (par commune)
     */
    private function indexCommunales(Request $request, $election, $elections)
    {
        // Récupérer les données de base (matrice des résultats par commune)
        $data = $this->resultatsCommunalesService->getResultatsParCommune($election->id);

        // Vérifier si une compilation est demandée
        $compilation = null;
        if ($request->has('compiler') || $request->get('compiler') === '1') {
            $cacheKey = "compilation_communales_{$election->id}";
            $compilation = Cache::remember($cacheKey, 300, function () use ($election) {
                return $this->resultatsCommunalesService->repartirSieges($election->id);
            });
        }

        return view('resultats.communales', [
            'election' => $election,
            'elections' => $elections,
            'data' => $data,
            'compilation' => $compilation,
            'type' => 'communale',
        ]);
    }

    /**
     * Vérifier l'éligibilité (AJAX) - Détecte automatiquement le type
     */
    public function verifierEligibilite(Request $request)
    {
        $election = $this->electionActive();
        if (!$election) {
            return response()->json(['error' => 'Aucune élection trouvée'], 404);
        }

        try {
            $typeElection = strtolower($election->type ?? 'legislative');
            
            if ($typeElection === 'communale') {
                $result = $this->resultatsCommunalesService->verifierEligibiliteNationale($election->id);
            } else {
                $result = $this->resultatsService->verifierEligibilite($election->id);
            }
            
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la vérification d\'éligibilité',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Compiler les résultats (AJAX) - Détecte automatiquement le type
     */
    public function compiler(Request $request)
    {
        $election = $this->electionActive();
        if (!$election) {
            return response()->json(['error' => 'Aucune élection trouvée'], 404);
        }

        try {
            $typeElection = strtolower($election->type ?? 'legislative');
            
            if ($typeElection === 'communale') {
                $result = $this->resultatsCommunalesService->repartirSieges($election->id);
                Cache::forget("compilation_communales_{$election->id}");
            } else {
                $result = $this->resultatsService->repartirSieges($election->id);
                Cache::forget("compilation_resultats_{$election->id}");
            }
            
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la compilation',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export de la matrice en CSV - Détecte automatiquement le type
     */
    public function exportMatriceCSV(Request $request)
    {
        $electionId = $request->get('election_id', session('election_active'));
        $election = DB::table('elections')->find($electionId);
        
        if (!$election) {
            abort(404, 'Élection introuvable');
        }

        $typeElection = strtolower($election->type ?? 'legislative');
        
        try {
            if ($typeElection === 'communale') {
                $csv = $this->resultatsCommunalesService->exporterResultatsCSV($electionId);
                $filename = 'matrice_communales_' . date('Y-m-d_His') . '.csv';
            } else {
                $csv = $this->resultatsService->exporterResultatsCSV($electionId);
                $filename = 'matrice_legislatives_' . date('Y-m-d_His') . '.csv';
            }
            
            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Exception $e) {
            return back()->with('error', 'Erreur lors de l\'export : ' . $e->getMessage());
        }
    }

    /**
     * Export des sièges en CSV - Détecte automatiquement le type
     */
    public function exportSiegesCSV(Request $request)
    {
        $electionId = $request->get('election_id', session('election_active'));
        $election = DB::table('elections')->find($electionId);
        
        if (!$election) {
            abort(404, 'Élection introuvable');
        }

        $typeElection = strtolower($election->type ?? 'legislative');
        
        try {
            if ($typeElection === 'communale') {
                $csv = $this->resultatsCommunalesService->exporterSiegesCSV($electionId);
                $filename = 'sieges_communales_' . date('Y-m-d_His') . '.csv';
            } else {
                $csv = $this->resultatsService->exporterSiegesCSV($electionId);
                $filename = 'sieges_legislatives_' . date('Y-m-d_His') . '.csv';
            }
            
            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Exception $e) {
            return back()->with('error', 'Erreur lors de l\'export : ' . $e->getMessage());
        }
    }

    /**
     * Export des détails en CSV
     */
    public function exportDetailsCSV(Request $request)
    {
        $electionId = $request->get('election_id', session('election_active'));
        $election = DB::table('elections')->find($electionId);
        
        if (!$election) {
            abort(404, 'Élection introuvable');
        }

        $typeElection = strtolower($election->type ?? 'legislative');
        
        try {
            if ($typeElection === 'communale') {
                $csv = $this->resultatsCommunalesService->exporterDetailsParCommune($electionId);
                $filename = 'details_communes_' . date('Y-m-d_His') . '.csv';
            } else {
                $compilation = Cache::remember(
                    "compilation_sieges_{$electionId}",
                    now()->addMinutes(5),
                    fn() => $this->resultatsService->repartirSieges($electionId)
                );
                $csv = $this->genererCSVDetailsCirconscriptions($compilation);
                $filename = 'details_circonscriptions_' . date('Y-m-d_His') . '.csv';
            }
            
            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Exception $e) {
            return back()->with('error', 'Erreur lors de l\'export : ' . $e->getMessage());
        }
    }

    /**
     * Export des arrondissements (communales uniquement)
     */
    public function exportArrondissementsCSV(Request $request)
    {
        $electionId = $request->get('election_id', session('election_active'));
        $election = DB::table('elections')->find($electionId);
        
        if (!$election) {
            abort(404, 'Élection introuvable');
        }

        $typeElection = strtolower($election->type ?? 'legislative');
        
        if ($typeElection !== 'communale') {
            return back()->with('error', 'Cet export n\'est disponible que pour les élections communales');
        }

        try {
            $csv = $this->resultatsCommunalesService->exporterDetailsParArrondissement($electionId);
            $filename = 'details_arrondissements_' . date('Y-m-d_His') . '.csv';
            
            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Exception $e) {
            return back()->with('error', 'Erreur lors de l\'export : ' . $e->getMessage());
        }
    }

    /**
     * Résumé des résultats (API JSON)
     */
    public function resume()
    {
        $election = $this->electionActive();
        if (!$election) {
            return response()->json(['error' => 'Aucune élection trouvée'], 404);
        }

        try {
            $typeElection = strtolower($election->type ?? 'legislative');
            
            if ($typeElection === 'communale') {
                $resume = $this->resultatsCommunalesService->getResume($election->id);
            } else {
                $resume = $this->resultatsService->getResume($election->id);
            }
            
            return response()->json([
                'success' => true,
                'election' => [
                    'id' => $election->id,
                    'nom' => $election->nom,
                    'type' => $election->type,
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
     */
    public function reinitialiserCache()
    {
        $election = $this->electionActive();
        if (!$election) {
            return redirect()->back()->with('error', 'Aucune élection trouvée');
        }

        $typeElection = strtolower($election->type ?? 'legislative');
        
        if ($typeElection === 'communale') {
            Cache::forget("compilation_communales_{$election->id}");
        } else {
            Cache::forget("compilation_resultats_{$election->id}");
        }
        
        return redirect()->back()->with('success', 'Cache réinitialisé avec succès');
    }

    /**
     * Générer le CSV des détails par circonscription (législatives)
     */
    private function genererCSVDetailsCirconscriptions($compilation): string
    {
        $csv = chr(0xEF).chr(0xBB).chr(0xBF);
        
        $csv .= "Circonscription;Sièges Total;Sièges Ordinaires;Sièges Femmes;Quotient Electoral;";
        
        $entites = $compilation['data']['entites'];
        foreach ($entites as $entite) {
            $nom = $entite->sigle ?: $entite->nom;
            $csv .= "{$nom} (Ord);";
        }
        
        $csv .= "Gagnant Siège Femme;Voix Gagnant\n";
        
        foreach ($compilation['repartition'] as $circId => $rep) {
            $circ = $rep['info'];
            
            $nomCirc = $circ->numero == 1 ? "1ère circonscription" : "{$circ->numero}ème circonscription";
            $csv .= $nomCirc . ";";
            
            $csv .= $circ->nombre_sieges_total . ";";
            $csv .= ($circ->nombre_sieges_total - $circ->nombre_sieges_femmes) . ";";
            $csv .= $circ->nombre_sieges_femmes . ";";
            $csv .= number_format($rep['quotient_electoral'], 2, ',', '') . ";";
            
            foreach ($entites as $entite) {
                $nbSieges = $rep['sieges_ordinaires'][$entite->id] ?? 0;
                $csv .= $nbSieges . ";";
            }
            
            if ($rep['siege_femme']) {
                $csv .= $rep['siege_femme']['entite_sigle'] . ";";
                $csv .= $rep['siege_femme']['voix'];
            } else {
                $csv .= ";";
            }
            
            $csv .= "\n";
        }
        
        $csv .= "\nTOTAUX;";
        $csv .= ";;;;;";
        
        foreach ($entites as $entite) {
            $totalOrdinaires = $compilation['sieges_totaux'][$entite->id]['sieges_ordinaires'] ?? 0;
            $csv .= $totalOrdinaires . ";";
        }
        
        $csv .= ";\n";
        
        return $csv;
    }
}