@extends('layouts.admin')

@section('title', 'Paramètres et Administration')

@section('breadcrumb')
    <span class="text-gray-400">Administration</span>
    <i class="fas fa-chevron-right text-xs text-gray-400"></i>
    <span class="text-gray-900 font-semibold">Paramètres</span>
@endsection

@section('content')
<div class="space-y-6" x-data="parametresApp()">

    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Paramètres et Administration</h1>
            <p class="text-gray-600 mt-1">
                {{ $election->nom ?? 'Élection' }}
                @if(!empty($election->date_scrutin))
                    - {{ \Carbon\Carbon::parse($election->date_scrutin)->format('d/m/Y') }}
                @endif
            </p>
        </div>
    </div>

    <!-- Onglets -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex" aria-label="Tabs">
                <button @click="tab = 'stats'"
                        :class="tab === 'stats' ? 'border-benin-green-500 text-benin-green-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="flex-1 py-4 px-1 text-center border-b-2 font-medium text-sm">
                    <i class="fas fa-chart-bar mr-2"></i>
                    Statistiques Saisie
                </button>
                <button @click="tab = 'recherche'"
                        :class="tab === 'recherche' ? 'border-benin-green-500 text-benin-green-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="flex-1 py-4 px-1 text-center border-b-2 font-medium text-sm">
                    <i class="fas fa-search mr-2"></i>
                    Rechercher PV
                </button>
                <button @click="tab = 'utilisateurs'"
                        :class="tab === 'utilisateurs' ? 'border-benin-green-500 text-benin-green-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="flex-1 py-4 px-1 text-center border-b-2 font-medium text-sm">
                    <i class="fas fa-users mr-2"></i>
                    Utilisateurs
                </button>
            </nav>
        </div>

        <!-- ONGLET 1 : Statistiques Saisie -->
        <div x-show="tab === 'stats'" class="p-6">
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-trophy text-benin-yellow-600 mr-2"></i>
                        TOP 20 Utilisateurs - Qui saisit le plus
                    </h3>
                    <button @click="chargerTopUtilisateurs()"
                            class="px-4 py-2 bg-benin-green-600 text-white rounded-lg hover:bg-benin-green-700">
                        <i class="fas fa-sync-alt mr-2"></i>
                        Actualiser
                    </button>
                </div>

                <!-- Graphique -->
                <div class="bg-gray-50 rounded-lg p-4" style="height: 400px;">
                    <canvas id="topUsersChart"></canvas>
                </div>

                <!-- Tableau -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rang</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Utilisateur</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">PV Saisis</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Validés</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Publiés</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Période</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template x-for="(user, index) in topUsers" :key="user.saisi_par_user_id">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-white font-bold"
                                              :class="{
                                                  'bg-benin-yellow-500': index === 0,
                                                  'bg-gray-400': index === 1,
                                                  'bg-orange-600': index === 2,
                                                  'bg-gray-300': index > 2
                                              }">
                                            <span x-text="index + 1"></span>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-medium text-gray-900" x-text="user.user_nom"></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600" x-text="user.user_email"></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-2xl font-bold text-benin-green-600" x-text="user.nb_pv_saisis"></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600" x-text="user.nb_pv_valides"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600" x-text="user.nb_pv_publies"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">
                                        <div x-text="formatDate(user.premiere_saisie)"></div>
                                        <div x-text="'→ ' + formatDate(user.derniere_saisie)"></div>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="topUsers.length === 0">
                                <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">Aucune donnée</td></tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ONGLET 2 : Recherche PV -->
        <div x-show="tab === 'recherche'" class="p-6" style="display: none;">
            <div class="space-y-6">
                <h3 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-search text-benin-green-600 mr-2"></i>
                    Rechercher un Procès-Verbal
                </h3>

                <!-- Formulaire de recherche -->
                <div class="bg-gray-50 rounded-lg p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Type de recherche</label>
                            <select x-model="rechercheType" class="w-full rounded-lg border-gray-300">
                                <option value="code">Par Code PV</option>
                                <option value="village">Par Village/Quartier</option>
                                <option value="arrondissement">Par Arrondissement</option>
                                <option value="commune">Par Commune</option>
                                <option value="departement">Par Département</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Valeur</label>
                            <input type="text" 
                                   x-model="rechercheValeur" 
                                   @keyup.enter="rechercherPV()"
                                   placeholder="Tapez pour rechercher..."
                                   class="w-full rounded-lg border-gray-300">
                        </div>
                    </div>

                    <button @click="rechercherPV()"
                            :disabled="!rechercheValeur"
                            class="w-full px-6 py-3 bg-benin-green-600 text-white rounded-lg hover:bg-benin-green-700 disabled:bg-gray-300 disabled:cursor-not-allowed">
                        <i class="fas fa-search mr-2"></i>
                        Rechercher
                    </button>
                </div>

                <!-- Résultats -->
                <div x-show="rechercheResultats.length > 0">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="font-semibold text-gray-900">
                            <span x-text="rechercheResultats.length"></span> résultat(s) trouvé(s)
                        </h4>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Saisi par</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <template x-for="pv in rechercheResultats" :key="pv.id">
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="font-mono text-sm text-gray-900" x-text="pv.code"></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full"
                                                  :class="{
                                                      'bg-benin-green-100 text-benin-green-800': pv.statut === 'valide',
                                                      'bg-blue-100 text-blue-800': pv.statut === 'publie',
                                                      'bg-benin-red-100 text-benin-red-800': pv.statut === 'annule',
                                                      'bg-gray-100 text-gray-800': pv.statut === 'brouillon'
                                                  }"
                                                  x-text="pv.statut"></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600" x-text="pv.saisi_par || '-'"></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600" x-text="formatDate(pv.created_at)"></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <div class="flex items-center gap-2">
                                                <button @click="voirDetailsPV(pv.id)"
                                                        class="text-blue-600 hover:text-blue-800">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button @click="annulerPV(pv.id)"
                                                        x-show="pv.statut !== 'annule'"
                                                        class="text-benin-yellow-600 hover:text-benin-yellow-800">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                                <button @click="supprimerPV(pv.id)"
                                                        class="text-benin-red-600 hover:text-benin-red-800">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Message si aucun résultat -->
                <div x-show="rechercheEffectuee && rechercheResultats.length === 0" 
                     class="text-center py-12 text-gray-500">
                    <i class="fas fa-search text-4xl mb-4"></i>
                    <p>Aucun PV trouvé</p>
                </div>
            </div>
        </div>

        <!-- ONGLET 3 : Utilisateurs -->
        <div x-show="tab === 'utilisateurs'" class="p-6" style="display: none;">
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-users text-benin-green-600 mr-2"></i>
                        Liste des Utilisateurs
                    </h3>
                    <button @click="chargerUtilisateurs()"
                            class="px-4 py-2 bg-benin-green-600 text-white rounded-lg hover:bg-benin-green-700">
                        <i class="fas fa-sync-alt mr-2"></i>
                        Actualiser
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nom</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rôle</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">PV Saisis</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Inscrit le</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template x-for="user in utilisateurs" :key="user.id">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-medium text-gray-900" x-text="user.nom + ' ' + user.prenom"></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600" x-text="user.email"></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800"
                                              x-text="user.role"></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-benin-green-600" 
                                        x-text="user.nb_pv_saisis"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600" x-text="formatDate(user.created_at)"></td>
                                </tr>
                            </template>
                            <template x-if="utilisateurs.length === 0">
                                <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">Aucun utilisateur</td></tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function parametresApp() {
    return {
        tab: '{{ $tab }}',
        topUsers: [],
        topUsersChart: null,
        rechercheType: 'code',
        rechercheValeur: '',
        rechercheResultats: [],
        rechercheEffectuee: false,
        utilisateurs: [],

        init() {
            this.chargerTopUtilisateurs();
        },

        async chargerTopUtilisateurs() {
            try {
                const response = await fetch('/parametres/top-utilisateurs');
                const data = await response.json();
                if (data.success) {
                    this.topUsers = data.data;
                    this.$nextTick(() => this.creerGraphique());
                }
            } catch (error) {
                console.error('Erreur:', error);
                await showError('Erreur lors du chargement des données');
            }
        },

        creerGraphique() {
            const ctx = document.getElementById('topUsersChart');
            if (!ctx) return;

            if (this.topUsersChart) {
                this.topUsersChart.destroy();
            }

            const labels = this.topUsers.map(u => u.user_nom);
            const data = this.topUsers.map(u => parseInt(u.nb_pv_saisis));

            this.topUsersChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Nombre de PV saisis',
                        data: data,
                        backgroundColor: '#008751',
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        },

        async rechercherPV() {
            if (!this.rechercheValeur) return;

            try {
                const response = await fetch(`/parametres/rechercher-pv?type=${this.rechercheType}&valeur=${encodeURIComponent(this.rechercheValeur)}`);
                const data = await response.json();
                if (data.success) {
                    this.rechercheResultats = data.data;
                    this.rechercheEffectuee = true;
                }
            } catch (error) {
                console.error('Erreur:', error);
                await showError('Erreur lors de la recherche');
            }
        },

        async voirDetailsPV(id) {
            try {
                const response = await fetch(`/parametres/pv/${id}`);
                const data = await response.json();
                if (data.success) {
                    // Afficher les détails dans une belle modale
                    await showInfo(
                        `<strong>Code:</strong> ${data.pv.code}<br>
                         <strong>Statut:</strong> ${data.pv.statut}<br>
                         <strong>Saisi par:</strong> ${data.pv.saisi_par_nom || '-'}<br>
                         <strong>Nombre de lignes:</strong> ${data.lignes.length}`,
                        'Détails du PV'
                    );
                }
            } catch (error) {
                console.error('Erreur:', error);
                await showError('Erreur lors du chargement des détails');
            }
        },

        async annulerPV(id) {
            try {
                const motif = await customConfirm(
                    'Êtes-vous sûr de vouloir annuler ce PV ?<br><small class="text-gray-500">Le statut sera changé en "annulé"</small>',
                    'Annuler le PV',
                    {
                        variant: 'warning',
                        confirmText: 'Annuler le PV',
                        input: true,
                        inputLabel: 'Motif de l\'annulation (optionnel)',
                        inputPlaceholder: 'Expliquez pourquoi vous annulez ce PV...'
                    }
                );

                const response = await fetch(`/parametres/pv/${id}/annuler`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ motif })
                });

                const data = await response.json();
                if (data.success) {
                    await showSuccess('PV annulé avec succès');
                    this.rechercherPV(); // Rafraîchir les résultats
                } else {
                    await showError(data.message || 'Erreur lors de l\'annulation');
                }
            } catch (error) {
                // Utilisateur a annulé la confirmation
                console.log('Annulation abandonnée');
            }
        },

        async supprimerPV(id) {
            try {
                // Première confirmation
                await customConfirm(
                    '⚠️ <strong>ATTENTION !</strong><br><br>Vous êtes sur le point de <strong class="text-benin-red-600">SUPPRIMER DÉFINITIVEMENT</strong> ce PV.<br><br>Cette action est <strong class="text-benin-red-600">IRRÉVERSIBLE</strong> et entraînera la perte totale des données.<br><br>Il est recommandé d\'<strong>annuler</strong> plutôt que de supprimer.',
                    'Suppression Définitive',
                    {
                        variant: 'danger',
                        confirmText: 'Continuer quand même'
                    }
                );

                // Deuxième confirmation
                await customConfirm(
                    '🔥 <strong>DERNIÈRE CONFIRMATION</strong><br><br>Vous avez bien compris que cette suppression est <strong class="text-benin-red-600">DÉFINITIVE</strong> et <strong class="text-benin-red-600">IRRÉVERSIBLE</strong> ?<br><br>Toutes les données du PV seront perdues à jamais.',
                    'Confirmer la Suppression',
                    {
                        variant: 'danger',
                        confirmText: 'Oui, SUPPRIMER'
                    }
                );

                const response = await fetch(`/parametres/pv/${id}/supprimer`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                const data = await response.json();
                if (data.success) {
                    await showSuccess('PV supprimé définitivement');
                    this.rechercherPV(); // Rafraîchir les résultats
                } else {
                    await showError(data.message || 'Erreur lors de la suppression');
                }
            } catch (error) {
                // Utilisateur a annulé
                console.log('Suppression abandonnée');
            }
        },

        async chargerUtilisateurs() {
            try {
                const response = await fetch('/parametres/utilisateurs');
                const data = await response.json();
                if (data.success) {
                    this.utilisateurs = data.data;
                }
            } catch (error) {
                console.error('Erreur:', error);
                await showError('Erreur lors du chargement des utilisateurs');
            }
        },

        formatDate(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return date.toLocaleDateString('fr-FR', { 
                year: 'numeric', 
                month: '2-digit', 
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    }
}
</script>
@endpush