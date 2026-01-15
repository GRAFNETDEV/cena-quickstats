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

        $departements = DB::table('departements')->select('id', 'nom', 'code')->orderBy('nom')->get();
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

        $circonscriptions = DB::table('circonscriptions_electorales')->select('id', 'nom', 'numero as numero_ordre')->orderBy('nom')->get();
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
            'stats' => [
                'election' => $election,
                'totaux' => $data['totaux'],
                'villages' => $data['villages'],
                'arrondissements' => $arrondissements,
                'selected' => $selected,
            ],
        ]);
    }

    public function village(Request $request)
    {
        // ✅ Accepter election_id en paramètre GET
        if ($request->has('election_id')) {
            $electionId = (int) $request->get('election_id');
            session(['election_active' => $electionId]);
        }

        $election = $this->electionActive();
        abort_if(!$election, 404, "Aucune élection trouvée");

        $villages = DB::table('villages_quartiers')->select('id', 'nom')->orderBy('nom')->get();
        $villageId = (int) ($request->get('village_id') ?: ($villages->first()->id ?? 0));

        if ($villageId) {
            $data = $this->stats->village($election->id, $villageId);
        } else {
            $data = ['totaux' => [], 'postes' => []];
        }

        // Calculer les stats par poste
        $parPosteVote = [];
        foreach (($data['postes'] ?? []) as $p) {
            $taux = ($p['electeurs_inscrits'] ?? 0) > 0
                ? round((($p['votants'] ?? 0) / $p['electeurs_inscrits']) * 100, 2)
                : 0;

            $parPosteVote[] = [
                'id' => $p['id'] ?? 0,
                'centre_vote_nom' => $p['centre_nom'] ?? '-',
                'poste_vote_nom' => $p['nom'] ?? '-',
                'numero' => $p['numero'] ?? '-',
                'inscrits' => $p['electeurs_inscrits'] ?? 0,
                'votants' => $p['votants'] ?? 0,
                'suffrages' => $p['suffrages'] ?? 0,
                'taux_participation' => $taux,
            ];
        }

        // Stats par centre de vote (agrégées)
        $parCentreVote = [];
        $centres = collect($data['postes'] ?? [])->groupBy('centre_nom');
        foreach ($centres as $centreNom => $postesGroupe) {
            $inscrits = $postesGroupe->sum('electeurs_inscrits');
            $votants = $postesGroupe->sum('votants');
            $taux = $inscrits > 0 ? round(($votants / $inscrits) * 100, 2) : 0;

            $parCentreVote[] = [
                'nom' => $centreNom,
                'taux_participation' => $taux,
            ];
        }

        return view('stats.village', [
            'election' => $election,
            'villages' => $villages,
            'villageId' => $villageId,
            'stats' => [
                'election' => $election,
                'totaux' => $data['totaux'],
                'par_poste_vote' => $parPosteVote,
                'par_centre_vote' => $parCentreVote,
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