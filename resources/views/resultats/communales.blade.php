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
                            Population
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
                    @php
                        $totalPopulation = 0;
                        $totalSieges = 0;
                    @endphp
                    @foreach($data['communes'] as $commune)
                        @php
                            $population = $data['matrice'][$commune->id]['population'] ?? 0;
                            $sieges = $data['matrice'][$commune->id]['nombre_sieges'];
                            $totalPopulation += $population;
                            $totalSieges += $sieges;
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap sticky left-0 bg-white z-10">
                                <div class="text-sm font-medium text-gray-900">{{ $commune->nom }}</div>
                                <div class="text-xs text-gray-500">{{ $commune->departement_nom }}</div>
                            </td>
                            <td class="px-4 py-3 text-center text-sm text-gray-700">
                                {{ number_format($population) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                                    {{ $sieges }}
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
                <tfoot class="bg-gray-100 font-bold sticky bottom-0 z-10">
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900 sticky left-0 bg-gray-100 z-20">TOTAL NATIONAL</td>
                        <td class="px-4 py-3 text-center text-sm text-gray-900">
                            {{ number_format($totalPopulation) }}
                        </td>
                        <td class="px-4 py-3 text-center text-sm">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-blue-200 text-blue-900">
                                {{ $totalSieges }}
                            </span>
                        </td>
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
                    Étape 1 : Seuil d'éligibilité
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
                    Étapes 2-5 : Répartition des Sièges
                </h3>
                <p class="text-sm text-gray-600 mt-1">Quotient Électoral • Attribution au quotient • Plus fort reste • Répartition par arrondissement</p>
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
                    <div class="relative" x-data="{ exportOpen: false }">
                        <button @click="exportOpen = !exportOpen" 
                                class="px-8 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-xl hover:shadow-xl inline-flex items-center gap-2 text-lg font-semibold">
                            <i class="fas fa-download"></i>
                            Exporter les Résultats
                            <i class="fas fa-chevron-down text-sm transition-transform" :class="{ 'rotate-180': exportOpen }"></i>
                        </button>
                        
                        <div x-show="exportOpen" 
                             @click.away="exportOpen = false"
                             x-transition
                             class="absolute top-full mt-2 left-1/2 transform -translate-x-1/2 bg-white rounded-xl shadow-2xl border-2 border-gray-200 min-w-[320px] z-50"
                             style="display: none;">
                            
                            <div class="p-3 space-y-1">
                                {{-- Export 1 : Matrice --}}
                                <a href="{{ route('resultats.export.matrice.csv', ['election_id' => $election->id]) }}" 
                                   class="block px-4 py-3 text-sm text-gray-700 hover:bg-blue-50 rounded-lg flex items-center gap-3 transition-colors">
                                    <i class="fas fa-table text-blue-600 w-6 text-lg"></i>
                                    <div class="flex-1">
                                        <div class="font-bold text-gray-900">Matrice des Résultats</div>
                                        <div class="text-xs text-gray-500">Voix par commune et parti</div>
                                    </div>
                                    <i class="fas fa-download text-gray-400 text-xs"></i>
                                </a>
                                
                                {{-- Export 2 : Sièges --}}
                                <a href="{{ route('resultats.export.sieges.csv', ['election_id' => $election->id]) }}" 
                                   class="block px-4 py-3 text-sm text-gray-700 hover:bg-purple-50 rounded-lg flex items-center gap-3 transition-colors">
                                    <i class="fas fa-chair text-purple-600 w-6 text-lg"></i>
                                    <div class="flex-1">
                                        <div class="font-bold text-gray-900">Total Sièges par Parti</div>
                                        <div class="text-xs text-gray-500">Récapitulatif national</div>
                                    </div>
                                    <i class="fas fa-download text-gray-400 text-xs"></i>
                                </a>
                                
                                <div class="border-t border-gray-200 my-2"></div>
                                
                                {{-- Export 3 : Détails Communes ⭐ --}}
                                <a href="{{ route('resultats.export.details.csv', ['election_id' => $election->id]) }}" 
                                   class="block px-4 py-3 text-sm hover:bg-indigo-50 rounded-lg flex items-center gap-3 transition-colors bg-indigo-50 border-2 border-indigo-200">
                                    <i class="fas fa-city text-indigo-600 w-6 text-lg"></i>
                                    <div class="flex-1">
                                        <div class="font-bold text-indigo-900">Détails par Commune ({{ count($compilation['repartition']) }})</div>
                                        <div class="text-xs text-indigo-600">Quotient + Reste + Sièges par commune</div>
                                    </div>
                                    <i class="fas fa-download text-indigo-600"></i>
                                </a>
                                
                                {{-- Export 4 : Détails Arrondissements --}}
                                {{-- ⚠️ ROUTE NON DISPONIBLE - Décommentez après avoir ajouté la route dans web.php
                                <a href="{{ route('resultats.export.arrondissements.csv', ['election_id' => $election->id]) }}" 
                                   class="block px-4 py-3 text-sm text-gray-700 hover:bg-teal-50 rounded-lg flex items-center gap-3 transition-colors">
                                    <i class="fas fa-map-marker-alt text-teal-600 w-6 text-lg"></i>
                                    <div class="flex-1">
                                        <div class="font-bold text-gray-900">Détails par Arrondissement</div>
                                        <div class="text-xs text-gray-500">Avec noms des candidats élus</div>
                                    </div>
                                    <i class="fas fa-download text-gray-400 text-xs"></i>
                                </a>
                                --}}
                                
                                <div class="border-t border-gray-200 my-2"></div>
                                
                                {{-- Export Complet ZIP --}}
                                {{-- ⚠️ ROUTE NON DISPONIBLE - Décommentez après avoir ajouté la route dans web.php
                                <a href="{{ route('export.communales.complet', ['election_id' => $election->id]) }}" 
                                   class="block px-4 py-3 text-sm text-white bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 rounded-lg flex items-center gap-3 shadow-md">
                                    <i class="fas fa-file-archive w-6 text-lg"></i>
                                    <div class="flex-1">
                                        <div class="font-bold">Export Complet (ZIP)</div>
                                        <div class="text-xs text-green-100">Tous les exports en un fichier</div>
                                    </div>
                                    <i class="fas fa-download"></i>
                                </a>
                                --}}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Détails par commune - SYSTÈME PLIABLE --}}
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="font-bold text-lg text-gray-900">🏘️ Détails par Commune ({{ count($compilation['repartition']) }} communes)</h4>
                    <div class="flex gap-2">
                        {{-- Bouton Export Détails Communes --}}
                        <a href="{{ route('resultats.export.details.csv', ['election_id' => $election->id]) }}" 
                           class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 inline-flex items-center gap-2 text-sm shadow-sm">
                            <i class="fas fa-file-excel"></i>
                            <span>Exporter Détails Communes</span>
                        </a>
                        
                        {{-- Bouton Export Arrondissements avec Candidats --}}
                        {{-- ⚠️ ROUTE NON DISPONIBLE - Décommentez après avoir ajouté la route dans web.php
                        <a href="{{ route('resultats.export.arrondissements.csv', ['election_id' => $election->id]) }}" 
                           class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 inline-flex items-center gap-2 text-sm shadow-sm">
                            <i class="fas fa-users"></i>
                            <span>Exporter Candidats</span>
                        </a>
                        --}}
                        
                        {{-- Boutons Plier/Déplier --}}
                        <button @click="toggleAll(true)" 
                                class="px-3 py-2 text-sm bg-benin-green-100 text-benin-green-700 rounded hover:bg-benin-green-200 inline-flex items-center gap-1">
                            <i class="fas fa-chevron-down"></i>
                            <span>Tout déplier</span>
                        </button>
                        <button @click="toggleAll(false)" 
                                class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200 inline-flex items-center gap-1">
                            <i class="fas fa-chevron-up"></i>
                            <span>Tout replier</span>
                        </button>
                    </div>
                </div>

                <div class="space-y-4">
                    @foreach($compilation['repartition'] as $communeId => $rep)
                        @if($rep['nombre_sieges'] > 0)
                        <div class="border-2 rounded-lg overflow-hidden bg-white" x-data="{ open: false }">
                            {{-- En-tête de la commune (cliquable) --}}
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
                                                Population: {{ number_format($rep['population'] ?? 0) }} • 
                                                {{ $rep['nombre_sieges'] }} sièges
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs text-gray-600">Quotient Électoral</div>
                                        <div class="text-lg font-bold text-benin-green-600">
                                            {{ number_format($rep['quotient_electoral'], 2) }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Contenu pliable --}}
                            <div x-show="open" 
                                 x-collapse
                                 class="border-t-2 border-gray-200">
                                
                                {{-- Tableau récapitulatif commune --}}
                                <div class="p-4 bg-gray-50">
                                    <h6 class="text-sm font-bold text-gray-700 mb-3">📊 Répartition des sièges au niveau communal</h6>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full text-sm bg-white rounded-lg overflow-hidden">
                                            <thead class="bg-gray-100">
                                                <tr>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-600">Parti</th>
                                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-600">Voix</th>
                                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-600">%</th>
                                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-600 bg-blue-50">Quotient</th>
                                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-600 bg-orange-50">Reste</th>
                                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-600 bg-benin-green-50">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200">
                                                @php $totalSiegesCommune = 0; @endphp
                                                @foreach($rep['details'] as $entiteId => $detail)
                                                    @php
                                                        $entite = collect($compilation['data']['entites'])->firstWhere('id', $entiteId);
                                                        $totalSiegesCommune += $detail['sieges_total'];
                                                        $pctCommune = $compilation['data']['matrice'][$communeId]['total_voix'] > 0 
                                                            ? ($detail['voix'] / $compilation['data']['matrice'][$communeId]['total_voix']) * 100 
                                                            : 0;
                                                    @endphp
                                                    <tr class="{{ $detail['sieges_total'] > 0 ? 'bg-green-50' : '' }}">
                                                        <td class="px-4 py-3 font-medium text-gray-900">
                                                            @if($detail['sieges_total'] > 0)
                                                                <i class="fas fa-trophy text-benin-yellow-600 mr-1"></i>
                                                            @endif
                                                            {{ $entite->sigle ?: $entite->nom }}
                                                        </td>
                                                        <td class="px-4 py-3 text-center">{{ number_format($detail['voix']) }}</td>
                                                        <td class="px-4 py-3 text-center text-gray-600">{{ number_format($pctCommune, 2) }}%</td>
                                                        <td class="px-4 py-3 text-center bg-blue-50">
                                                            <span class="px-2 py-1 rounded-full text-xs font-bold bg-blue-200 text-blue-900">
                                                                {{ $detail['sieges_quotient'] }}
                                                            </span>
                                                        </td>
                                                        <td class="px-4 py-3 text-center bg-orange-50">
                                                            <span class="px-2 py-1 rounded-full text-xs font-bold bg-orange-200 text-orange-900">
                                                                {{ $detail['sieges_reste'] }}
                                                            </span>
                                                        </td>
                                                        <td class="px-4 py-3 text-center bg-benin-green-50">
                                                            <span class="px-3 py-1 rounded-full text-sm font-bold 
                                                                {{ $detail['sieges_total'] > 0 ? 'bg-benin-green-600 text-white' : 'bg-gray-200 text-gray-600' }}">
                                                                {{ $detail['sieges_total'] }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                            <tfoot class="bg-gray-100 font-bold">
                                                <tr>
                                                    <td colspan="5" class="px-4 py-3 text-right text-sm">TOTAL :</td>
                                                    <td class="px-4 py-3 text-center bg-benin-green-100">
                                                        <span class="text-lg font-bold text-benin-green-600">
                                                            {{ $totalSiegesCommune }} / {{ $rep['nombre_sieges'] }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>

                                {{-- ÉTAPE 5 : Répartition par arrondissement (GROUPÉ PAR ARRONDISSEMENT) --}}
                                @if(!empty($rep['repartition_arrondissements']))
                                    <div class="p-4 border-t-2 border-gray-200 bg-white">
                                        <h6 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
                                            <i class="fas fa-map-marker-alt text-benin-green-600"></i>
                                            Étape 5 : Répartition des Sièges par Arrondissement
                                            <span class="text-xs text-gray-500">(Projection des sièges gagnés au niveau communal)</span>
                                        </h6>
                                        
                                        <div class="space-y-3">
                                            @foreach($rep['repartition_arrondissements'] as $arrId => $arrData)
                                                <div class="bg-gradient-to-br from-white to-gray-50 rounded-lg border-2 border-benin-green-200 overflow-hidden"
                                                     x-data="{ arrOpen: false }">
                                                    
                                                    {{-- En-tête arrondissement (cliquable) --}}
                                                    <div @click="arrOpen = !arrOpen"
                                                         class="p-3 bg-benin-green-50 cursor-pointer hover:bg-benin-green-100 transition-colors">
                                                        <div class="flex justify-between items-center">
                                                            <div class="flex items-center gap-2 flex-1">
                                                                <i :class="arrOpen ? 'fas fa-chevron-down' : 'fas fa-chevron-right'" 
                                                                   class="text-benin-green-600 text-sm"></i>
                                                                <div>
                                                                    <div class="font-bold text-gray-900">
                                                                        📍 {{ $arrData['arrondissement_nom'] }}
                                                                    </div>
                                                                    <div class="text-xs text-gray-600 mt-0.5">
                                                                        Sièges disponibles : {{ $arrData['sieges_arrondissement'] }} • 
                                                                        Sièges attribués : {{ $arrData['sieges_attribues'] }}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="text-2xl font-bold text-benin-green-600">
                                                                {{ $arrData['sieges_attribues'] }} 🪑
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {{-- Détails par parti dans cet arrondissement --}}
                                                    <div x-show="arrOpen" 
                                                         x-collapse
                                                         class="p-3 bg-white">
                                                        @if(!empty($arrData['partis']))
                                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                                @foreach($arrData['partis'] as $entiteId => $partiData)
                                                                    @php
                                                                        $entite = collect($compilation['data']['entites'])->firstWhere('id', $entiteId);
                                                                    @endphp
                                                                    <div class="bg-gradient-to-br from-benin-green-50 to-white rounded-lg p-3 border border-benin-green-300">
                                                                        <div class="flex justify-between items-start mb-2">
                                                                            <div class="flex-1">
                                                                                <div class="font-bold text-gray-900 flex items-center gap-1">
                                                                                    <i class="fas fa-flag text-benin-green-600 text-xs"></i>
                                                                                    {{ $entite->sigle ?: $entite->nom }}
                                                                                </div>
                                                                            </div>
                                                                            <div class="text-right">
                                                                                <div class="text-2xl font-bold text-benin-green-600">
                                                                                    {{ $partiData['sieges'] }}
                                                                                </div>
                                                                                <div class="text-xs text-gray-500">siège(s)</div>
                                                                            </div>
                                                                        </div>
                                                                        
                                                                        {{-- ✅ NOUVEAU : Affichage des candidats élus --}}
                                                                        @if(!empty($partiData['candidats']))
                                                                            <div class="mt-3 pt-2 border-t border-benin-green-200">
                                                                                <div class="text-xs font-semibold text-gray-700 mb-2 flex items-center gap-1">
                                                                                    <i class="fas fa-user-check text-benin-green-600"></i>
                                                                                    Candidats élus :
                                                                                </div>
                                                                                <div class="space-y-1">
                                                                                    @foreach($partiData['candidats'] as $candidat)
                                                                                        <div class="bg-white rounded p-2 border border-benin-green-100">
                                                                                            <div class="flex items-start gap-2">
                                                                                                <div class="flex-shrink-0 w-6 h-6 bg-benin-green-600 text-white rounded-full flex items-center justify-center text-xs font-bold">
                                                                                                    {{ $candidat['position'] }}
                                                                                                </div>
                                                                                                <div class="flex-1 min-w-0">
                                                                                                    <div class="font-bold text-xs text-gray-900 truncate" title="{{ $candidat['titulaire'] ?? 'Non renseigné' }}">
                                                                                                        👤 {{ $candidat['titulaire'] ?? 'Non renseigné' }}
                                                                                                    </div>
                                                                                                    @if($candidat['suppleant'])
                                                                                                        <div class="text-xs text-gray-600 truncate" title="Suppléant : {{ $candidat['suppleant'] }}">
                                                                                                            Suppléant : {{ $candidat['suppleant'] }}
                                                                                                        </div>
                                                                                                    @endif
                                                                                                    @if($candidat['no'])
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
                                                                        
                                                                        <div class="mt-2 pt-2 border-t border-benin-green-200">
                                                                            <div class="text-xs text-gray-600">
                                                                                Voix obtenues : <span class="font-bold text-gray-900">{{ number_format($partiData['voix']) }}</span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
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
        },

        toggleAll(state) {
            // Utiliser Alpine pour déclencher l'ouverture/fermeture de tous les éléments
            document.querySelectorAll('[x-data]').forEach(el => {
                if (el.__x && el.__x.$data.open !== undefined) {
                    el.__x.$data.open = state;
                }
                if (el.__x && el.__x.$data.arrOpen !== undefined) {
                    el.__x.$data.arrOpen = state;
                }
            });
        }
    }
}
</script>

@endsection