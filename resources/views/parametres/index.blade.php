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
                <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">
                            <i class="fas fa-search text-benin-green-600 mr-2"></i>
                            Recherche et suppression de PV
                        </h3>
                        <p class="text-sm text-gray-600 mt-1">
                            Utilisez des critères ciblés, vérifiez le contexte territorial, puis annulez ou supprimez en sécurité.
                        </p>
                    </div>
                    <button @click="resetRecherche()"
                            class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm">
                        <i class="fas fa-eraser mr-2"></i>
                        Réinitialiser la recherche
                    </button>
                </div>

                <div class="bg-gray-50 rounded-xl p-6 border border-gray-200 space-y-4">
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Type de recherche</label>
                            <select x-model="rechercheType" class="w-full rounded-lg border-gray-300">
                                <option value="code">Code PV / Numéro PV</option>
                                <option value="village">Village / Quartier</option>
                                <option value="arrondissement">Arrondissement</option>
                                <option value="commune">Commune</option>
                                <option value="departement">Département</option>
                            </select>
                        </div>

                        <div class="lg:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Valeur à chercher</label>
                            <input type="text"
                                   x-model="rechercheValeur"
                                   x-ref="rechercheInput"
                                   @keyup.enter="rechercherPV()"
                                   :placeholder="placeholderRecherche()"
                                   class="w-full rounded-lg border-gray-300">
                            <p class="text-xs text-gray-500 mt-1" x-text="hintRecherche()"></p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                            <select x-model="rechercheStatut" class="w-full rounded-lg border-gray-300">
                                <option value="all">Tous les statuts</option>
                                <option value="publie">Publié</option>
                                <option value="valide">Validé</option>
                                <option value="brouillon">Brouillon</option>
                                <option value="litigieux">Litigieux</option>
                                <option value="annule">Annulé</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Volume max</label>
                            <select x-model.number="rechercheLimit" class="w-full rounded-lg border-gray-300">
                                <option :value="25">25 résultats</option>
                                <option :value="50">50 résultats</option>
                                <option :value="100">100 résultats</option>
                                <option :value="200">200 résultats</option>
                            </select>
                        </div>
                        <div class="md:col-span-2 flex items-end">
                            <button @click="rechercherPV()"
                                    :disabled="!canSearch() || rechercheLoading"
                                    class="w-full px-6 py-3 bg-benin-green-600 text-white rounded-lg hover:bg-benin-green-700 disabled:bg-gray-300 disabled:cursor-not-allowed">
                                <template x-if="!rechercheLoading">
                                    <span><i class="fas fa-search mr-2"></i>Lancer la recherche</span>
                                </template>
                                <template x-if="rechercheLoading">
                                    <span><i class="fas fa-spinner fa-spin mr-2"></i>Recherche en cours...</span>
                                </template>
                            </button>
                        </div>
                    </div>
                </div>

                <div x-show="rechercheEffectuee && rechercheMeta.total_matching > 0" class="bg-white rounded-xl border border-gray-200 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="text-sm text-gray-700">
                            <strong x-text="rechercheMeta.total_matching || 0"></strong> PV correspondent au filtre.
                            <span class="text-gray-500">(affichés: <span x-text="rechercheResultats.length"></span>)</span>
                        </div>
                        <div class="flex flex-wrap gap-2 text-xs">
                            <template x-for="badge in statusBadges()" :key="badge.key">
                                <span class="px-2.5 py-1 rounded-full font-semibold"
                                      :class="badge.className"
                                      x-text="badge.label + ' : ' + badge.value"></span>
                            </template>
                        </div>
                    </div>
                </div>

                <div x-show="rechercheResultats.length > 0" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 bg-white rounded-xl overflow-hidden">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">PV</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Localisation</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Saisie</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Maj</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template x-for="pv in rechercheResultats" :key="pv.id">
                                <tr class="hover:bg-gray-50 align-top">
                                    <td class="px-4 py-3">
                                        <div class="font-mono text-sm font-semibold text-gray-900" x-text="pv.code || '-'"></div>
                                        <div class="text-xs text-gray-500 mt-1">N° PV: <span x-text="pv.numero_pv || '-'"></span></div>
                                        <div class="text-xs text-gray-500 mt-1">Lignes: <span x-text="Number(pv.nb_lignes || 0).toLocaleString()"></span></div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full"
                                              :class="statusClass(pv.statut)"
                                              x-text="pv.statut"></span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700">
                                        <div><strong x-text="pv.departement_nom || '-'"></strong></div>
                                        <div class="text-xs text-gray-500 mt-1" x-text="(pv.commune_nom || '-') + ' / ' + (pv.arrondissement_nom || '-')"></div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700">
                                        <div x-text="pv.saisi_par || '-'"></div>
                                        <div class="text-xs text-gray-500 mt-1" x-text="formatDate(pv.created_at)"></div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700" x-text="formatDate(pv.updated_at)"></td>
                                    <td class="px-4 py-3 text-sm">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <button @click="voirDetailsPV(pv.id)"
                                                    class="inline-flex items-center px-2.5 py-1.5 rounded-md bg-blue-50 text-blue-700 hover:bg-blue-100">
                                                <i class="fas fa-eye mr-1"></i>Détails
                                            </button>
                                            <button @click="annulerPV(pv.id)"
                                                    x-show="pv.statut !== 'annule'"
                                                    class="inline-flex items-center px-2.5 py-1.5 rounded-md bg-amber-50 text-amber-700 hover:bg-amber-100">
                                                <i class="fas fa-ban mr-1"></i>Annuler
                                            </button>
                                            <button @click="supprimerPV(pv.id)"
                                                    class="inline-flex items-center px-2.5 py-1.5 rounded-md bg-red-50 text-red-700 hover:bg-red-100">
                                                <i class="fas fa-trash mr-1"></i>Supprimer
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div x-show="rechercheEffectuee && !rechercheLoading && rechercheResultats.length === 0"
                     class="text-center py-12 text-gray-500 bg-white rounded-xl border border-gray-200">
                    <i class="fas fa-search text-4xl mb-4"></i>
                    <p class="font-semibold">Aucun PV trouvé</p>
                    <p class="text-sm mt-1">Essayez un autre type de recherche, un autre terme ou un statut différent.</p>
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
        rechercheStatut: 'all',
        rechercheLimit: 50,
        rechercheResultats: [],
        rechercheEffectuee: false,
        rechercheLoading: false,
        rechercheMeta: {
            total_matching: 0,
            counts_by_status: {}
        },

        utilisateurs: [],

        init() {
            this.chargerTopUtilisateurs();

            this.$watch('tab', (value) => {
                if (value === 'utilisateurs' && this.utilisateurs.length === 0) {
                    this.chargerUtilisateurs();
                }
                if (value === 'recherche') {
                    this.$nextTick(() => this.focusRechercheInput());
                }
            });

            if (this.tab === 'utilisateurs') {
                this.chargerUtilisateurs();
            }
            if (this.tab === 'recherche') {
                this.$nextTick(() => this.focusRechercheInput());
            }
        },

        focusRechercheInput() {
            if (this.$refs.rechercheInput) {
                this.$refs.rechercheInput.focus();
            }
        },

        canSearch() {
            return this.rechercheValeur.trim().length > 0;
        },

        placeholderRecherche() {
            const map = {
                code: 'Ex: PV-ARR-0012, 75033-01 ou un fragment...',
                village: 'Ex: ARBONGA, Zongo, Gbèdjromédé...',
                arrondissement: 'Ex: Banikoara, Godomey, Djidja...',
                commune: 'Ex: Cotonou, Porto-Novo, Djougou...',
                departement: 'Ex: Atlantique, Alibori, Diaspora...'
            };
            return map[this.rechercheType] || 'Tapez votre recherche...';
        },

        hintRecherche() {
            const map = {
                code: 'Recherche par code PV ou numéro PV (exact ou partiel).',
                village: 'Recherche sur les villages/quartiers présents dans les lignes du PV.',
                arrondissement: 'Recherche sur le nom de l’arrondissement de rattachement.',
                commune: 'Recherche sur le nom de la commune du PV.',
                departement: 'Recherche sur le nom du département du PV.'
            };
            return map[this.rechercheType] || '';
        },

        statusClass(statut) {
            const map = {
                valide: 'bg-benin-green-100 text-benin-green-800',
                publie: 'bg-blue-100 text-blue-800',
                annule: 'bg-benin-red-100 text-benin-red-800',
                brouillon: 'bg-gray-100 text-gray-800',
                litigieux: 'bg-orange-100 text-orange-800'
            };
            return map[statut] || 'bg-gray-100 text-gray-800';
        },

        statusBadges() {
            const counts = this.rechercheMeta?.counts_by_status || {};
            const defs = [
                { key: 'publie', label: 'Publié', className: 'bg-blue-100 text-blue-800' },
                { key: 'valide', label: 'Validé', className: 'bg-benin-green-100 text-benin-green-800' },
                { key: 'brouillon', label: 'Brouillon', className: 'bg-gray-100 text-gray-800' },
                { key: 'litigieux', label: 'Litigieux', className: 'bg-orange-100 text-orange-800' },
                { key: 'annule', label: 'Annulé', className: 'bg-red-100 text-red-800' }
            ];

            return defs
                .map((d) => ({ ...d, value: Number(counts[d.key] || 0) }))
                .filter((d) => d.value > 0);
        },

        resetRecherche() {
            this.rechercheType = 'code';
            this.rechercheValeur = '';
            this.rechercheStatut = 'all';
            this.rechercheLimit = 50;
            this.rechercheResultats = [];
            this.rechercheEffectuee = false;
            this.rechercheLoading = false;
            this.rechercheMeta = { total_matching: 0, counts_by_status: {} };
            this.$nextTick(() => this.focusRechercheInput());
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
            if (!this.canSearch()) return;

            this.rechercheLoading = true;
            this.rechercheEffectuee = true;

            try {
                const params = new URLSearchParams({
                    type: this.rechercheType,
                    valeur: this.rechercheValeur.trim(),
                    statut: this.rechercheStatut,
                    limit: String(this.rechercheLimit)
                });

                const response = await fetch(`/parametres/rechercher-pv?${params.toString()}`);
                const data = await response.json();

                if (data.success) {
                    this.rechercheResultats = data.data || [];
                    this.rechercheMeta = data.meta || { total_matching: this.rechercheResultats.length, counts_by_status: {} };
                } else {
                    this.rechercheResultats = [];
                    this.rechercheMeta = { total_matching: 0, counts_by_status: {} };
                    await showError(data.message || 'Erreur lors de la recherche');
                }
            } catch (error) {
                console.error('Erreur:', error);
                this.rechercheResultats = [];
                this.rechercheMeta = { total_matching: 0, counts_by_status: {} };
                await showError('Erreur lors de la recherche');
            } finally {
                this.rechercheLoading = false;
            }
        },

        getPvFromList(id) {
            return this.rechercheResultats.find((pv) => Number(pv.id) === Number(id)) || null;
        },

        async fetchPvDetails(id) {
            const response = await fetch(`/parametres/pv/${id}`);
            const data = await response.json();
            if (!data.success) {
                throw new Error(data.message || 'PV introuvable');
            }
            return data;
        },

        async voirDetailsPV(id) {
            try {
                const data = await this.fetchPvDetails(id);
                const pv = data.pv || {};
                const resume = data.resume || {};
                const lignes = data.lignes || [];
                const previewVillages = lignes
                    .slice(0, 5)
                    .map((l) => l.village_nom || 'Village N/A')
                    .join(', ');

                await showInfo(
                    `<div class="text-left space-y-1">
                        <p><strong>Code:</strong> ${pv.code || '-'}</p>
                        <p><strong>Numéro PV:</strong> ${pv.numero_pv || '-'}</p>
                        <p><strong>Statut:</strong> ${pv.statut || '-'}</p>
                        <p><strong>Département:</strong> ${pv.departement_nom || '-'}</p>
                        <p><strong>Commune:</strong> ${pv.commune_nom || '-'}</p>
                        <p><strong>Arrondissement:</strong> ${pv.arrondissement_nom || '-'}</p>
                        <p><strong>Saisi par:</strong> ${pv.saisi_par_nom || '-'}</p>
                        <p><strong>Lignes:</strong> ${Number(resume.nb_lignes || lignes.length || 0).toLocaleString()}</p>
                        <p><strong>Résultats:</strong> ${Number(resume.nb_resultats || 0).toLocaleString()}</p>
                        <p><strong>Total voix:</strong> ${Number(resume.total_voix || 0).toLocaleString()}</p>
                        <p><strong>Bulletins nuls:</strong> ${Number(resume.bulletins_nuls || 0).toLocaleString()}</p>
                        <p><strong>Extrait villages:</strong> ${previewVillages || '-'}</p>
                    </div>`,
                    'Détails du PV'
                );
            } catch (error) {
                console.error('Erreur:', error);
                await showError(error.message || 'Erreur lors du chargement des détails');
            }
        },

        async annulerPV(id) {
            try {
                const pv = this.getPvFromList(id);
                const code = pv?.code || `ID ${id}`;
                const motif = await customConfirm(
                    `Vous allez annuler le PV <strong>${code}</strong>.<br><small class="text-gray-500">Le statut passera à "annulé".</small>`,
                    'Annuler le PV',
                    {
                        variant: 'warning',
                        confirmText: 'Confirmer l’annulation',
                        input: true,
                        inputLabel: 'Motif de l’annulation (optionnel)',
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
                    this.rechercherPV();
                } else {
                    await showError(data.message || 'Erreur lors de l’annulation');
                }
            } catch (error) {
                if (error) {
                    console.log('Annulation abandonnée');
                }
            }
        },

        normalizeCode(value) {
            return String(value || '').trim().toUpperCase();
        },

        async supprimerPV(id) {
            try {
                const details = await this.fetchPvDetails(id);
                const pv = details.pv || {};
                const resume = details.resume || {};
                const code = pv.code || '';

                const confirmationCode = await customConfirm(
                    `Vous allez supprimer définitivement le PV <strong>${code || ('ID ' + id)}</strong>.<br><br>
                     <span class="text-benin-red-700 font-semibold">Action irréversible</span>.<br>
                     Lignes: <strong>${Number(resume.nb_lignes || 0).toLocaleString()}</strong> - Résultats: <strong>${Number(resume.nb_resultats || 0).toLocaleString()}</strong>.<br><br>
                     Saisissez le code exact du PV pour confirmer.`,
                    'Suppression sécurisée',
                    {
                        variant: 'danger',
                        confirmText: 'Valider le code',
                        input: true,
                        inputLabel: 'Code de confirmation',
                        inputPlaceholder: code || 'Code PV'
                    }
                );

                if (this.normalizeCode(confirmationCode) !== this.normalizeCode(code)) {
                    await showError('Le code saisi ne correspond pas au code du PV. Suppression annulée.');
                    return;
                }

                const motif = await customConfirm(
                    'Dernière étape: ajoutez un motif (optionnel), puis confirmez la suppression définitive.',
                    'Motif de suppression',
                    {
                        variant: 'danger',
                        confirmText: 'Supprimer définitivement',
                        input: true,
                        inputLabel: 'Motif (optionnel)',
                        inputPlaceholder: 'Ex: doublon, erreur de saisie irréparable...'
                    }
                );

                const response = await fetch(`/parametres/pv/${id}/supprimer`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        confirmation_code: confirmationCode,
                        motif: motif || ''
                    })
                });

                const data = await response.json();
                if (data.success) {
                    await showSuccess('PV supprimé définitivement');
                    this.rechercherPV();
                } else {
                    await showError(data.message || 'Erreur lors de la suppression');
                }
            } catch (error) {
                if (error?.message) {
                    console.error('Erreur:', error);
                    await showError(error.message);
                } else {
                    console.log('Suppression abandonnée');
                }
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

