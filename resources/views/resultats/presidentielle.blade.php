@extends('layouts.admin')

@section('title', 'Résultats Présidentiels - Compilation')

@section('breadcrumb')
    <span class="text-gray-400">Résultats</span>
    <i class="fas fa-chevron-right text-xs text-gray-400"></i>
    <span class="text-gray-900 font-semibold">Élections présidentielles</span>
@endsection

@section('content')
@php
    $classementNational = $compilation['classement_national'] ?? $data['classement_national'] ?? [];
    $totalVoixNational = (int) ($data['total_voix_national'] ?? 0);
    $majoriteAbsolueVoix = $totalVoixNational > 0 ? intdiv($totalVoixNational, 2) + 1 : 0;
@endphp

<div class="space-y-6" x-data="resultatsApp()">

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Résultats Élections Présidentielles</h1>
            <p class="text-gray-600 mt-1">{{ $election->nom }}</p>
        </div>

        <div class="flex items-center gap-2">
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

    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-gray-700">Règle de proclamation (1er tour)</h3>
                <p class="text-xs text-gray-600 mt-1">
                    Le duo est élu au premier tour uniquement à la majorité absolue des suffrages exprimés.
                    À défaut, second tour entre les deux duos en tête.
                </p>
            </div>
            <div class="text-right">
                <div class="text-xs text-gray-500">Majorité absolue</div>
                <div class="text-lg font-extrabold text-benin-green-700">{{ number_format($majoriteAbsolueVoix) }} voix</div>
            </div>
        </div>
    </div>

    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl shadow-sm p-6 border border-blue-200">
        <div class="flex items-center mb-4">
            <svg class="w-6 h-6 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <h3 class="text-lg font-semibold text-gray-900">Exports disponibles</h3>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            <a href="{{ route('resultats.export.matrice.csv', ['election_id' => $election->id]) }}"
               class="flex items-center justify-between p-4 bg-white rounded-lg border border-gray-200 hover:border-blue-400 hover:shadow-md transition-all group">
                <div>
                    <div class="font-semibold text-gray-900 group-hover:text-blue-600">Matrice par commune</div>
                    <div class="text-xs text-gray-500 mt-1">Voix et pourcentages par duo</div>
                </div>
                <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </a>

            <a href="{{ route('resultats.export.details.csv', ['election_id' => $election->id]) }}"
               class="flex items-center justify-between p-4 bg-white rounded-lg border border-gray-200 hover:border-indigo-400 hover:shadow-md transition-all group">
                <div>
                    <div class="font-semibold text-gray-900 group-hover:text-indigo-600">Synthèse juridique</div>
                    <div class="text-xs text-gray-500 mt-1">Décision 1er tour / second tour</div>
                </div>
                <svg class="w-5 h-5 text-gray-400 group-hover:text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </a>

            <a href="{{ route('resultats.export.sieges.csv', ['election_id' => $election->id]) }}"
               class="flex items-center justify-between p-4 bg-white rounded-lg border border-gray-200 hover:border-purple-400 hover:shadow-md transition-all group">
                <div>
                    <div class="font-semibold text-gray-900 group-hover:text-purple-600">Classement national</div>
                    <div class="text-xs text-gray-500 mt-1">Rang, voix, statut de qualification</div>
                </div>
                <svg class="w-5 h-5 text-gray-400 group-hover:text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </a>

            <a href="{{ route('rapports.presidentielle', ['election_id' => $election->id]) }}"
               class="flex items-center justify-between p-4 bg-white rounded-lg border border-gray-200 hover:border-red-400 hover:shadow-md transition-all group">
                <div>
                    <div class="font-semibold text-gray-900 group-hover:text-red-700">Rapport présidentiel</div>
                    <div class="text-xs text-gray-500 mt-1">Vue structurée et export PDF officiel</div>
                </div>
                <svg class="w-5 h-5 text-gray-400 group-hover:text-red-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </a>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Matrice des résultats par commune</h3>
                    <p class="text-sm text-gray-600 mt-1">Suffrages agrégés à partir des PV d’arrondissements dédupliqués</p>
                </div>
                <div class="text-xs text-gray-500">Total national : {{ number_format($totalVoixNational) }} voix</div>
            </div>
        </div>

        <div class="overflow-x-auto" style="max-height: 620px;">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 sticky top-0 z-10">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase sticky left-0 bg-gray-50 z-20">
                            Commune
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
                        @php
                            $resultatsCommune = $data['matrice_communes'][$commune->id]['resultats'] ?? [];
                            $maxVoixCommune = 0;
                            foreach ($resultatsCommune as $r) {
                                $maxVoixCommune = max($maxVoixCommune, (int) ($r['voix'] ?? 0));
                            }
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap sticky left-0 bg-white">
                                <div class="text-sm font-medium text-gray-900">{{ $commune->nom }}</div>
                                <div class="text-xs text-gray-500">{{ $commune->departement_nom }}</div>
                            </td>
                            @foreach($data['entites'] as $entite)
                                @php
                                    $result = $resultatsCommune[$entite->id] ?? ['voix' => 0, 'pourcentage' => 0];
                                    $voix = (int) ($result['voix'] ?? 0);
                                    $pct = (float) ($result['pourcentage'] ?? 0);
                                    $isLeader = $voix > 0 && $voix === $maxVoixCommune;
                                    $cardClass = $isLeader
                                        ? 'bg-benin-green-100 border-benin-green-300 text-benin-green-900'
                                        : 'bg-gray-50 border-gray-200 text-gray-900';
                                @endphp
                                <td class="px-4 py-3 text-center">
                                    <div class="inline-block px-3 py-2 rounded-lg border {{ $cardClass }}">
                                        <div class="font-bold text-sm">{{ number_format($voix) }}</div>
                                        <div class="text-xs">({{ number_format($pct, 2) }}%)</div>
                                    </div>
                                </td>
                            @endforeach
                            <td class="px-4 py-3 text-right font-semibold text-gray-900 bg-gray-50">
                                {{ number_format($data['matrice_communes'][$commune->id]['total_voix'] ?? 0) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-100 font-bold sticky bottom-0">
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900 sticky left-0 bg-gray-100">TOTAL NATIONAL</td>
                        @foreach($data['entites'] as $entite)
                            @php
                                $totalVoix = $data['totaux_par_entite'][$entite->id]['voix'] ?? 0;
                                $pctNational = $data['totaux_par_entite'][$entite->id]['pourcentage_national'] ?? 0;
                            @endphp
                            <td class="px-4 py-3 text-center text-sm">
                                <div>{{ number_format($totalVoix) }}</div>
                                <div class="text-xs text-gray-600">({{ number_format($pctNational, 2) }}%)</div>
                            </td>
                        @endforeach
                        <td class="px-4 py-3 text-right text-sm bg-gray-200">
                            {{ number_format($totalVoixNational) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Matrice des résultats par département</h3>
            <p class="text-sm text-gray-600 mt-1">Vue de synthèse territoriale</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Département</th>
                        @foreach($data['entites'] as $entite)
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                {{ $entite->sigle ?: $entite->nom }}
                            </th>
                        @endforeach
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($data['departements'] as $departement)
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $departement->nom }}</td>
                            @foreach($data['entites'] as $entite)
                                @php
                                    $result = $data['matrice_departements'][$departement->id]['resultats'][$entite->id] ?? ['voix' => 0, 'pourcentage' => 0];
                                @endphp
                                <td class="px-4 py-3 text-center text-sm">
                                    <div>{{ number_format($result['voix']) }}</div>
                                    <div class="text-xs text-gray-500">({{ number_format($result['pourcentage'], 2) }}%)</div>
                                </td>
                            @endforeach
                            <td class="px-4 py-3 text-right text-sm font-semibold">{{ number_format($data['matrice_departements'][$departement->id]['total_voix'] ?? 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

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

    @if($compilation)
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50">
                <h3 class="text-xl font-bold text-gray-900">
                    <i class="fas fa-gavel text-blue-600 mr-2"></i>
                    {{ $compilation['decision_label'] ?? 'Décision de compilation' }}
                </h3>
                <p class="text-sm text-gray-600 mt-1">
                    Majorité absolue requise : {{ number_format($compilation['majorite_absolue_voix'] ?? 0) }} voix
                </p>
            </div>

            <div class="p-6 space-y-4">
                @if(!empty($compilation['elu_premier_tour']) && !empty($compilation['premier']))
                    <div class="rounded-lg border border-green-200 bg-green-50 p-4">
                        <div class="text-sm text-green-900 font-semibold">Duo élu au premier tour</div>
                        <div class="text-xl font-bold text-green-900 mt-1">{{ $compilation['premier']['ticket'] }}</div>
                        <div class="text-sm text-green-800 mt-1">
                            {{ number_format($compilation['premier']['voix']) }} voix
                            ({{ number_format($compilation['premier']['pourcentage'], 2) }}%)
                        </div>
                    </div>
                @elseif(!empty($compilation['second_tour_requis']))
                    <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4">
                        <div class="text-sm text-yellow-900 font-semibold">Second tour requis</div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                            @foreach($compilation['qualifies_second_tour'] ?? [] as $duo)
                                <div class="bg-white rounded-lg border border-yellow-200 p-3">
                                    <div class="text-xs text-gray-500">Rang {{ $duo['rang'] }}</div>
                                    <div class="font-bold text-gray-900">{{ $duo['ticket'] }}</div>
                                    <div class="text-sm text-gray-700 mt-1">
                                        {{ number_format($duo['voix']) }} voix ({{ number_format($duo['pourcentage'], 2) }}%)
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(!empty($compilation['egalite_limite_second_tour']))
                    <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                        Égalité détectée à la limite de qualification du second tour. Une validation juridique est requise avant proclamation.
                    </div>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Classement national des duos</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rang</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Duo</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Voix</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">%</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($compilation['classement_national'] as $ligne)
                            @php
                                $badge = 'bg-gray-100 text-gray-700';
                                if (($ligne['statut'] ?? '') === 'elu_premier_tour') $badge = 'bg-green-100 text-green-800';
                                if (($ligne['statut'] ?? '') === 'qualifie_second_tour') $badge = 'bg-yellow-100 text-yellow-800';
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ $ligne['rang'] }}</td>
                                <td class="px-4 py-3">
                                    <div class="text-sm font-semibold text-gray-900">{{ $ligne['ticket'] }}</div>
                                    <div class="text-xs text-gray-500">{{ $ligne['sigle'] }}</div>
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900">{{ number_format($ligne['voix']) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900">{{ number_format($ligne['pourcentage'], 2) }}%</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $badge }}">
                                        {{ $ligne['statut'] === 'elu_premier_tour' ? 'Élu' : ($ligne['statut'] === 'qualifie_second_tour' ? 'Qualifié' : 'Non qualifié') }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
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
