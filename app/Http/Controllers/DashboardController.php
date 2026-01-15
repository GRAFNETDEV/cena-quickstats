<?php

namespace App\Http\Controllers;

use App\Models\Election;
use App\Services\StatsService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected $statsService;

    public function __construct(StatsService $statsService)
    {
        // RETIRÉ: $this->middleware('auth');
        $this->statsService = $statsService;
    }

    /**
     * Page d'accueil du dashboard
     */
    public function index()
    {
        // Récupérer toutes les élections
        $elections = Election::orderBy('date_scrutin', 'desc')->get();

        // Élection par défaut (la plus récente)
        $electionActive = $elections->first();

        return view('dashboard', compact('elections', 'electionActive'));
    }

    /**
     * Sélectionner une élection
     */
    public function selectElection(Request $request)
    {
        $electionId = $request->input('election_id');
        session(['election_active' => $electionId]);

        return redirect()->route('stats.national');
    }

    /**
     * Obtenir l'ID de l'élection active
     */
    private function getElectionActive()
    {
        $electionId = session('election_active');
        
        if (!$electionId) {
            $election = Election::orderBy('date_scrutin', 'desc')->first();
            $electionId = $election?->id;
            session(['election_active' => $electionId]);
        }

        return $electionId;
    }
}