@extends('layouts.admin')

@section('title', 'Statistiques Globales par Village')

@section('breadcrumb')
    <span class="text-gray-400">Statistiques</span>
    <i class="fas fa-chevron-right text-xs text-gray-400"></i>
    <span class="text-gray-900 font-semibold">Villages Globaux</span>
@endsection

@section('content')
<div class="space-y-6" x-data="{
    q:'',
    tab: 'saisis',
    rowsSaisis: @js($stats['villages_avec_voix'] ?? []),
    rowsNonSaisis: @js($stats['villages_non_saisis'] ?? []),
    get filteredSaisis(){
        const s=(this.q||'').toLowerCase().trim();
        if(!s) return this.rowsSaisis;
        return this.rowsSaisis.filter(r => 
            (r.village_quartier_nom||'').toLowerCase().includes(s) ||
            (r.arrondissement_nom||'').toLowerCase().includes(s) ||
            (r.commune_nom||'').toLowerCase().includes(s) ||
            (r.departement_nom||'').toLowerCase().includes(s)
        );
    },
    get filteredNonSaisis(){
        const s=(this.q||'').toLowerCase().trim();
        if(!s) return this.rowsNonSaisis;
        return this.rowsNonSaisis.filter(r => 
            (r.village_quartier_nom||'').toLowerCase().includes(s) ||
            (r.arrondissement_nom||'').toLowerCase().includes(s) ||
            (r.commune_nom||'').toLowerCase().includes(s) ||
            (r.departement_nom||'').toLowerCase().includes(s)
        );
    }
}">

    <!-- Page Header + Sélecteurs -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Statistiques Globales par Village</h1>
            <p class="text-gray-600 mt-1">
                {{ $stats['election']->nom ?? 'Élection' }}
                @if(!empty($stats['election']) && !empty($stats['election']->date_scrutin))
                    - {{ \Carbon\Carbon::parse($stats['election']->date_scrutin)->format('d/m/Y') }}
                @endif
            </p>
        </div>

        <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
            {{-- SÉLECTEUR D'ÉLECTION --}}
            <form method="GET" action="{{ route('stats.village') }}" class="inline-flex items-center gap-2">
                <label class="text-sm font-medium text-gray-700">Élection</label>
                <select name="election_id" 
                        onchange="this.form.submit()"
                        class="px-4 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 font-medium">
                    @php
                        $elections = DB::table('elections')->orderBy('date_scrutin', 'desc')->get();
                        $currentElectionId = $stats['election']->id ?? null;
                    @endphp
                    @foreach($elections as $elec)
                        <option value="{{ $elec->id }}" {{ $currentElectionId == $elec->id ? 'selected' : '' }}>
                            {{ $elec->nom }}
                        </option>
                    @endforeach
                </select>
            </form>

            <div class="flex items-center gap-2">
                <button onclick="window.print()"
                        class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 flex items-center space-x-2">
                    <i class="fas fa-print"></i><span>Imprimer</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Info Box -->
    <div class="bg-benin-green-50 border-l-4 border-benin-green-500 p-4 rounded-lg">
        <div class="flex items-start">
            <i class="fas fa-info-circle text-benin-green-600 mt-1 mr-3"></i>
            <div>
                <h3 class="font-semibold text-benin-green-900 mb-1">📌 Vue Globale des Villages</h3>
                <div class="text-sm text-benin-green-800 space-y-1">
                    <p><strong>Villages inscrits :</strong> Total des villages hors diaspora</p>
                    <p><strong>Villages saisis :</strong> Villages présents dans au moins un PV validé</p>
                    <p><strong>Taux de couverture :</strong> Pourcentage de villages saisis par rapport aux villages inscrits</p>
                </div>
            </div>
        </div>
    </div>

    <!-- KPIs Globaux -->
    <div class="flex gap-6">
        <!-- Villages Inscrits -->
        <div class="flex-1 bg-white rounded-xl shadow-sm p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Villages Inscrits</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($stats['nombre_villages_inscrits'] ?? 0) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Hors diaspora</p>
                </div>
                <div class="bg-blue-100 rounded-full p-4">
                    <i class="fas fa-map-marked-alt text-blue-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Villages Saisis -->
        <div class="flex-1 bg-white rounded-xl shadow-sm p-6 border-l-4 border-benin-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Villages Saisis</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($stats['nombre_villages_saisis'] ?? 0) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Dans les PV validés</p>
                </div>
                <div class="bg-benin-green-100 rounded-full p-4">
                    <i class="fas fa-check-circle text-benin-green-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Taux de Couverture -->
        <div class="flex-1 bg-white rounded-xl shadow-sm p-6 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Taux de Couverture</p>
                    @php
                        $inscrits = $stats['nombre_villages_inscrits'] ?? 1;
                        $saisis = $stats['nombre_villages_saisis'] ?? 0;
                        $taux = $inscrits > 0 ? ($saisis / $inscrits) * 100 : 0;
                    @endphp
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($taux, 2) }}%</p>
                    <p class="text-xs text-gray-500 mt-1">Villages saisis / inscrits</p>
                </div>
                <div class="bg-purple-100 rounded-full p-4">
                    <i class="fas fa-chart-pie text-purple-600 text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Barre de Progression -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-lg font-semibold text-gray-900">Progression de la Saisie</h3>
            <span class="text-sm font-medium text-gray-600">
                {{ number_format($stats['nombre_villages_saisis'] ?? 0) }} / {{ number_format($stats['nombre_villages_inscrits'] ?? 0) }}
            </span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-6">
            <div class="bg-benin-green-600 h-6 rounded-full flex items-center justify-center text-white text-sm font-bold shadow-lg"
                 style="width: {{ min($taux, 100) }}%">
                {{ number_format($taux, 1) }}%
            </div>
        </div>
    </div>

    <!-- Onglets -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex" aria-label="Tabs">
                <button @click="tab = 'saisis'"
                        :class="tab === 'saisis' ? 'border-benin-green-500 text-benin-green-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="w-1/2 py-4 px-1 text-center border-b-2 font-medium text-sm">
                    <i class="fas fa-check-circle mr-2"></i>
                    Villages Saisis ({{ number_format($stats['nombre_villages_saisis'] ?? 0) }})
                </button>
                <button @click="tab = 'non_saisis'"
                        :class="tab === 'non_saisis' ? 'border-benin-red-500 text-benin-red-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="w-1/2 py-4 px-1 text-center border-b-2 font-medium text-sm">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Villages Non Saisis ({{ count($stats['villages_non_saisis'] ?? []) }})
                </button>
            </nav>
        </div>

        <!-- Recherche -->
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center gap-3">
                <i class="fas fa-search text-gray-400"></i>
                <input type="text" x-model="q" 
                       class="flex-1 border-0 focus:ring-0 text-gray-900" 
                       placeholder="Rechercher un village, arrondissement, commune ou département...">
                <span class="text-sm text-gray-500" 
                      x-text="tab === 'saisis' ? (filteredSaisis.length + ' résultat(s)') : (filteredNonSaisis.length + ' résultat(s)')"></span>
            </div>
        </div>

        <!-- ONGLET : Villages Saisis -->
        <div x-show="tab === 'saisis'">
            <div class="p-6 border-b border-gray-200 bg-gray-50">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-list-ul text-benin-green-600 mr-2"></i>
                        Liste des Villages Saisis
                    </h3>
                    <a href="{{ route('export.village.saisis.csv', ['election_id' => $election->id]) }}"
                       class="px-4 py-2 bg-benin-green-600 text-white rounded-lg hover:bg-benin-green-700 flex items-center space-x-2">
                        <i class="fas fa-file-csv"></i>
                        <span>Exporter CSV</span>
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Département</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Commune</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Arrondissement</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Village/Quartier</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Résultats</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <template x-for="v in filteredSaisis" :key="v.village_quartier_id">
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-gray-600" x-text="v.departement_nom"></td>
                                <td class="px-6 py-4 text-sm text-gray-600" x-text="v.commune_nom"></td>
                                <td class="px-6 py-4 text-sm text-gray-600" x-text="v.arrondissement_nom"></td>
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900" x-text="v.village_quartier_nom"></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="space-y-1">
                                        <template x-for="(entite, idx) in (v.entites || [])" :key="idx">
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="font-medium text-gray-700" x-text="entite.entite_sigle"></span>
                                                <span class="text-benin-green-600 font-semibold" x-text="Number(entite.voix||0).toLocaleString()"></span>
                                            </div>
                                        </template>
                                        <template x-if="(v.bulletins_nuls || 0) > 0">
                                            <div class="flex items-center justify-between text-xs border-t pt-1">
                                                <span class="font-medium text-gray-500">Bulletins nuls</span>
                                                <span class="text-benin-red-600 font-semibold" x-text="Number(v.bulletins_nuls||0).toLocaleString()"></span>
                                            </div>
                                        </template>
                                        <div class="flex items-center justify-between text-xs font-bold border-t pt-1">
                                            <span class="text-gray-900">TOTAL</span>
                                            <span class="text-gray-900" x-text="Number((v.total_voix||0) + (v.bulletins_nuls||0)).toLocaleString()"></span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <template x-if="filteredSaisis.length === 0">
                            <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">Aucun village trouvé</td></tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ONGLET : Villages Non Saisis -->
        <div x-show="tab === 'non_saisis'" style="display: none;">
            <div class="p-6 border-b border-gray-200 bg-red-50">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-exclamation-triangle text-benin-red-600 mr-2"></i>
                        Liste des Villages Non Saisis
                    </h3>
                    <a href="{{ route('export.village.non-saisis.csv', ['election_id' => $election->id]) }}"
                       class="px-4 py-2 bg-benin-red-600 text-white rounded-lg hover:bg-benin-red-700 flex items-center space-x-2">
                        <i class="fas fa-file-csv"></i>
                        <span>Exporter CSV</span>
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Département</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Commune</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Arrondissement</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Village/Quartier</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <template x-for="v in filteredNonSaisis" :key="v.village_quartier_id">
                            <tr class="hover:bg-red-50">
                                <td class="px-6 py-4 text-sm text-gray-600" x-text="v.departement_nom"></td>
                                <td class="px-6 py-4 text-sm text-gray-600" x-text="v.commune_nom"></td>
                                <td class="px-6 py-4 text-sm text-gray-600" x-text="v.arrondissement_nom"></td>
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900" x-text="v.village_quartier_nom"></div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-benin-red-100 text-benin-red-800">
                                        <i class="fas fa-times-circle mr-1"></i>
                                        Non saisi
                                    </span>
                                </td>
                            </tr>
                        </template>
                        <template x-if="filteredNonSaisis.length === 0">
                            <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                <i class="fas fa-check-circle text-benin-green-600 text-2xl mb-2"></i>
                                <p>Tous les villages ont été saisis !</p>
                            </td></tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
// Pas de graphiques pour cette vue globale
console.log('Vue globale des villages chargée');
</script>
@endpush