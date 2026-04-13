@extends('layouts.admin')

@section('title', 'Résultats Communales - Compilation')

@section('breadcrumb')
    <span class="text-gray-400">Résultats</span>
    <i class="fas fa-chevron-right text-xs text-gray-400"></i>
    <span class="text-gray-900 font-semibold">Élections Communales</span>
@endsection

@section('content')
<div class="space-y-6" x-data="resultatsApp()">

    <!-- En-tête -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Résultats Élections Communales</h1>
            <p class="text-gray-600 mt-1">{{ $election->nom }}</p>
        </div>

        <div class="flex items-center gap-2">
            {{-- Sélecteur d'élection --}}
            <form method="GET" action="{{ route('rapports.communales') }}" class="inline-flex items-center gap-2">
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
    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
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

    {{-- Section Exports Principaux --}}
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl shadow-sm p-6 border border-blue-200">
        <div class="flex items-center mb-4">
            <svg class="w-6 h-6 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <h3 class="text-lg font-semibold text-gray-900">Exports Disponibles</h3>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            {{-- Export Matrice --}}
            <a href="{{ route('export.communales.matrice-csv', ['election_id' => $election->id]) }}" 
               class="flex items-center justify-between p-4 bg-white rounded-lg border border-gray-200 hover:border-blue-400 hover:shadow-md transition-all group">
                <div>
                    <div class="font-semibold text-gray-900 group-hover:text-blue-600">Matrice Résultats</div>
                    <div class="text-xs text-gray-500 mt-1">Voix par commune et parti</div>
                </div>
                <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </a>

            {{-- Export Sièges --}}
            <a href="{{ route('export.communales.sieges-csv', ['election_id' => $election->id]) }}" 
               class="flex items-center justify-between p-4 bg-white rounded-lg border border-gray-200 hover:border-purple-400 hover:shadow-md transition-all group">
                <div>
                    <div class="font-semibold text-gray-900 group-hover:text-purple-600">Sièges par Parti</div>
                    <div class="text-xs text-gray-500 mt-1">Répartition des sièges</div>
                </div>
                <svg class="w-5 h-5 text-gray-400 group-hover:text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </a>

            {{-- Export Détails Communes --}}
            <a href="{{ route('export.communales.details-csv', ['election_id' => $election->id]) }}" 
               class="flex items-center justify-between p-4 bg-white rounded-lg border border-gray-200 hover:border-indigo-400 hover:shadow-md transition-all group">
                <div>
                    <div class="font-semibold text-gray-900 group-hover:text-indigo-600">Détails Communes</div>
                    <div class="text-xs text-gray-500 mt-1">Résultats par arrondissement</div>
                </div>
                <svg class="w-5 h-5 text-gray-400 group-hover:text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </a>

            {{-- Export Arrondissements avec Candidats --}}
            <a href="{{ route('export.communales.arrondissements-csv', ['election_id' => $election->id]) }}" 
               class="flex items-center justify-between p-4 bg-white rounded-lg border border-gray-200 hover:border-teal-400 hover:shadow-md transition-all group">
                <div>
                    <div class="font-semibold text-gray-900 group-hover:text-teal-600">Détails Arrondissements</div>
                    <div class="text-xs text-gray-500 mt-1">Avec candidats élus</div>
                </div>
                <svg class="w-5 h-5 text-gray-400 group-hover:text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </a>

            {{-- ✅ NOUVEAU : Export Liste Élus Simple --}}
            <a href="{{ route('export.communales.candidats-elus', ['election_id' => $election->id]) }}" 
               class="flex items-center justify-between p-4 bg-white rounded-lg border-2 border-green-300 hover:border-green-500 hover:shadow-lg transition-all group">
                <div>
                    <div class="font-semibold text-gray-900 group-hover:text-green-600 flex items-center gap-2">
                        <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                        </svg>
                        Liste Élus (Simple)
                    </div>
                    <div class="text-xs text-gray-500 mt-1">Candidats par parti et localisation</div>
                </div>
                <svg class="w-5 h-5 text-green-400 group-hover:text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </a>

            {{-- ✅ NOUVEAU : Export Liste Élus Détaillé --}}
            <a href="{{ route('export.communales.candidats-elus-detailles', ['election_id' => $election->id]) }}" 
               class="flex items-center justify-between p-4 bg-white rounded-lg border-2 border-blue-300 hover:border-blue-500 hover:shadow-lg transition-all group">
                <div>
                    <div class="font-semibold text-gray-900 group-hover:text-blue-600 flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-6-3a2 2 0 11-4 0 2 2 0 014 0zm-2 4a5 5 0 00-4.546 2.916A5.986 5.986 0 0010 16a5.986 5.986 0 004.546-2.084A5 5 0 0010 11z" clip-rule="evenodd"/>
                        </svg>
                        Liste Élus (Détaillé)
                    </div>
                    <div class="text-xs text-gray-500 mt-1">Avec quotient et mode attribution</div>
                </div>
                <svg class="w-5 h-5 text-blue-400 group-hover:text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </a>
        </div>

        {{-- Info exports --}}
        <div class="mt-4 bg-white bg-opacity-60 rounded-lg p-3 border border-blue-200">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="text-xs text-gray-700">
                    <p class="font-medium mb-1">Tous les exports sont au format CSV (compatible Excel)</p>
                    <ul class="space-y-0.5 text-gray-600">
                        <li>• <strong>Liste Élus Simple</strong> : Tous les candidats élus avec leur parti et localisation</li>
                        <li>• <strong>Liste Élus Détaillé</strong> : Informations enrichies (quotient communal, mode d'attribution, statistiques)</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    {{-- Matrice des résultats par commune --}}
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">📊 Matrice des Résultats par Commune</h3>
                    <p class="text-sm text-gray-600 mt-1">Voix et pourcentages par commune et par entité politique</p>
                </div>
            </div>
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
            <form method="GET" action="{{ route('rapports.communales') }}">
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
            <form x-ref="compileForm" method="GET" action="{{ route('rapports.communales') }}">
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
                    Étape 1 : Seuil d'éligibilité national
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
                    Étape 2 : Répartition des Sièges
                </h3>
                <p class="text-sm text-gray-600 mt-1">Attribution selon les Articles 186-187 du Code Électoral</p>
            </div>

            {{-- Récapitulatif national --}}
            <div class="p-6 border-b border-gray-200">
                <h4 class="font-bold text-lg text-gray-900 mb-4">📊 Récapitulatif National des Sièges</h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entité Politique</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase bg-benin-green-50">Total Sièges</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">% National</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Communes Majoritaires</th>
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
                                        $communesMajoritaires = $compilation['communes_majoritaires'][$entiteId] ?? 0;
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
                                        <td class="px-6 py-4 text-center">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-purple-100 text-purple-800">
                                                {{ $communesMajoritaires }}
                                            </span>
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
                                <td class="px-6 py-4 text-center">-</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- Détails par commune (échantillon) --}}
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="font-bold text-lg text-gray-900">🏘️ Détails par Commune (10 premières)</h4>
                    <div class="text-sm text-gray-600">
                        <i class="fas fa-info-circle text-blue-500"></i>
                        Consultez les exports CSV pour voir toutes les communes
                    </div>
                </div>

                <div class="space-y-3">
                    @foreach(array_slice($compilation['repartition'], 0, 10, true) as $communeId => $rep)
                        @if($rep['nombre_sieges'] > 0)
                        <div class="border rounded-lg p-4 bg-gray-50 hover:bg-gray-100 transition-colors">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <span class="font-semibold text-gray-900 text-lg">{{ $rep['info']->nom }}</span>
                                    <span class="text-sm text-gray-600 ml-3">{{ $rep['info']->departement_nom }}</span>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="text-right">
                                        <div class="text-xs text-gray-500">Population</div>
                                        <div class="font-semibold">{{ number_format($rep['population']) }}</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs text-gray-500">Sièges</div>
                                        <div class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-blue-100 text-blue-800">
                                            {{ $rep['nombre_sieges'] }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Arrondissements --}}
                            @if(!empty($rep['repartition_arrondissements']))
                                <div class="space-y-2">
                                    @foreach($rep['repartition_arrondissements'] as $arrId => $arrData)
                                        <div class="bg-white rounded-lg p-3 border border-gray-200">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="font-medium text-gray-800">{{ $arrData['arrondissement_nom'] }}</span>
                                                <span class="text-xs text-gray-500">{{ $arrData['sieges_arrondissement'] }} sièges</span>
                                            </div>
                                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                                @foreach($arrData['listes'] as $entiteId => $liste)
                                                    @if(($liste['sieges'] ?? 0) > 0)
                                                        @php
                                                            $entite = $entitesById[$entiteId] ?? null;
                                                        @endphp
                                                        @if($entite)
                                                        <div class="bg-benin-green-50 rounded p-2 border border-benin-green-200">
                                                            <div class="font-semibold text-sm text-benin-green-900">{{ $entite->sigle }}</div>
                                                            <div class="text-benin-green-700 font-bold">{{ $liste['sieges'] }} siège(s)</div>
                                                            <div class="text-xs text-gray-600">{{ number_format($liste['pourcentage'], 1) }}%</div>
                                                        </div>
                                                        @endif
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Villages non saisis --}}
        @if(!empty($villagesNonSaisis) && count($villagesNonSaisis) > 0)
        <div class="bg-white rounded-xl shadow-sm overflow-hidden border-l-4 border-yellow-500">
            <div class="p-6 border-b border-gray-200 bg-yellow-50">
                <h3 class="text-xl font-bold text-gray-900">
                    <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                    Villages/Quartiers Non Saisis
                </h3>
                <p class="text-sm text-gray-600 mt-1">{{ count($villagesNonSaisis) }} villages/quartiers n'ont pas encore de PV validé</p>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach(array_slice($villagesNonSaisis, 0, 15) as $village)
                        <div class="flex items-start gap-2 p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                            <i class="fas fa-map-marker-alt text-yellow-600 mt-1"></i>
                            <div class="text-sm">
                                <div class="font-medium text-gray-900">{{ $village->village_quartier_nom }}</div>
                                <div class="text-xs text-gray-600">
                                    {{ $village->arrondissement_nom }} • {{ $village->commune_nom }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if(count($villagesNonSaisis) > 15)
                    <div class="mt-4 text-center text-sm text-gray-600">
                        <i class="fas fa-info-circle text-blue-500"></i>
                        Et {{ count($villagesNonSaisis) - 15 }} autres villages/quartiers non affichés
                    </div>
                @endif
            </div>
        </div>
        @endif
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