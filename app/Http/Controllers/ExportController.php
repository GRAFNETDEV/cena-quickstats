<?php

namespace App\Http\Controllers;

use App\Services\StatsService;
use App\Services\ResultatsCommunalesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExportController extends Controller
{
    private StatsService $stats;
    private ResultatsCommunalesService $communalesService;

    public function __construct(
        StatsService $stats,
        ResultatsCommunalesService $communalesService
    ) {
        $this->stats = $stats;
        $this->communalesService = $communalesService;
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
                'Inscrits Comptabilisés',
                'Couverture (%)',
                'Votants',
                'Participation (%)',
            ], ';');
            
            foreach ($data['par_arrondissement'] as $a) {
                fputcsv($file, [
                    $a['nom'],
                    $a['nombre_pv'],
                    $a['inscrits_cena'],
                    $a['inscrits_comptabilises'],
                    number_format($a['couverture_saisie'], 2, ',', ''),
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
                'Inscrits Comptabilisés',
                'Couverture (%)',
                'Votants',
                'Participation (%)',
            ], ';');
            
            foreach ($data['villages'] as $v) {
                fputcsv($file, [
                    $v['nom'],
                    $v['nombre_pv_valides'],
                    $v['inscrits_cena'],
                    $v['inscrits_comptabilises'],
                    number_format($v['couverture_saisie'], 2, ',', ''),
                    $v['nombre_votants'],
                    number_format($v['taux_participation_global'], 2, ',', ''),
                ], ';');
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export Village CSV (ancienne méthode conservée pour compatibilité)
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
            ], ';');
            
            foreach ($data['postes'] as $p) {
                fputcsv($file, [
                    $p['nom'],
                    $p['numero'],
                    $p['electeurs_inscrits'],
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

    /**
     * ✅ EXPORT CSV : Villages Saisis avec Résultats
     */
    public function villageSaisisCsv(Request $request)
    {
        $electionId = $request->get('election_id') ?: session('election_active');
        
        if (!$electionId) {
            return response('Aucune élection sélectionnée', 400);
        }

        // Récupérer les données
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
                    dep.nom AS departement_nom,
                    com.nom AS commune_nom,
                    a.nom AS arrondissement_nom,
                    vq.nom AS village_quartier_nom,
                    ep.sigle AS entite_sigle,
                    COALESCE(SUM(plr.nombre_voix),0) AS total_voix,
                    0 AS ordre_ligne
                FROM lignes_retenues lr
                JOIN public.arrondissements a ON a.id = lr.arrondissement_id
                JOIN public.communes com ON com.id = a.commune_id
                LEFT JOIN public.departements dep ON dep.id = com.departement_id
                JOIN public.villages_quartiers vq ON vq.id = lr.village_quartier_id
                JOIN public.pv_ligne_resultats plr ON plr.pv_ligne_id = lr.pv_ligne_id
                JOIN public.candidatures ca ON ca.id = plr.candidature_id
                JOIN public.entites_politiques ep ON ep.id = ca.entite_politique_id
                GROUP BY
                    dep.nom, com.nom, a.nom, vq.nom, ep.sigle
            ),
            ligne_bulletins_nuls AS (
                SELECT
                    dep.nom AS departement_nom,
                    com.nom AS commune_nom,
                    a.nom AS arrondissement_nom,
                    vq.nom AS village_quartier_nom,
                    'Bulletin nul'::text AS entite_sigle,
                    lr.bulletins_nuls AS total_voix,
                    1 AS ordre_ligne
                FROM lignes_retenues lr
                JOIN public.arrondissements a ON a.id = lr.arrondissement_id
                JOIN public.communes com ON com.id = a.commune_id
                LEFT JOIN public.departements dep ON dep.id = com.departement_id
                JOIN public.villages_quartiers vq ON vq.id = lr.village_quartier_id
            )
            SELECT
                departement_nom AS \"Département\",
                commune_nom AS \"Commune\",
                arrondissement_nom AS \"Arrondissement\",
                village_quartier_nom AS \"Village/Quartier\",
                entite_sigle AS \"Entité\",
                total_voix AS \"Voix\"
            FROM (
                SELECT * FROM voix_par_entite
                UNION ALL
                SELECT * FROM ligne_bulletins_nuls
            ) x
            ORDER BY
                departement_nom, commune_nom, arrondissement_nom, village_quartier_nom,
                ordre_ligne ASC,
                total_voix DESC
        ", [$electionId]);

        // Générer le CSV
        $filename = 'villages_saisis_' . date('Y-m-d_His') . '.csv';
        
        $callback = function() use ($villagesAvecVoix) {
            $file = fopen('php://output', 'w');
            
            // BOM UTF-8 pour Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // En-têtes
            if (!empty($villagesAvecVoix)) {
                $headers = array_keys((array) $villagesAvecVoix[0]);
                fputcsv($file, $headers, ';');
            }
            
            // Données
            foreach ($villagesAvecVoix as $row) {
                fputcsv($file, (array) $row, ';');
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * ✅ EXPORT CSV : Villages Non Saisis
     */
    public function villageNonSaisisCsv(Request $request)
    {
        $electionId = $request->get('election_id') ?: session('election_active');
        
        if (!$electionId) {
            return response('Aucune élection sélectionnée', 400);
        }

        // Récupérer les données
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
                    dep.nom AS departement_nom,
                    com.nom AS commune_nom,
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
                r.departement_nom AS \"Département\",
                r.commune_nom AS \"Commune\",
                r.arrondissement_nom AS \"Arrondissement\",
                r.village_quartier_nom AS \"Village/Quartier\",
                'Non saisi' AS \"Statut\"
            FROM referentiel r
            LEFT JOIN villages_saisis vs ON vs.village_quartier_id = r.village_quartier_id
            WHERE vs.village_quartier_id IS NULL
            ORDER BY r.departement_nom, r.commune_nom, r.arrondissement_nom, r.village_quartier_nom
        ", [$electionId]);

        // Générer le CSV
        $filename = 'villages_non_saisis_' . date('Y-m-d_His') . '.csv';
        
        $callback = function() use ($villagesNonSaisis) {
            $file = fopen('php://output', 'w');
            
            // BOM UTF-8 pour Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // En-têtes
            if (!empty($villagesNonSaisis)) {
                $headers = array_keys((array) $villagesNonSaisis[0]);
                fputcsv($file, $headers, ';');
            }
            
            // Données
            foreach ($villagesNonSaisis as $row) {
                fputcsv($file, (array) $row, ';');
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * ✅ NOUVEAUX EXPORTS POUR ÉLECTIONS COMMUNALES
     */

    /**
     * Export matrice des résultats communales (toutes communes)
     */
    public function communalesMatriceCsv(Request $request)
    {
        $electionId = $request->get('election_id');
        if (!$electionId) {
            $election = $this->electionActive();
            $electionId = $election ? $election->id : null;
        }

        if (!$electionId) abort(404, "Aucune élection trouvée");

        $election = DB::table('elections')->find($electionId);
        if (!$election) abort(404, "Élection introuvable");

        try {
            $csv = $this->communalesService->exporterResultatsCSV($electionId);
            $filename = 'matrice_communales_' . date('Y-m-d_His') . '.csv';
            
            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        } catch (\Exception $e) {
            abort(500, "Erreur lors de l'export : " . $e->getMessage());
        }
    }

    /**
     * Export des sièges communales
     */
    public function communalesSiegesCsv(Request $request)
    {
        $electionId = $request->get('election_id');
        if (!$electionId) {
            $election = $this->electionActive();
            $electionId = $election ? $election->id : null;
        }

        if (!$electionId) abort(404, "Aucune élection trouvée");

        $election = DB::table('elections')->find($electionId);
        if (!$election) abort(404, "Élection introuvable");

        try {
            $csv = $this->communalesService->exporterSiegesCSV($electionId);
            $filename = 'sieges_communales_' . date('Y-m-d_His') . '.csv';
            
            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        } catch (\Exception $e) {
            abort(500, "Erreur lors de l'export : " . $e->getMessage());
        }
    }

    /**
     * Export des détails par commune (communales)
     */
    public function communalesDetailsCsv(Request $request)
    {
        $electionId = $request->get('election_id');
        if (!$electionId) {
            $election = $this->electionActive();
            $electionId = $election ? $election->id : null;
        }

        if (!$electionId) abort(404, "Aucune élection trouvée");

        $election = DB::table('elections')->find($electionId);
        if (!$election) abort(404, "Élection introuvable");

        try {
            $csv = $this->communalesService->exporterDetailsParCommune($electionId);
            $filename = 'details_communes_' . date('Y-m-d_His') . '.csv';
            
            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        } catch (\Exception $e) {
            abort(500, "Erreur lors de l'export : " . $e->getMessage());
        }
    }

    /**
     * Export des détails par arrondissement (communales)
     */
    public function communalesArrondissementsCsv(Request $request)
    {
        $electionId = $request->get('election_id');
        if (!$electionId) {
            $election = $this->electionActive();
            $electionId = $election ? $election->id : null;
        }

        if (!$electionId) abort(404, "Aucune élection trouvée");

        $election = DB::table('elections')->find($electionId);
        if (!$election) abort(404, "Élection introuvable");

        try {
            $csv = $this->communalesService->exporterDetailsParArrondissement($electionId);
            $filename = 'details_arrondissements_' . date('Y-m-d_His') . '.csv';
            
            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        } catch (\Exception $e) {
            abort(500, "Erreur lors de l'export : " . $e->getMessage());
        }
    }

    /**
     * Export complet communales (tous les exports en un ZIP)
     */
    public function communalesExportComplet(Request $request)
    {
        $electionId = $request->get('election_id');
        if (!$electionId) {
            $election = $this->electionActive();
            $electionId = $election ? $election->id : null;
        }

        if (!$electionId) abort(404, "Aucune élection trouvée");

        $election = DB::table('elections')->find($electionId);
        if (!$election) abort(404, "Élection introuvable");

        try {
            // Créer un fichier ZIP temporaire
            $zipFilename = 'export_communales_complet_' . date('Y-m-d_His') . '.zip';
            $zipPath = storage_path('app/temp/' . $zipFilename);
            
            // Créer le dossier temp s'il n'existe pas
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
                abort(500, "Impossible de créer le fichier ZIP");
            }

            // Ajouter tous les CSV au ZIP
            $zip->addFromString('01_matrice_resultats.csv', $this->communalesService->exporterResultatsCSV($electionId));
            $zip->addFromString('02_sieges.csv', $this->communalesService->exporterSiegesCSV($electionId));
            $zip->addFromString('03_details_communes.csv', $this->communalesService->exporterDetailsParCommune($electionId));
            $zip->addFromString('04_details_arrondissements.csv', $this->communalesService->exporterDetailsParArrondissement($electionId));

            $zip->close();

            // Télécharger et supprimer le fichier
            return response()->download($zipPath, $zipFilename)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            abort(500, "Erreur lors de l'export complet : " . $e->getMessage());
        }
    }
}