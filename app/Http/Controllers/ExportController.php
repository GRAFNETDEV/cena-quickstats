<?php

namespace App\Http\Controllers;

use App\Services\StatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExportController extends Controller
{
    private StatsService $stats;

    public function __construct(StatsService $stats)
    {
        $this->stats = $stats;
    }

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
     * Export National CSV
     */
    public function nationalCsv()
    {
        $election = $this->electionActive();
        if (!$election) abort(404, "Aucune élection trouvée");

        $data = $this->stats->national($election->id);
        
        $filename = 'stats_national_' . date('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            // BOM UTF-8 pour Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // En-têtes
            fputcsv($file, [
                'Département',
                'Code',
                'PV Validés',
                'Inscrits CENA',
                'Inscrits Comptabilisés',
                'Couverture (%)',
                'Votants',
                'Suffrages Exprimés',
                'Bulletins Nuls',
                'Participation (%)',
            ], ';');
            
            // Données
            foreach ($data['par_departement'] as $dept) {
                fputcsv($file, [
                    $dept['nom'],
                    $dept['code'],
                    $dept['nombre_pv'],
                    $dept['inscrits_cena'],
                    $dept['inscrits_comptabilises'],
                    number_format($dept['couverture_saisie'], 2, ',', ''),
                    $dept['nombre_votants'],
                    $dept['nombre_suffrages_exprimes'],
                    ($data['totaux']['nombre_bulletins_nuls'] ?? 0),
                    number_format($dept['taux_participation'], 2, ',', ''),
                ], ';');
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export Département CSV
     */
    public function departementCsv(Request $request)
    {
        $election = $this->electionActive();
        if (!$election) abort(404, "Aucune élection trouvée");

        $departementId = (int) $request->get('departement_id');
        if (!$departementId) abort(400, "Département non spécifié");

        $departement = DB::table('departements')->find($departementId);
        if (!$departement) abort(404, "Département non trouvé");

        $data = $this->stats->departement($election->id, $departementId);
        
        $filename = 'stats_dept_' . $departement->code . '_' . date('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($file, [
                'Commune',
                'Code',
                'PV Validés',
                'Inscrits CENA',
                'Inscrits Comptabilisés',
                'Couverture (%)',
                'Votants',
                'Participation (%)',
            ], ';');
            
            foreach ($data['par_commune'] as $c) {
                fputcsv($file, [
                    $c['nom'],
                    $c['code'] ?? '',
                    $c['nombre_pv'],
                    $c['inscrits_cena'],
                    $c['inscrits_comptabilises'],
                    number_format($c['couverture_saisie'], 2, ',', ''),
                    $c['nombre_votants'],
                    number_format($c['taux_participation'], 2, ',', ''),
                ], ';');
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export Communes d'un Département CSV
     */
    public function departementCommunesCsv(Request $request)
    {
        return $this->departementCsv($request);
    }

    /**
     * Export Circonscription CSV
     */
    public function circonscriptionCsv(Request $request)
    {
        $election = $this->electionActive();
        if (!$election) abort(404, "Aucune élection trouvée");

        $circonscriptionId = (int) $request->get('circonscription_id');
        if (!$circonscriptionId) abort(400, "Circonscription non spécifiée");

        $circ = DB::table('circonscriptions_electorales')->find($circonscriptionId);
        if (!$circ) abort(404, "Circonscription non trouvée");

        $data = $this->stats->circonscription($election->id, $circonscriptionId);
        
        $filename = 'stats_circ_' . ($circ->numero ?? $circonscriptionId) . '_' . date('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($file, [
                'Commune',
                'PV Validés',
                'Inscrits CENA',
                'Inscrits Comptabilisés',
                'Couverture (%)',
                'Participation (%)',
            ], ';');
            
            foreach ($data['par_commune'] as $c) {
                fputcsv($file, [
                    $c['nom'],
                    $c['pv'] ?? $c['nombre_pv'] ?? 0,
                    $c['inscrits_cena'] ?? 0,
                    $c['inscrits_comptabilises'] ?? 0,
                    number_format($c['couverture_saisie'] ?? 0, 2, ',', ''),
                    number_format($c['participation'] ?? $c['taux_participation'] ?? 0, 2, ',', ''),
                ], ';');
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export Communes d'une Circonscription CSV
     */
    public function circonscriptionCommunesCsv(Request $request)
    {
        return $this->circonscriptionCsv($request);
    }

    /**
     * Export Commune CSV
     */
    public function communeCsv(Request $request)
    {
        $election = $this->electionActive();
        if (!$election) abort(404, "Aucune élection trouvée");

        $communeId = (int) $request->get('commune_id');
        if (!$communeId) abort(400, "Commune non spécifiée");

        $commune = DB::table('communes')->find($communeId);
        if (!$commune) abort(404, "Commune non trouvée");

        $data = $this->stats->commune($election->id, $communeId);
        
        $filename = 'stats_commune_' . ($commune->code ?? $communeId) . '_' . date('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($file, [
                'Arrondissement',
                'PV Validés',
                'Inscrits CENA',
                'Votants',
                'Participation (%)',
            ], ';');
            
            foreach ($data['par_arrondissement'] as $a) {
                fputcsv($file, [
                    $a['nom'],
                    $a['nombre_pv'],
                    $a['inscrits_cena'],
                    $a['nombre_votants'],
                    number_format($a['taux_participation'], 2, ',', ''),
                ], ';');
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export Arrondissements d'une Commune CSV
     */
    public function communeArrondissementsCsv(Request $request)
    {
        return $this->communeCsv($request);
    }

    /**
     * Export Arrondissement CSV
     */
    public function arrondissementCsv(Request $request)
    {
        $election = $this->electionActive();
        if (!$election) abort(404, "Aucune élection trouvée");

        $arrondissementId = (int) $request->get('arrondissement_id');
        if (!$arrondissementId) abort(400, "Arrondissement non spécifié");

        $arr = DB::table('arrondissements')->find($arrondissementId);
        if (!$arr) abort(404, "Arrondissement non trouvé");

        $data = $this->stats->arrondissement($election->id, $arrondissementId);
        
        $filename = 'stats_arr_' . $arrondissementId . '_' . date('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($file, [
                'Village/Quartier',
                'PV Validés',
                'Inscrits CENA',
                'Couverture (%)',
                'Votants',
                'Participation (%)',
            ], ';');
            
            foreach ($data['villages'] as $v) {
                fputcsv($file, [
                    $v['nom'],
                    $v['nombre_pv'],
                    $v['inscrits_cena'],
                    number_format($v['couverture_saisie'], 2, ',', ''),
                    $v['nombre_votants'],
                    number_format($v['taux_participation'], 2, ',', ''),
                ], ';');
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export Village CSV
     */
    public function villageCsv(Request $request)
    {
        $election = $this->electionActive();
        if (!$election) abort(404, "Aucune élection trouvée");

        $villageId = (int) $request->get('village_id');
        if (!$villageId) abort(400, "Village non spécifié");

        $village = DB::table('villages_quartiers')->find($villageId);
        if (!$village) abort(404, "Village non trouvé");

        $data = $this->stats->village($election->id, $villageId);
        
        $filename = 'stats_village_' . $villageId . '_' . date('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($file, [
                'Poste de Vote',
                'Numéro',
                'Inscrits',
                'Votants',
                'Suffrages',
                'Bulletins Nuls',
            ], ';');
            
            foreach ($data['postes'] as $p) {
                fputcsv($file, [
                    $p->nom,
                    $p->numero,
                    $p->electeurs_inscrits,
                    $p->votants,
                    $p->suffrages,
                    $p->nuls,
                ], ';');
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export Postes d'un Village CSV
     */
    public function villagePostesCsv(Request $request)
    {
        return $this->villageCsv($request);
    }
}