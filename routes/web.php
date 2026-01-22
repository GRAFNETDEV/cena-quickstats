<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ResultatsController;
use App\Http\Controllers\ParametresController;
use App\Http\Controllers\RapportCommunalesController;

Route::get('/', function () {
    return redirect()->route('login');
});

// Routes protégées par authentification
Route::middleware(['auth'])->group(function () {
    // Dashboard principal
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/select-election', [DashboardController::class, 'selectElection'])->name('select.election');

    // Profil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Statistiques
    Route::prefix('stats')->name('stats.')->group(function () {
        Route::get('/national', [StatsController::class, 'national'])->name('national');
        Route::get('/departement', [StatsController::class, 'departement'])->name('departement');
        Route::get('/circonscription', [StatsController::class, 'circonscription'])->name('circonscription');
        Route::get('/commune', [StatsController::class, 'commune'])->name('commune');
        Route::get('/arrondissement', [StatsController::class, 'arrondissement'])->name('arrondissement');
        Route::get('/village', [StatsController::class, 'village'])->name('village');
    });
    
    // ==========================================
    // ROUTES D'EXPORT CSV
    // ==========================================

    // Export National
    Route::get('/export/national/csv', [ExportController::class, 'nationalCsv'])
        ->name('export.national.csv');

    // Export Département
    Route::get('/export/departement/csv', [ExportController::class, 'departementCsv'])
        ->name('export.departement.csv');
    Route::get('/export/departement/communes/csv', [ExportController::class, 'departementCommunesCsv'])
        ->name('export.departement.communes.csv');

    // Export Circonscription
    Route::get('/export/circonscription/csv', [ExportController::class, 'circonscriptionCsv'])
        ->name('export.circonscription.csv');
    Route::get('/export/circonscription/communes/csv', [ExportController::class, 'circonscriptionCommunesCsv'])
        ->name('export.circonscription.communes.csv');

    // Export Commune
    Route::get('/export/commune/csv', [ExportController::class, 'communeCsv'])
        ->name('export.commune.csv');
    Route::get('/export/commune/arrondissements/csv', [ExportController::class, 'communeArrondissementsCsv'])
        ->name('export.commune.arrondissements.csv');

    // Export Arrondissement
    Route::get('/export/arrondissement/csv', [ExportController::class, 'arrondissementCsv'])
        ->name('export.arrondissement.csv');

    // Export Village (ancienne route conservée pour compatibilité)
    Route::get('/export/village/csv', [ExportController::class, 'villageCsv'])
        ->name('export.village.csv');
    Route::get('/export/village/postes/csv', [ExportController::class, 'villagePostesCsv'])
        ->name('export.village.postes.csv');

    // ==========================================
    // NOUVEAUX EXPORTS VILLAGES (Vue Globale)
    // ==========================================

    // Export des villages saisis avec résultats
    Route::get('/export/village/saisis/csv', [ExportController::class, 'villageSaisisCsv'])
        ->name('export.village.saisis.csv');

    // Export des villages non saisis
    Route::get('/export/village/non-saisis/csv', [ExportController::class, 'villageNonSaisisCsv'])
        ->name('export.village.non-saisis.csv');

    // ==========================================
    // ROUTES RÉSULTATS (Législatives)
    // ==========================================

    // Page principale des résultats
    Route::get('/resultats', [ResultatsController::class, 'index'])
        ->name('resultats');
    
    // Vérifier l'éligibilité (AJAX)
    Route::post('/resultats/verifier-eligibilite', [ResultatsController::class, 'verifierEligibilite'])
        ->name('resultats.verifier-eligibilite');
    
    // Compiler les résultats (AJAX)
    Route::post('/resultats/compiler', [ResultatsController::class, 'compiler'])
        ->name('resultats.compiler');
    
    // Export CSV de la matrice complète
    Route::get('/resultats/export/matrice-csv', [ResultatsController::class, 'exportMatriceCSV'])
        ->name('resultats.export.matrice.csv');
    
    // Export CSV des détails par circonscription
    Route::get('/resultats/export/details-csv', [ResultatsController::class, 'exportDetailsCSV'])
        ->name('resultats.export.details.csv');
    
    // Export CSV des sièges
    Route::get('/resultats/export/sieges-csv', [ResultatsController::class, 'exportSiegesCsv'])
        ->name('resultats.export.sieges.csv');
    
    // Résumé des résultats (API JSON)
    Route::get('/resultats/resume', [ResultatsController::class, 'resume'])
        ->name('resultats.resume');
    
    // Réinitialiser le cache
    Route::post('/resultats/reinitialiser-cache', [ResultatsController::class, 'reinitialiserCache'])
        ->name('resultats.reinitialiser-cache');
    
    // Détails par circonscription
    Route::get('/resultats/circonscription/{id}', [ResultatsController::class, 'detailsCirconscription'])
        ->name('resultats.circonscription');

    // ==========================================
    // EXPORTS COMMUNALES
    // ==========================================

    // Export matrice communales
    Route::get('/export/communales/matrice', [ExportController::class, 'communalesMatriceCsv'])
        ->name('export.communales.matrice');

    // Export sièges communales
    Route::get('/export/communales/sieges', [ExportController::class, 'communalesSiegesCsv'])
        ->name('export.communales.sieges');

    // Export détails communes
    Route::get('/export/communales/details', [ExportController::class, 'communalesDetailsCsv'])
        ->name('export.communales.details');

    // Export détails arrondissements
    Route::get('/export/communales/arrondissements', [ExportController::class, 'communalesArrondissementsCsv'])
        ->name('export.communales.arrondissements');

    // Export complet ZIP
    Route::get('/export/communales/complet', [ExportController::class, 'communalesExportComplet'])
        ->name('export.communales.complet');

        Route::get('/parametres', [ParametresController::class, 'index'])
    ->name('parametres.index');
Route::get('/parametres/top-utilisateurs', [ParametresController::class, 'topUtilisateurs']);
Route::get('/parametres/rechercher-pv', [ParametresController::class, 'rechercherPv']);
Route::get('/parametres/pv/{id}', [ParametresController::class, 'detailsPv']);
Route::post('/parametres/pv/{id}/annuler', [ParametresController::class, 'annulerPv']);
Route::delete('/parametres/pv/{id}/supprimer', [ParametresController::class, 'supprimerPv']);
Route::get('/parametres/utilisateurs', [ParametresController::class, 'utilisateurs']);

Route::prefix('rapports')->name('rapports.')->group(function () {
    Route::get('/communales', [RapportCommunalesController::class, 'index'])
        ->name('communales');

    Route::get('/communales/pdf', [RapportCommunalesController::class, 'pdf'])
        ->name('communales.pdf');
});

});

require __DIR__.'/auth.php';