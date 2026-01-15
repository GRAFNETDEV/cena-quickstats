@extends('layouts.admin')

@section('title', 'Statistiques Nationales')

@section('breadcrumb')
    <span class="text-gray-400">Statistiques</span>
    <i class="fas fa-chevron-right text-xs text-gray-400"></i>
    <span class="text-gray-900 font-semibold">National</span>
@endsection

@section('content')
<div class="space-y-6">

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Statistiques Nationales</h1>
            <p class="text-gray-600 mt-1">{{ $stats['election']->nom ?? 'Élection' }} - {{ \Carbon\Carbon::parse($stats['election']->date_scrutin ?? now())->format('d/m/Y') }}</p>
        </div>

        <div class="flex items-center space-x-2">
            {{-- ✅ SÉLECTEUR D'ÉLECTION --}}
            <form method="GET" action="{{ route('stats.national') }}" class="inline-flex items-center gap-2">
                <select name="election_id" 
                        onchange="this.form.submit()"
                        class="px-4 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 font-medium hover:border-benin-green-500 focus:border-benin-green-500 focus:ring-2 focus:ring-benin-green-200">
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

            <a href="/export/national/csv"
               class="px-4 py-2 bg-benin-green-600 text-white rounded-lg hover:bg-benin-green-700 flex items-center space-x-2">
                <i class="fas fa-file-csv"></i><span>CSV</span>
            </a>
            <button onclick="window.print()"
                    class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-900 flex items-center space-x-2">
                <i class="fas fa-print"></i><span>Imprimer</span>
            </button>
        </div>
    </div>

    <div class="bg-benin-green-50 border-l-4 border-benin-green-500 p-4 rounded-lg">
        <div class="flex items-start">
            <i class="fas fa-info-circle text-benin-green-600 mt-1 mr-3"></i>
            <div>
                <h3 class="font-semibold text-benin-green-900 mb-1">📊 Distinction des Inscrits</h3>
                <div class="text-sm text-benin-green-800 space-y-1">
                    <p><strong>Inscrits CENA:</strong> Somme des inscrits par poste de vote (référence)</p>
                    <p><strong>Inscrits comptabilisés:</strong> Somme des inscrits des postes ayant un PV validé (PV dédupliqués)</p>
                    <p><strong>Couverture de saisie:</strong> {{ $stats['totaux']['couverture_saisie'] ?? 0 }}%</p>
                </div>
            </div>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        @php($t = $stats['totaux'] ?? [])
        <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-benin-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">PV Validés</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($t['nombre_pv_valides'] ?? 0) }}</p>
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
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($t['inscrits_cena'] ?? 0) }}</p>
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
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($t['inscrits_comptabilises'] ?? 0) }}</p>
                    <p class="text-xs text-benin-green-700 mt-1">{{ $t['couverture_saisie'] ?? 0 }}% de couverture</p>
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
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($t['nombre_votants'] ?? 0) }}</p>
                </div>
                <div class="bg-purple-100 rounded-full p-4">
                    <i class="fas fa-vote-yea text-purple-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-benin-yellow-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Participation Globale</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($t['taux_participation_global'] ?? 0, 2) }}%</p>
                    <p class="text-xs text-gray-500 mt-1">Sur inscrits CENA</p>
                </div>
                <div class="bg-benin-yellow-100 rounded-full p-4">
                    <i class="fas fa-percentage text-benin-yellow-700 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-teal-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Participation Bureaux</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($t['taux_participation_bureaux_comptabilises'] ?? 0, 2) }}%</p>
                    <p class="text-xs text-gray-500 mt-1">Bureaux comptabilisés</p>
                </div>
                <div class="bg-teal-100 rounded-full p-4">
                    <i class="fas fa-chart-line text-teal-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-cyan-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Suffrages Exprimés</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($t['nombre_suffrages_exprimes'] ?? 0) }}</p>
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
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($t['nombre_bulletins_nuls'] ?? 0) }}</p>
                </div>
                <div class="bg-benin-red-100 rounded-full p-4">
                    <i class="fas fa-times-circle text-benin-red-600 text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Charts --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Progression de la Saisie</h3>
            <div style="height: 300px;"><canvas id="progressionChart"></canvas></div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Départements (Participation)</h3>
            <div style="height: 300px;"><canvas id="deptsChart"></canvas></div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">Statistiques par Département</h3>
            <a href="/export/national/csv" class="text-benin-green-700 hover:text-benin-green-900 text-sm font-medium">
                <i class="fas fa-download mr-1"></i> Exporter ce tableau
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Département</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">PV</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Inscrits CENA</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Inscrits Comptabilisés</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Couverture</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Votants</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Suffrages</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Participation</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach(($stats['par_departement'] ?? []) as $dept)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="font-medium text-gray-900">{{ $dept['nom'] }}</div>
                            <div class="text-sm text-gray-500">{{ $dept['code'] }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ number_format($dept['nombre_pv_valides'] ?? 0) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span class="text-blue-700 font-semibold">{{ number_format($dept['inscrits_cena'] ?? 0) }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ number_format($dept['inscrits_comptabilises'] ?? 0) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php($c = $dept['couverture_saisie'] ?? 0)
                            <span class="px-2 py-1 text-xs font-semibold rounded-full
                                {{ $c >= 80 ? 'bg-benin-green-100 text-benin-green-800' :
                                   ($c >= 50 ? 'bg-benin-yellow-100 text-benin-yellow-800' : 'bg-benin-red-100 text-benin-red-800') }}">
                                {{ number_format($c, 1) }}%
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ number_format($dept['nombre_votants'] ?? 0) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ number_format($dept['nombre_suffrages_exprimes'] ?? 0) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-1 bg-gray-200 rounded-full h-2 mr-2" style="width: 120px;">
                                    <div class="bg-benin-green-600 h-2 rounded-full" style="width: {{ min($dept['taux_participation_global'] ?? 0, 100) }}%"></div>
                                </div>
                                <span class="text-sm font-semibold text-gray-900">{{ number_format($dept['taux_participation_global'] ?? 0, 2) }}%</span>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
const beninColors = { green:'#008751', yellow:'#FCD116', red:'#E8112D' };

const progression = @json($stats['progression'] ?? ['valides'=>0,'brouillons'=>0,'litigieux'=>0]);
const depts = @json($stats['par_departement'] ?? []);

const ctxProg = document.getElementById('progressionChart');
if (ctxProg && window.Chart) {
  new Chart(ctxProg, {
    type: 'doughnut',
    data: {
      labels: ['Validés','Brouillons','Litigieux'],
      datasets: [{ data: [progression.valides, progression.brouillons, progression.litigieux], backgroundColor:[beninColors.green, beninColors.yellow, beninColors.red], borderWidth:0 }]
    },
    options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom' } } }
  });
}

const ctxDept = document.getElementById('deptsChart');
if (ctxDept && window.Chart) {
  new Chart(ctxDept, {
    type: 'bar',
    data: {
      labels: depts.map(d => d.nom),
      datasets: [{ label:'Participation (%)', data: depts.map(d => d.taux_participation_global || 0), backgroundColor: beninColors.green, borderRadius: 6 }]
    },
    options: {
      responsive:true, maintainAspectRatio:false,
      plugins:{ legend:{ display:false } },
      scales:{ y:{ beginAtZero:true, max:100, ticks:{ callback:(v)=> v+'%' } } }
    }
  });
}
</script>
@endpush