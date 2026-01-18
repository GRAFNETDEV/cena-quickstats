@extends('layouts.admin')

@section('title', 'Statistiques par Arrondissement')

@section('breadcrumb')
    <span class="text-gray-400">Statistiques</span>
    <i class="fas fa-chevron-right text-xs text-gray-400"></i>
    <span class="text-gray-900 font-semibold">Arrondissements</span>
@endsection

@section('content')
<div class="space-y-6" x-data="{
    q:'',
    rows: @js($stats['villages'] ?? []),
    get filtered(){
        const s=(this.q||'').toLowerCase().trim();
        if(!s) return this.rows;
        return this.rows.filter(r => (r.nom||'').toLowerCase().includes(s));
    }
}">

    <!-- Page Header + Sélecteurs -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Statistiques par Arrondissement</h1>
            <p class="text-gray-600 mt-1">
                {{ $stats['election']->nom ?? 'Élection' }}
                @if(!empty($stats['election']) && !empty($stats['election']->date_scrutin))
                    - {{ \Carbon\Carbon::parse($stats['election']->date_scrutin)->format('d/m/Y') }}
                @endif
            </p>
        </div>

        <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
            {{-- SÉLECTEUR D'ÉLECTION --}}
            <form method="GET" action="{{ route('stats.arrondissement') }}" class="inline-flex items-center gap-2">
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

            {{-- SÉLECTEUR D'ARRONDISSEMENT --}}
            <form method="GET" action="{{ route('stats.arrondissement') }}" class="flex items-center gap-2">
                @if(request('election_id'))
                    <input type="hidden" name="election_id" value="{{ request('election_id') }}">
                @endif
                <label class="text-sm font-medium text-gray-700">Arrondissement</label>
                <select name="arrondissement_id"
                        class="w-72 rounded-lg border-gray-300 focus:border-benin-green-500 focus:ring-benin-green-500"
                        onchange="this.form.submit()">
                    @foreach(($stats['arrondissements'] ?? []) as $d)
                        <option value="{{ $d->id }}"
                            {{ (int)($arrondissementId ?? request('arrondissement_id')) === (int)$d->id ? 'selected' : '' }}>
                            {{ $d->nom }}
                        </option>
                    @endforeach
                </select>
            </form>

            <div class="flex items-center gap-2">
                <a href="{{ route('export.arrondissement.csv', ['arrondissement_id' => ($arrondissementId ?? request('arrondissement_id'))]) }}"
                   class="px-4 py-2 bg-benin-green-600 text-white rounded-lg hover:bg-benin-green-700 flex items-center space-x-2">
                    <i class="fas fa-file-csv"></i><span>CSV</span>
                </a>
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
                <h3 class="font-semibold text-benin-green-900 mb-1">📌 Lecture des indicateurs</h3>
                <div class="text-sm text-benin-green-800 space-y-1">
                    <p><strong>Inscrits CENA:</strong> base de référence</p>
                    <p><strong>Inscrits comptabilisés:</strong> inscrits des bureaux couverts par des PV validés</p>
                    <p><strong>Couverture:</strong> ratio comptabilisés / CENA</p>
                    <p><strong>Participation:</strong> votants / inscrits CENA</p>
                </div>
            </div>
        </div>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

        <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-benin-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">PV Validés</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($stats['totaux']['nombre_pv_valides'] ?? 0) }}</p>
                </div>
                <div class="bg-benin-green-100 rounded-full p-4">
                    <i class="fas fa-file-alt text-benin-green-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Inscrits CENA</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($stats['totaux']['inscrits_cena'] ?? 0) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Base de référence</p>
                </div>
                <div class="bg-blue-100 rounded-full p-4">
                    <i class="fas fa-users text-blue-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-benin-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Inscrits Comptabilisés</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($stats['totaux']['inscrits_comptabilises'] ?? 0) }}</p>
                    <p class="text-xs text-benin-green-600 mt-1">
                        {{ number_format($stats['totaux']['couverture_saisie'] ?? 0, 2) }}% de couverture
                    </p>
                </div>
                <div class="bg-benin-green-100 rounded-full p-4">
                    <i class="fas fa-user-check text-benin-green-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Votants</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($stats['totaux']['nombre_votants'] ?? 0) }}</p>
                </div>
                <div class="bg-purple-100 rounded-full p-4">
                    <i class="fas fa-vote-yea text-purple-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-benin-yellow-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Participation</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($stats['totaux']['taux_participation_global'] ?? 0, 2) }}%</p>
                    <p class="text-xs text-gray-500 mt-1">Sur inscrits CENA</p>
                </div>
                <div class="bg-benin-yellow-100 rounded-full p-4">
                    <i class="fas fa-percentage text-benin-yellow-700 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-cyan-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Suffrages Exprimés</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($stats['totaux']['nombre_suffrages_exprimes'] ?? 0) }}</p>
                </div>
                <div class="bg-cyan-100 rounded-full p-4">
                    <i class="fas fa-check-circle text-cyan-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-benin-red-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Bulletins Nuls</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($stats['totaux']['nombre_bulletins_nuls'] ?? 0) }}</p>
                </div>
                <div class="bg-benin-red-100 rounded-full p-4">
                    <i class="fas fa-times-circle text-benin-red-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-teal-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Participation Bureaux</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($stats['totaux']['taux_participation_bureaux_comptabilises'] ?? 0, 2) }}%</p>
                    <p class="text-xs text-gray-500 mt-1">Bureaux comptabilisés</p>
                </div>
                <div class="bg-teal-100 rounded-full p-4">
                    <i class="fas fa-chart-line text-teal-600 text-2xl"></i>
                </div>
            </div>
        </div>

    </div>

    <!-- Chart Villages -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Villages/Quartiers (Participation %)</h3>
        <div style="height: 320px;">
            <canvas id="villagesChart"></canvas>
        </div>
    </div>

    <!-- Recherche -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center gap-3">
            <i class="fas fa-search text-gray-400"></i>
            <input type="text" x-model="q" 
                   class="flex-1 border-0 focus:ring-0 text-gray-900" 
                   placeholder="Rechercher un village/quartier...">
            <span class="text-sm text-gray-500" x-text="filtered.length + ' élément(s)'"></span>
        </div>
    </div>

    <!-- Table Villages - VERSION COMPLÈTE ✅ -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Villages/Quartiers de l'Arrondissement</h3>
                <a href="{{ route('export.arrondissement.csv', ['arrondissement_id' => ($arrondissementId ?? request('arrondissement_id'))]) }}"
                   class="text-benin-green-600 hover:text-benin-green-800 text-sm font-medium">
                    <i class="fas fa-download mr-1"></i> Exporter ce tableau
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Village/Quartier</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">PV</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Inscrits CENA</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Inscrits Comptabilisés</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Couverture</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Votants</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Participation</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <template x-for="r in filtered" :key="r.id">
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900" x-text="r.nom"></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <span x-text="Number(r.nombre_pv_valides||0).toLocaleString()"></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="text-blue-600 font-semibold" x-text="Number(r.inscrits_cena||0).toLocaleString()"></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <span x-text="Number(r.inscrits_comptabilises||0).toLocaleString()"></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full"
                                      :class="{
                                          'bg-benin-green-100 text-benin-green-800': (r.couverture_saisie||0) >= 80,
                                          'bg-benin-yellow-100 text-benin-yellow-800': (r.couverture_saisie||0) >= 50 && (r.couverture_saisie||0) < 80,
                                          'bg-benin-red-100 text-benin-red-800': (r.couverture_saisie||0) < 50
                                      }">
                                    <span x-text="Number(r.couverture_saisie||0).toFixed(1) + '%'"></span>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <span x-text="Number(r.nombre_votants||0).toLocaleString()"></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-1 bg-gray-200 rounded-full h-2 mr-2" style="width: 110px;">
                                        <div class="bg-benin-green-500 h-2 rounded-full"
                                             :style="'width: ' + Math.min((r.taux_participation_global||0), 100) + '%'"></div>
                                    </div>
                                    <span class="text-sm font-semibold text-gray-900">
                                        <span x-text="Number(r.taux_participation_global||0).toFixed(2) + '%'"></span>
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <template x-if="filtered.length === 0">
                        <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">Aucune donnée</td></tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
const beninColors = { green:'#008751', yellow:'#FCD116', red:'#E8112D' };

// Graphique Villages (Participation)
const vilLabels = [
@foreach(($stats['villages'] ?? []) as $x)
    @if(isset($x['nom'])) '{{ $x['nom'] }}', @endif
@endforeach
];

const vilParticipation = [
@foreach(($stats['villages'] ?? []) as $x)
    {{ (float)($x['taux_participation_global'] ?? 0) }},
@endforeach
];

const ctxVil = document.getElementById('villagesChart');
if (ctxVil && typeof Chart !== 'undefined') {
    new Chart(ctxVil.getContext('2d'), {
        type: 'bar',
        data: {
            labels: vilLabels,
            datasets: [{
                label: 'Participation (%)',
                data: vilParticipation,
                backgroundColor: beninColors.green,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { callback: v => v + '%' }
                }
            }
        }
    });
}
</script>
@endpush