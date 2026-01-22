@extends('layouts.admin')

@section('title', 'Résultats Communales')

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
            <h1 class="text-3xl font-bold text-gray-900">📊 Résultats Élections Communales</h1>
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

    <!-- Info Seuil -->
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-gray-700">Seuil d'éligibilité national</h3>
                <p class="text-xs text-gray-600 mt-1">
                    Seuls les partis ayant obtenu ≥ 10% au plan national participent à l'attribution des sièges (Art.184)
                </p>
            </div>
            <div class="flex items-center gap-4 text-sm">
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded bg-red-500"></div>
                    <span>&lt; 10%</span>
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
                <p class="text-sm text-gray-600 mt-1">Voix et % par commune</p>
            </div>
            <a href="{{ route('export.communales.matrice', ['election_id' => $election->id]) }}"
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
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Population</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Sièges</th>
                        @foreach($data['entites'] as $entite)
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                <div class="font-bold">{{ $entite->sigle ?: $entite->nom }}</div>
                            </th>
                        @endforeach
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase bg-gray-100">Total</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @php $totalPopulation = 0; $totalSieges = 0; @endphp
                    @foreach($data['communes'] as $commune)
                        @php
                            $population = $data['matrice'][$commune->id]['population'] ?? 0;
                            $sieges = $data['matrice'][$commune->id]['nombre_sieges'] ?? 0;
                            $totalPopulation += $population;
                            $totalSieges += $sieges;
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap sticky left-0 bg-white z-10">
                                <div class="text-sm font-medium text-gray-900">{{ $commune->nom }}</div>
                                <div class="text-xs text-gray-500">{{ $commune->departement_nom }}</div>
                            </td>
                            <td class="px-4 py-3 text-center text-sm text-gray-700">{{ number_format($population) }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                                    {{ $sieges }}
                                </span>
                            </td>
                            @foreach($data['entites'] as $entite)
                                @php
                                    $result = $data['matrice'][$commune->id]['resultats'][$entite->id] ?? ['voix' => 0, 'pourcentage' => 0];
                                @endphp
                                <td class="px-4 py-3 text-center">
                                    <div class="inline-block px-3 py-2 rounded-lg">
                                        <div class="font-bold text-sm text-gray-900">{{ number_format($result['voix']) }}</div>
                                        <div class="text-xs text-gray-600">({{ number_format($result['pourcentage'], 2) }}%)</div>
                                    </div>
                                </td>
                            @endforeach
                            <td class="px-4 py-3 text-right font-semibold text-gray-900 bg-gray-50">
                                {{ number_format($data['matrice'][$commune->id]['total_voix'] ?? 0) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-100 font-bold sticky bottom-0 z-10">
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900 sticky left-0 bg-gray-100 z-20">TOTAL NATIONAL</td>
                        <td class="px-4 py-3 text-center text-sm text-gray-900">{{ number_format($totalPopulation) }}</td>
                        <td class="px-4 py-3 text-center text-sm">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-blue-200 text-blue-900">
                                {{ $totalSieges }}
                            </span>
                        </td>
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
                        <td class="px-4 py-3 text-right text-sm bg-gray-200">{{ number_format($data['total_voix_national'] ?? 0) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Bouton compilation --}}
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

    {{-- Résultats compilation --}}
    @if($compilation)

        {{-- Eligibilité --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50">
                <h3 class="text-xl font-bold text-gray-900">
                    <i class="fas fa-check-circle text-blue-600 mr-2"></i>
                    Étape 1 : Seuil d'éligibilité (Art.184)
                </h3>
                <p class="text-sm text-gray-600 mt-1">≥ 10% des suffrages exprimés au plan national</p>
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

        {{-- Récap National sièges --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-purple-50 to-pink-50">
                <h3 class="text-xl font-bold text-gray-900">
                    <i class="fas fa-chair text-purple-600 mr-2"></i>
                    Attribution des sièges (Conforme Art.183-187)
                </h3>
                <p class="text-sm text-gray-600 mt-1">Quotient démographique + Attribution par arrondissement</p>
            </div>

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
                                @if(($sieges['sieges_total'] ?? 0) > 0)
                                    @php
                                        $entite = collect($compilation['data']['entites'])->firstWhere('id', $entiteId);
                                        $totalGeneral += $sieges['sieges_total'];
                                        $pctNational = $compilation['data']['totaux_par_entite'][$entiteId]['pourcentage_national'] ?? 0;
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 font-medium text-gray-900">
                                            {{ $entite->nom ?? "Entité $entiteId" }}
                                            <span class="text-sm text-gray-500">({{ $entite->sigle ?? '' }})</span>
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

                {{-- Boutons export --}}
                <div class="mt-6 flex flex-wrap gap-2 justify-center">
                    <a href="{{ route('export.communales.matrice', ['election_id' => $election->id]) }}"
                       class="px-4 py-2 bg-gray-900 text-white rounded-lg text-sm font-semibold hover:bg-gray-800 inline-flex items-center gap-2">
                        <i class="fas fa-table"></i>
                        Matrice Résultats
                    </a>

                    <a href="{{ route('export.communales.sieges', ['election_id' => $election->id]) }}"
                       class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 inline-flex items-center gap-2">
                        <i class="fas fa-chair"></i>
                        Total Sièges
                    </a>

                    <a href="{{ route('export.communales.details', ['election_id' => $election->id]) }}"
                       class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700 inline-flex items-center gap-2">
                        <i class="fas fa-city"></i>
                        Détails Communes
                    </a>

                    <a href="{{ route('export.communales.arrondissements', ['election_id' => $election->id]) }}"
                       class="px-4 py-2 bg-teal-700 text-white rounded-lg text-sm font-semibold hover:bg-teal-800 inline-flex items-center gap-2">
                        <i class="fas fa-users"></i>
                        Détails Arrondissements (avec candidats)
                    </a>
                </div>
            </div>

            {{-- Détails par commune --}}
            <div class="p-6">
                <h4 class="font-bold text-lg text-gray-900 mb-4">🏘️ Détails par Commune ({{ count($compilation['repartition']) }} communes)</h4>

                <div class="space-y-4">
                    @foreach($compilation['repartition'] as $communeId => $rep)
                        @if(($rep['nombre_sieges'] ?? 0) > 0)
                        <div class="border-2 rounded-lg overflow-hidden bg-white" x-data="{ open: false }">
                            <div @click="open = !open"
                                 class="bg-gradient-to-r from-benin-green-50 to-blue-50 p-4 cursor-pointer hover:bg-benin-green-100 transition-colors">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3 flex-1">
                                        <i :class="open ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"
                                           class="text-benin-green-600 transition-transform"></i>
                                        <div>
                                            <h5 class="font-bold text-xl text-gray-900">{{ $rep['info']->nom }}</h5>
                                            <p class="text-sm text-gray-600 mt-1">
                                                {{ $rep['info']->departement_nom }} •
                                                Population: {{ number_format($rep['population'] ?? 0) }} hab. •
                                                Sièges à pourvoir: {{ $rep['nombre_sieges'] ?? 0 }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs text-gray-600">Quotient communal (Art.183)</div>
                                        <div class="text-lg font-bold text-benin-green-600">
                                            {{ number_format($rep['quotient_communal'] ?? 0, 2) }} hab/siège
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div x-show="open" x-collapse class="border-t-2 border-gray-200">

                                {{-- Arrondissements --}}
                                @if(!empty($rep['repartition_arrondissements']))
                                <div class="p-4 bg-white">
                                    <h6 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
                                        <i class="fas fa-map-marker-alt text-benin-green-600"></i>
                                        Attribution par arrondissement (Art.184-187)
                                    </h6>

                                    <div class="space-y-3">
                                        @foreach($rep['repartition_arrondissements'] as $arrId => $arrData)
                                            @php
                                                $details = $arrData['details_repartition'] ?? [];
                                                $arrondissement = $details['arrondissement'] ?? null;
                                                $populationArr = $arrondissement ? $arrondissement->population : 0;
                                            @endphp
                                            <div class="rounded-lg border-2 border-benin-green-200 overflow-hidden" x-data="{ arrOpen: false }">

                                                <div @click="arrOpen = !arrOpen"
                                                     class="p-3 bg-benin-green-50 cursor-pointer hover:bg-benin-green-100 transition-colors">
                                                    <div class="flex justify-between items-center">
                                                        <div class="flex items-center gap-2 flex-1">
                                                            <i :class="arrOpen ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"
                                                               class="text-benin-green-600 text-sm"></i>
                                                            <div>
                                                                <div class="font-bold text-gray-900">
                                                                    📍 {{ $arrData['arrondissement_nom'] ?? 'Arrondissement' }}
                                                                </div>
                                                                <div class="text-xs text-gray-600 mt-0.5">
                                                                    Pop: {{ number_format($populationArr) }} hab •
                                                                    Sièges: {{ $arrData['sieges_arrondissement'] ?? 0 }} •
                                                                    Voix: {{ number_format($arrData['total_voix'] ?? 0) }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="text-2xl font-bold text-benin-green-600">
                                                            {{ $arrData['sieges_attribues'] ?? 0 }} 🪑
                                                        </div>
                                                    </div>
                                                </div>

                                                <div x-show="arrOpen" x-collapse class="p-3 bg-white">
                                                    @if(!empty($arrData['listes']))
                                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                            @foreach($arrData['listes'] as $entiteId => $listeData)
                                                                @if(($listeData['sieges'] ?? 0) > 0)
                                                                    @php
                                                                        $entite = collect($compilation['data']['entites'])->firstWhere('id', (int)$entiteId);
                                                                    @endphp
                                                                    <div class="bg-gradient-to-br from-benin-green-50 to-white rounded-lg p-3 border border-benin-green-300">
                                                                        <div class="flex justify-between items-start mb-2">
                                                                            <div class="flex-1">
                                                                                <div class="font-bold text-gray-900 flex items-center gap-1">
                                                                                    <i class="fas fa-flag text-benin-green-600 text-xs"></i>
                                                                                    {{ $entite->sigle ?: $entite->nom }}
                                                                                </div>
                                                                                <div class="text-xs text-gray-600 mt-1">
                                                                                    Voix: <b>{{ number_format($listeData['voix'] ?? 0) }}</b>
                                                                                    ({{ number_format($listeData['pourcentage'] ?? 0, 2) }}%)
                                                                                </div>
                                                                            </div>
                                                                            <div class="text-right">
                                                                                <div class="text-2xl font-bold text-benin-green-600">
                                                                                    {{ $listeData['sieges'] }}
                                                                                </div>
                                                                                <div class="text-xs text-gray-500">siège(s)</div>
                                                                            </div>
                                                                        </div>

                                                                        {{-- Mode d'attribution --}}
                                                                        @php
                                                                            $mode = '';
                                                                            $siegesArr = $arrData['sieges_arrondissement'] ?? 0;
                                                                            $pct = $listeData['pourcentage'] ?? 0;
                                                                            if ($siegesArr == 1) {
                                                                                $mode = 'Uninominal (Art.186)';
                                                                            } elseif ($pct >= 50) {
                                                                                $mode = 'Majorité ≥50% (Art.187.1)';
                                                                            } elseif ($pct >= 40) {
                                                                                $mode = 'Majorité ≥40% (Art.187.2)';
                                                                            } else {
                                                                                $mode = 'Proportionnelle (Art.187.3)';
                                                                            }
                                                                        @endphp
                                                                        <div class="text-xs text-blue-600 bg-blue-50 px-2 py-1 rounded mt-2">
                                                                            <i class="fas fa-info-circle"></i> {{ $mode }}
                                                                        </div>

                                                                        {{-- Candidats élus --}}
                                                                        @if(!empty($listeData['candidats']))
                                                                            <div class="mt-3 pt-2 border-t border-benin-green-200">
                                                                                <div class="text-xs font-semibold text-gray-700 mb-2 flex items-center gap-1">
                                                                                    <i class="fas fa-user-check text-benin-green-600"></i>
                                                                                    Candidats élus (Art.187.7) :
                                                                                </div>
                                                                                <div class="space-y-1">
                                                                                    @foreach($listeData['candidats'] as $candidat)
                                                                                        <div class="bg-white rounded p-2 border border-benin-green-100">
                                                                                            <div class="flex items-start gap-2">
                                                                                                <div class="w-6 h-6 bg-benin-green-600 text-white rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">
                                                                                                    {{ $candidat['position'] ?? '?' }}
                                                                                                </div>
                                                                                                <div class="flex-1 min-w-0">
                                                                                                    <div class="font-bold text-xs text-gray-900 truncate" title="{{ $candidat['titulaire'] ?? 'Non renseigné' }}">
                                                                                                        👤 {{ $candidat['titulaire'] ?? 'Non renseigné' }}
                                                                                                    </div>
                                                                                                    @if(!empty($candidat['suppleant']))
                                                                                                        <div class="text-xs text-gray-600 truncate" title="Suppléant : {{ $candidat['suppleant'] }}">
                                                                                                            Suppléant : {{ $candidat['suppleant'] }}
                                                                                                        </div>
                                                                                                    @endif
                                                                                                    @if(!empty($candidat['no']))
                                                                                                        <div class="text-xs text-gray-500">
                                                                                                            N° {{ $candidat['no'] }}
                                                                                                        </div>
                                                                                                    @endif
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                    @endforeach
                                                                                </div>
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                @endif
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <p class="text-sm text-gray-500 italic">Aucun siège attribué dans cet arrondissement</p>
                                                    @endif
                                                </div>

                                            </div>
                                        @endforeach
                                    </div>

                                </div>
                                @endif

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