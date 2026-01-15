@extends('layouts.admin')

@section('title', 'Statistiques par Circonscription')

@section('breadcrumb')
    <span class="text-gray-400">Statistiques</span>
    <i class="fas fa-chevron-right text-xs text-gray-400"></i>
    <span class="text-gray-900 font-semibold">Circonscriptions</span>
@endsection

@section('content')
<div class="space-y-6">

    <!-- Page Header + Sélecteurs -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Statistiques par Circonscription</h1>
            <p class="text-gray-600 mt-1">
                {{ $stats['election']->nom ?? 'Élection' }}
                @if(!empty($stats['election']) && !empty($stats['election']->date_scrutin))
                    - {{ \Carbon\Carbon::parse($stats['election']->date_scrutin)->format('d/m/Y') }}
                @endif
            </p>
        </div>

        <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
            {{-- ✅ SÉLECTEUR D'ÉLECTION --}}
            <form method="GET" action="{{ route('stats.circonscription') }}" class="inline-flex items-center gap-2">
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

            {{-- SÉLECTEUR DE DÉPARTEMENT --}}
            <form method="GET" action="{{ route('stats.circonscription') }}" class="flex items-center gap-2">
                @if(request('election_id'))
                    <input type="hidden" name="election_id" value="{{ request('election_id') }}">
                @endif
                <label class="text-sm font-medium text-gray-700">Circonscription</label>
                <select name="circonscription_id"
                        class="w-72 rounded-lg border-gray-300 focus:border-benin-green-500 focus:ring-benin-green-500"
                        onchange="this.form.submit()">
                    @foreach(($circonscriptions ?? []) as $d)
                        <option value="{{ $d->id }}"
                            {{ (int)($circonscriptionId ?? request('circonscription_id')) === (int)$d->id ? 'selected' : '' }}>
                            {{ $d->nom }}
                        </option>
                    @endforeach
                </select>
            </form>

            <div class="flex items-center gap-2">
                <a href="{{ route('export.circonscription.csv', ['circonscription_id' => ($circonscriptionId ?? request('circonscription_id'))]) }}"
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

    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Circonscriptions (Participation)</h3>
            <div style="height: 320px;">
                <canvas id="circonscriptionsChart"></canvas>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Couverture par Commune</h3>
            <div style="height: 320px;">
                <canvas id="couvertureCommunesChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Table Communes -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Communes du Circonscription</h3>
                <a href="{{ route('export.circonscription.communes.csv', ['circonscription_id' => ($circonscriptionId ?? request('circonscription_id'))]) }}"
                   class="text-benin-green-600 hover:text-benin-green-800 text-sm font-medium">
                    <i class="fas fa-download mr-1"></i> Exporter ce tableau
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200" id="communesTable">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Commune</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">PV</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Inscrits CENA</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Inscrits Comptabilisés</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Couverture</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Votants</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Participation</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach(($stats['par_commune'] ?? []) as $c)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="font-medium text-gray-900">{{ $c['nom'] ?? '-' }}</div>
                            <div class="text-sm text-gray-500">{{ $c['code'] ?? '' }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ number_format($c['nombre_pv_valides'] ?? 0) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span class="text-blue-600 font-semibold">{{ number_format($c['inscrits_cena'] ?? 0) }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ number_format($c['inscrits_comptabilises'] ?? 0) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full
                                {{ ($c['couverture_saisie'] ?? 0) >= 80 ? 'bg-benin-green-100 text-benin-green-800' :
                                   (($c['couverture_saisie'] ?? 0) >= 50 ? 'bg-benin-yellow-100 text-benin-yellow-800' : 'bg-benin-red-100 text-benin-red-800') }}">
                                {{ number_format($c['couverture_saisie'] ?? 0, 1) }}%
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ number_format($c['nombre_votants'] ?? 0) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-1 bg-gray-200 rounded-full h-2 mr-2" style="width: 110px;">
                                    <div class="bg-benin-green-500 h-2 rounded-full"
                                         style="width: {{ min((float)($c['taux_participation_global'] ?? 0), 100) }}%"></div>
                                </div>
                                <span class="text-sm font-semibold text-gray-900">{{ number_format($c['taux_participation_global'] ?? 0, 2) }}%</span>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                    @if(empty($stats['par_commune']))
                        <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">Aucune donnée</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
const beninColors = { green:'#008751', yellow:'#FCD116', red:'#E8112D' };

// Circonscriptions chart (bar)
const circoLabels = [
@foreach(($stats['par_circonscription'] ?? []) as $x)
    @if(isset($x['nom'])) '{{ $x['nom'] }}', @endif
@endforeach
];

const circoParticipation = [
@foreach(($stats['par_circonscription'] ?? []) as $x)
    {{ (float)($x['taux_participation'] ?? 0) }},
@endforeach
];

const ctxCirco = document.getElementById('circonscriptionsChart');
if (ctxCirco && typeof Chart !== 'undefined') {
    new Chart(ctxCirco.getContext('2d'), {
        type: 'bar',
        data: {
            labels: circoLabels,
            datasets: [{
                label: 'Participation (%)',
                data: circoParticipation,
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

// Couverture communes chart (horizontal bar)
const comLabels = [
@foreach(($stats['par_commune'] ?? []) as $x)
    @if(isset($x['nom'])) '{{ $x['nom'] }}', @endif
@endforeach
];
const comCouverture = [
@foreach(($stats['par_commune'] ?? []) as $x)
    {{ (float)($x['couverture_saisie'] ?? 0) }},
@endforeach
];

const ctxCov = document.getElementById('couvertureCommunesChart');
if (ctxCov && typeof Chart !== 'undefined') {
    new Chart(ctxCov.getContext('2d'), {
        type: 'bar',
        data: {
            labels: comLabels,
            datasets: [{
                label: 'Couverture (%)',
                data: comCouverture,
                backgroundColor: beninColors.yellow,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, ticks: { callback: v => v + '%' } }
            }
        }
    });
}
</script>
@endpush