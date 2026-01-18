@extends('layouts.admin')

@section('title', 'Résultats Électoraux')

@section('breadcrumb')
    <span class="text-gray-400">Résultats</span>
    <i class="fas fa-chevron-right text-xs text-gray-400"></i>
    <span class="text-gray-900 font-semibold">Compilation</span>
@endsection

@section('content')
<div class="space-y-6" x-data="resultatsApp()">

    <!-- En-tête -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Résultats Électoraux</h1>
            <p class="text-gray-600 mt-1">{{ $election->nom }}</p>
        </div>

        <div class="flex items-center gap-2">
            {{-- Sélecteur d'élection --}}
            <form method="GET" action="{{ route('resultats') }}" class="inline-flex items-center gap-2">
                <label class="text-sm font-medium text-gray-700">Élection</label>
                <select name="election_id" 
                        onchange="this.form.submit()"
                        class="px-4 py-2 border border-gray-300 rounded-lg bg-white">
                    @foreach($elections as $elec)
                        <option value="{{ $elec->id }}" {{ $election->id == $elec->id ? 'selected' : '' }}>
                            {{ $elec->nom }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>

    <!-- Info Seuils -->
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-gray-700">Seuil d'éligibilité national</h3>
                <p class="text-xs text-gray-600 mt-1">Seuls les partis ayant obtenu ≥ 10% des suffrages au plan national participent à l'attribution des sièges</p>
            </div>
            <div class="flex items-center gap-4 text-sm">
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded bg-red-500"></div>
                    <span>< 10%</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded bg-green-500"></div>
                    <span>≥ 10% (Éligible)</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Matrice des résultats par commune --}}
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">📊 Matrice des Résultats par Commune</h3>
                <p class="text-sm text-gray-600 mt-1">Voix et pourcentages par commune et par entité politique</p>
            </div>
            <a href="{{ route('resultats.export.matrice.csv', ['election_id' => $election->id]) }}" 
               class="px-4 py-2 bg-benin-green-600 text-white rounded-lg hover:bg-benin-green-700 inline-flex items-center gap-2">
                <i class="fas fa-file-csv"></i>
                <span>Exporter CSV</span>
            </a>
        </div>

        <div class="overflow-x-auto" style="max-height: 600px;">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 sticky top-0 z-10">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase sticky left-0 bg-gray-50 z-20">
                            Commune
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase bg-gray-50">
                            Sièges
                        </th>
                        @foreach($data['entites'] as $entite)
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                <div class="font-bold">{{ $entite->sigle ?: $entite->nom }}</div>
                            </th>
                        @endforeach
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase bg-gray-100">
                            Total
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($data['communes'] as $commune)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap sticky left-0 bg-white">
                                <div class="text-sm font-medium text-gray-900">{{ $commune->nom }}</div>
                                <div class="text-xs text-gray-500">{{ $commune->departement_nom }}</div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                                    {{ $data['matrice'][$commune->id]['nombre_sieges'] }}
                                </span>
                            </td>
                            @foreach($data['entites'] as $entite)
                                @php
                                    $result = $data['matrice'][$commune->id]['resultats'][$entite->id] ?? ['voix' => 0, 'pourcentage' => 0];
                                    $voix = $result['voix'];
                                    $pct = $result['pourcentage'];
                                @endphp
                                <td class="px-4 py-3 text-center">
                                    <div class="inline-block px-3 py-2 rounded-lg">
                                        <div class="font-bold text-sm text-gray-900">{{ number_format($voix) }}</div>
                                        <div class="text-xs text-gray-600">({{ number_format($pct, 2) }}%)</div>
                                    </div>
                                </td>
                            @endforeach
                            <td class="px-4 py-3 text-right font-semibold text-gray-900 bg-gray-50">
                                {{ number_format($data['matrice'][$commune->id]['total_voix']) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-100 font-bold sticky bottom-0">
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900 sticky left-0 bg-gray-100">TOTAL NATIONAL</td>
                        <td class="px-4 py-3 text-center text-sm"></td>
                        @foreach($data['entites'] as $entite)
                            @php
                                $totalVoix = $data['totaux_par_entite'][$entite->id]['voix'];
                                $pctNational = $data['totaux_par_entite'][$entite->id]['pourcentage_national'];
                            @endphp
                            <td class="px-4 py-3 text-center text-sm">
                                <div>{{ number_format($totalVoix) }}</div>
                                <div class="text-xs text-gray-600">({{ number_format($pctNational, 2) }}%)</div>
                            </td>
                        @endforeach
                        <td class="px-4 py-3 text-right text-sm bg-gray-200">
                            {{ number_format($data['total_voix_national']) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Boutons de compilation --}}
    <div class="flex justify-center gap-4">
        @if($compilation)
            <form method="GET" action="{{ route('resultats') }}">
                @if(request('election_id'))
                    <input type="hidden" name="election_id" value="{{ request('election_id') }}">
                @endif
                <button type="submit"
                        class="px-8 py-4 bg-gray-600 text-white rounded-xl font-bold text-lg hover:shadow-xl transform hover:-translate-y-1 transition-all">
                    <i class="fas fa-redo mr-3"></i>
                    RÉINITIALISER
                </button>
            </form>
        @else
            <form x-ref="compileForm" method="GET" action="{{ route('resultats') }}">
                @if(request('election_id'))
                    <input type="hidden" name="election_id" value="{{ request('election_id') }}">
                @endif
                <input type="hidden" name="compiler" value="1">

                <button type="button"
                        @click="lancerCompilation()"
                        :disabled="isCompiling"
                        class="px-8 py-4 rounded-xl font-bold text-lg hover:shadow-xl transform hover:-translate-y-1 transition-all
                               bg-gradient-to-r from-benin-green-600 to-benin-green-700 text-white
                               disabled:opacity-60 disabled:cursor-not-allowed disabled:transform-none">
                    <span x-show="!isCompiling" class="inline-flex items-center">
                        <i class="fas fa-calculator mr-3"></i>
                        COMPILER LES RÉSULTATS
                    </span>
                    <span x-show="isCompiling" class="inline-flex items-center" style="display:none;">
                        <i class="fas fa-spinner fa-spin mr-3"></i>
                        Calcul en cours...
                    </span>
                </button>
            </form>
        @endif
    </div>

    {{-- Résultats de la compilation --}}
    @if($compilation)
        {{-- ÉTAPE 1 : Éligibilité Nationale --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50">
                <h3 class="text-xl font-bold text-gray-900">
                    <i class="fas fa-check-circle text-blue-600 mr-2"></i>
                    Seuil d'éligibilité
                </h3>
                <p class="text-sm text-gray-600 mt-1">Seuil : ≥ 10% des suffrages exprimés au plan national</p>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($compilation['eligibilite'] as $entiteId => $elig)
                        <div class="border-2 rounded-lg p-4 {{ $elig['eligible'] ? 'border-green-500 bg-green-50' : 'border-red-500 bg-red-50' }}">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h4 class="font-bold text-gray-900">{{ $elig['entite']->nom }}</h4>
                                    <p class="text-sm text-gray-600">{{ $elig['entite']->sigle }}</p>
                                </div>
                                <div class="text-right">
                                    @if($elig['eligible'])
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-green-600 text-white">
                                            <i class="fas fa-check mr-1"></i> ÉLIGIBLE
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-red-600 text-white">
                                            <i class="fas fa-times mr-1"></i> NON ÉLIGIBLE
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-4 space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Pourcentage national</span>
                                    <span class="font-semibold {{ $elig['eligible'] ? 'text-green-600' : 'text-red-600' }}">
                                        {{ number_format($elig['pourcentage_national'], 2) }}%
                                    </span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Total voix</span>
                                    <span class="font-semibold">{{ number_format($elig['total_voix']) }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Répartition des Sièges --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-purple-50 to-pink-50">
                <h3 class="text-xl font-bold text-gray-900">
                    <i class="fas fa-chair text-purple-600 mr-2"></i>
                    Répartition des Sièges par Commune
                </h3>
                <p class="text-sm text-gray-600 mt-1">Quotient Électoral • Attribution au quotient • Plus fort reste</p>
            </div>

            {{-- Récapitulatif national --}}
            <div class="p-6 border-b border-gray-200">
                <h4 class="font-bold text-lg text-gray-900 mb-4">📊 Récapitulatif National</h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entité Politique</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase bg-benin-green-50">Total Sièges</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">% National</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @php $totalGeneral = 0; @endphp
                            @foreach($compilation['sieges_totaux'] as $entiteId => $sieges)
                                @if($sieges['sieges_total'] > 0)
                                    @php
                                        $entite = collect($compilation['data']['entites'])->firstWhere('id', $entiteId);
                                        $totalGeneral += $sieges['sieges_total'];
                                        $pctNational = $compilation['data']['totaux_par_entite'][$entiteId]['pourcentage_national'];
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 font-medium text-gray-900">
                                            {{ $entite->nom }}
                                            <span class="text-sm text-gray-500">({{ $entite->sigle }})</span>
                                        </td>
                                        <td class="px-6 py-4 text-center text-2xl font-bold text-benin-green-600 bg-benin-green-50">
                                            {{ $sieges['sieges_total'] }}
                                        </td>
                                        <td class="px-6 py-4 text-center font-semibold text-gray-700">
                                            {{ number_format($pctNational, 2) }}%
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100 font-bold">
                            <tr>
                                <td class="px-6 py-4 text-gray-900">TOTAL</td>
                                <td class="px-6 py-4 text-center text-2xl text-benin-green-600 bg-benin-green-100">{{ $totalGeneral }}</td>
                                <td class="px-6 py-4 text-center">100.00%</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="mt-4 flex justify-center">
                    <a href="{{ route('resultats.export.sieges.csv', ['election_id' => $election->id]) }}" 
                       class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        <i class="fas fa-download mr-2"></i>
                        Exporter les Sièges (CSV)
                    </a>
                </div>
            </div>

            {{-- Détails par commune (échantillon des 10 premières) --}}
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="font-bold text-lg text-gray-900">🏘️ Détails par Commune (extrait)</h4>
                    <a href="{{ route('resultats.export.details.csv', ['election_id' => $election->id]) }}" 
                       class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 inline-flex items-center gap-2">
                        <i class="fas fa-file-csv"></i>
                        <span>Export Complet CSV</span>
                    </a>
                </div>
                
                <div class="text-sm text-gray-600 mb-4">
                    <i class="fas fa-info-circle text-blue-500"></i>
                    Affichage des 10 premières communes. Utilisez l'export CSV pour voir toutes les communes.
                </div>

                <div class="space-y-3">
                    @foreach(array_slice($compilation['repartition'], 0, 10, true) as $communeId => $rep)
                        @if($rep['nombre_sieges'] > 0)
                        <div class="border rounded-lg p-3 bg-gray-50">
                            <div class="flex items-center justify-between mb-2">
                                <div>
                                    <span class="font-semibold text-gray-900">{{ $rep['info']->nom }}</span>
                                    <span class="text-sm text-gray-600 ml-2">• {{ $rep['nombre_sieges'] }} sièges</span>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                                @foreach($rep['details'] as $entiteId => $detail)
                                    @if($detail['sieges_total'] > 0)
                                        @php
                                            $entite = collect($compilation['data']['entites'])->firstWhere('id', $entiteId);
                                        @endphp
                                        <div class="bg-white rounded p-2 border">
                                            <div class="font-semibold text-gray-900">{{ $entite->sigle }}</div>
                                            <div class="text-benin-green-600 font-bold">{{ $detail['sieges_total'] }} siège(s)</div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    @endif

</div>

<script>
function resultatsApp() {
    return {
        isCompiling: false,

        lancerCompilation() {
            if (this.isCompiling) return;
            this.isCompiling = true;
            this.$refs.compileForm.submit();
        }
    }
}
</script>

@endsection