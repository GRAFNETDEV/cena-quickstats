@extends('layouts.admin')

@section('title', 'Rapports - Élections Communales')

@section('breadcrumb')
    <span class="text-gray-400">Rapports</span>
    <i class="fas fa-chevron-right text-xs text-gray-400"></i>
    <span class="text-gray-900 font-semibold">Élections Communales</span>
@endsection

@section('content')
<div class="space-y-6" x-data="rapportApp()">

    {{-- En-tête avec bouton PDF --}}
    <div class="bg-gradient-to-r from-benin-green-600 to-benin-yellow-500 rounded-xl p-6 text-white shadow-lg">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <h1 class="text-3xl font-bold flex items-center gap-3">
                    <i class="fas fa-file-alt"></i>
                    Rapport des Résultats – Élections Communales
                </h1>
                <p class="text-benin-green-100 mt-2">
                    {{ $election->nom ?? 'Élection Communale' }}
                </p>
            </div>
            
            <div class="flex items-center gap-4">
                {{-- Total voix --}}
                <div class="text-right">
                    <div class="bg-white/20 rounded-lg px-4 py-2">
                        <div class="text-sm text-benin-green-100">Total voix</div>
                        <div class="text-2xl font-bold">{{ number_format($totalVoixScope, 0, ',', ' ') }}</div>
                    </div>
                </div>
                
                {{-- Bouton PDF en haut --}}
                <a href="{{ route('rapports.communales.pdf', array_merge(request()->all())) }}"
                   target="_blank"
                   class="px-6 py-3 bg-red-600 text-white rounded-xl font-bold hover:bg-red-700 shadow-lg hover:shadow-xl transform hover:scale-105 transition-all inline-flex items-center gap-3 border-2 border-white/30">
                    <i class="fas fa-file-pdf text-2xl"></i>
                    <div class="text-left">
                        <div class="text-sm font-normal">Télécharger</div>
                        <div class="text-lg font-bold leading-tight">PDF</div>
                    </div>
                </a>
            </div>
        </div>
    </div>

    {{-- Carte de configuration --}}
    <div class="bg-white rounded-xl shadow-sm border-2 border-gray-200">
        <div class="p-6 border-b border-gray-200 bg-gray-50">
            <div class="flex items-center gap-3">
                <i class="fas fa-sliders-h text-2xl text-benin-green-600"></i>
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Configuration du Rapport</h2>
                    <p class="text-sm text-gray-600">Sélectionnez le niveau de détail souhaité</p>
                </div>
            </div>
        </div>

        <form method="GET" action="{{ route('rapports.communales') }}" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                
                {{-- Niveau --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-layer-group text-benin-green-600"></i>
                        Niveau de localisation
                    </label>
                    <select name="niveau" 
                            @change="onNiveauChange($event.target.value)"
                            class="w-full border-2 border-gray-300 rounded-lg px-4 py-2.5 focus:border-benin-green-500 focus:ring-2 focus:ring-benin-green-200 transition-all">
                        <option value="national" {{ $niveau === 'national' ? 'selected' : '' }}>🇧🇯 National</option>
                        <option value="departement" {{ $niveau === 'departement' ? 'selected' : '' }}>📍 Département</option>
                        <option value="commune" {{ $niveau === 'commune' ? 'selected' : '' }}>🏘️ Commune</option>
                        <option value="arrondissement" {{ $niveau === 'arrondissement' ? 'selected' : '' }}>📌 Arrondissement</option>
                    </select>
                </div>

                {{-- Département --}}
                <div x-show="showDepartement" x-transition>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-map-marked-alt text-blue-600"></i>
                        Département
                    </label>
                    <select name="departement_id" 
                            @change="onDepartementChange($event.target.value)"
                            class="w-full border-2 border-gray-300 rounded-lg px-4 py-2.5 focus:border-benin-green-500 focus:ring-2 focus:ring-benin-green-200 transition-all">
                        <option value="">-- Sélectionner --</option>
                        @foreach($departements as $d)
                            <option value="{{ $d->id }}" {{ (int)$filters['departement_id'] === (int)$d->id ? 'selected' : '' }}>
                                {{ $d->nom }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Commune --}}
                <div x-show="showCommune" x-transition>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-city text-indigo-600"></i>
                        Commune
                    </label>
                    <select name="commune_id" 
                            @change="onCommuneChange($event.target.value)"
                            class="w-full border-2 border-gray-300 rounded-lg px-4 py-2.5 focus:border-benin-green-500 focus:ring-2 focus:ring-benin-green-200 transition-all">
                        <option value="">-- Sélectionner --</option>
                        @foreach($communesRef as $c)
                            <option value="{{ $c->id }}" {{ (int)$filters['commune_id'] === (int)$c->id ? 'selected' : '' }}>
                                {{ $c->nom }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Arrondissement --}}
                <div x-show="showArrondissement" x-transition>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-map-pin text-purple-600"></i>
                        Arrondissement
                    </label>
                    <select name="arrondissement_id" 
                            class="w-full border-2 border-gray-300 rounded-lg px-4 py-2.5 focus:border-benin-green-500 focus:ring-2 focus:ring-benin-green-200 transition-all">
                        <option value="">-- Sélectionner --</option>
                        @foreach($arrondissementsRef as $a)
                            <option value="{{ $a->id }}" {{ (int)$filters['arrondissement_id'] === (int)$a->id ? 'selected' : '' }}>
                                {{ $a->nom }}
                            </option>
                        @endforeach
                    </select>
                </div>

            </div>

            {{-- Boutons d'action --}}
            <div class="mt-6 flex flex-wrap gap-3 justify-between items-center">
                <div class="text-sm text-gray-600">
                    <i class="fas fa-info-circle text-blue-500"></i>
                    Sélectionnez un niveau puis cliquez sur "Actualiser le Rapport" ou "Générer PDF"
                </div>
                
                <div class="flex gap-3">
                    <button type="submit"
                            class="px-6 py-3 bg-gradient-to-r from-benin-green-600 to-benin-green-700 text-white rounded-lg font-semibold hover:shadow-lg transform hover:-translate-y-0.5 transition-all inline-flex items-center gap-2">
                        <i class="fas fa-sync-alt"></i>
                        Actualiser le Rapport
                    </button>

                    <a href="{{ route('rapports.communales.pdf', array_merge(request()->all())) }}"
                       target="_blank"
                       class="px-6 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg font-semibold hover:shadow-lg transform hover:-translate-y-0.5 transition-all inline-flex items-center gap-2">
                        <i class="fas fa-file-pdf text-xl"></i>
                        <span class="font-bold">Générer PDF</span>
                    </a>
                </div>
            </div>
        </form>
    </div>

    {{-- Aperçu du rapport --}}
    <div class="bg-white rounded-xl shadow-sm border-2 border-gray-200">
        <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-blue-50">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">📋 Aperçu du Rapport</h2>
                    <p class="text-sm text-gray-600 mt-1">{{ $titre }}</p>
                </div>
                <div class="text-right">
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Scope</div>
                    <div class="text-lg font-bold text-benin-green-600">{{ $titre }}</div>
                </div>
            </div>
        </div>

        <div class="p-6">
            {{-- Résumé par entité politique --}}
            <div class="mb-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <i class="fas fa-chart-pie text-benin-green-600"></i>
                    Résultats par Entité Politique
                </h3>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 border border-gray-200 rounded-lg overflow-hidden">
                        <thead class="bg-gradient-to-r from-benin-green-600 to-benin-green-700 text-white">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase">Parti/Entité</th>
                                <th class="px-4 py-3 text-right text-xs font-bold uppercase">Voix</th>
                                <th class="px-4 py-3 text-right text-xs font-bold uppercase">% Scope</th>
                                <th class="px-4 py-3 text-center text-xs font-bold uppercase">Éligibilité</th>
                                <th class="px-4 py-3 text-right text-xs font-bold uppercase">% National</th>
                                <th class="px-4 py-3 text-right text-xs font-bold uppercase bg-white/10">Sièges</th>
                                <th class="px-4 py-3 text-right text-xs font-bold uppercase bg-white/10">Communes Maj.</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @foreach($tableScope as $row)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-gray-900">{{ $row['entite']->sigle ?: $row['entite']->nom }}</div>
                                        <div class="text-xs text-gray-500">{{ $row['entite']->nom }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-700">
                                        {{ number_format($row['voix'], 0, ',', ' ') }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-blue-600">
                                        {{ number_format($row['pct'], 2, ',', '') }}%
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if($row['eligible_national'])
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800">
                                                <i class="fas fa-check-circle mr-1"></i>
                                                Éligible
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800">
                                                <i class="fas fa-times-circle mr-1"></i>
                                                Non éligible
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-600">
                                        {{ number_format($row['pct_national'], 2, ',', '') }}%
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-lg font-bold bg-benin-green-100 text-benin-green-800">
                                            {{ (int)$row['sieges'] }} 🪑
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="inline-flex items-center px-2 py-1 rounded-lg text-sm font-bold bg-blue-100 text-blue-800">
                                            {{ (int)($row['communes_majoritaires'] ?? 0) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100 font-bold">
                            <tr>
                                <td class="px-4 py-3 text-gray-900">TOTAL</td>
                                <td class="px-4 py-3 text-right text-gray-900">{{ number_format($totalVoixScope, 0, ',', ' ') }}</td>
                                <td class="px-4 py-3 text-right text-gray-900">100.00%</td>
                                <td class="px-4 py-3"></td>
                                <td class="px-4 py-3"></td>
                                <td class="px-4 py-3 text-right text-benin-green-600">
                                    {{ array_sum(array_column($tableScope, 'sieges')) }} 🪑
                                </td>
                                <td class="px-4 py-3"></td>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- Détails par commune/arrondissement --}}
            @if(!empty($communesBlocs))
                <div class="mt-8">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <i class="fas fa-building text-indigo-600"></i>
                        Détails par Commune ({{ count($communesBlocs) }} commune(s))
                    </h3>

                    <div class="space-y-4">
                        @foreach($communesBlocs as $cb)
                            <div class="border-2 border-gray-200 rounded-lg overflow-hidden" x-data="{ communeOpen: false }">
                                <div @click="communeOpen = !communeOpen" 
                                     class="bg-gradient-to-r from-indigo-50 to-blue-50 p-4 cursor-pointer hover:from-indigo-100 hover:to-blue-100 transition-colors">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3 flex-1">
                                            <i :class="communeOpen ? 'fas fa-chevron-down' : 'fas fa-chevron-right'" 
                                               class="text-indigo-600 transition-transform"></i>
                                            <div>
                                                <h4 class="text-lg font-bold text-gray-900">{{ $cb['commune']->nom }}</h4>
                                                <p class="text-sm text-gray-600">
                                                    {{ $cb['commune']->departement_nom ?? '' }} •
                                                    Population: {{ number_format($cb['population'], 0, ',', ' ') }} hab. •
                                                    Sièges: {{ (int)$cb['sieges'] }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-xs text-gray-500">Quotient communal</div>
                                            <div class="text-xl font-bold text-indigo-600">
                                                {{ number_format($cb['quotient_communal'], 2, ',', ' ') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div x-show="communeOpen" x-collapse class="p-4 bg-white border-t-2 border-gray-200">
                                    @foreach($cb['arrondissements'] as $arr)
                                        <div class="mb-4 last:mb-0 border border-gray-200 rounded-lg overflow-hidden">
                                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                                <div class="flex items-center justify-between">
                                                    <h5 class="font-bold text-gray-900 flex items-center gap-2">
                                                        <i class="fas fa-map-marker-alt text-purple-600"></i>
                                                        {{ $arr['arrondissement_nom'] }}
                                                    </h5>
                                                    <div class="text-sm text-gray-600">
                                                        Sièges: <b>{{ (int)$arr['sieges_arrondissement'] }}</b> •
                                                        Voix: <b>{{ number_format($arr['total_voix'], 0, ',', ' ') }}</b>
                                                    </div>
                                                </div>
                                                
                                                {{-- ✅ Affichage de la méthode d'attribution --}}
                                                @if(!empty($arr['methode_attribution']))
                                                    @php $methode = $arr['methode_attribution']; @endphp
                                                    <div class="mt-2 p-2 bg-blue-50 border border-blue-200 rounded text-xs">
                                                        <div class="flex items-start gap-2">
                                                            <i class="fas fa-calculator text-blue-600 mt-0.5"></i>
                                                            <div class="flex-1">
                                                                <div class="font-semibold text-blue-900">{{ $methode['description'] ?? '' }}</div>
                                                                @if(!empty($methode['details']))
                                                                    <div class="text-blue-700 mt-1">{{ $methode['details'] }}</div>
                                                                @endif
                                                                @if(isset($methode['quotient_electoral']) && $methode['quotient_electoral'] > 0)
                                                                    <div class="mt-1 font-bold text-blue-800">
                                                                        QE: {{ number_format($methode['quotient_electoral'], 2, ',', ' ') }}
                                                                        <span class="font-normal text-xs">(tous les suffrages exprimés / sièges à répartir)</span>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>

                                            @if(!empty($arr['listes']))
                                                <div class="p-3 bg-white">
                                                    <table class="min-w-full text-sm">
                                                        <thead class="bg-gray-50 border-b border-gray-200">
                                                            <tr>
                                                                <th class="px-3 py-2 text-left text-xs font-bold text-gray-700 uppercase">Parti</th>
                                                                <th class="px-3 py-2 text-right text-xs font-bold text-gray-700 uppercase">Voix</th>
                                                                <th class="px-3 py-2 text-right text-xs font-bold text-gray-700 uppercase">%</th>
                                                                <th class="px-3 py-2 text-right text-xs font-bold text-gray-700 uppercase">Sièges</th>
                                                                <th class="px-3 py-2 text-left text-xs font-bold text-gray-700 uppercase">Candidats élus</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-gray-100">
                                                            @foreach($arr['listes'] as $entiteId => $liste)
                                                                @if(($liste['sieges'] ?? 0) > 0)
                                                                    @php
                                                                        $entite = collect($entites)->firstWhere('id', $entiteId);
                                                                    @endphp
                                                                    <tr class="hover:bg-gray-50">
                                                                        <td class="px-3 py-2 font-semibold text-gray-900">
                                                                            {{ $entite ? ($entite->sigle ?: $entite->nom) : "Entité $entiteId" }}
                                                                        </td>
                                                                        <td class="px-3 py-2 text-right text-gray-700">
                                                                            {{ number_format($liste['voix'], 0, ',', ' ') }}
                                                                        </td>
                                                                        <td class="px-3 py-2 text-right text-blue-600 font-semibold">
                                                                            {{ number_format($liste['pourcentage'], 2, ',', '') }}%
                                                                        </td>
                                                                        <td class="px-3 py-2 text-right">
                                                                            <span class="inline-flex items-center px-2 py-1 rounded bg-benin-green-100 text-benin-green-800 font-bold">
                                                                                {{ (int)$liste['sieges'] }}
                                                                            </span>
                                                                        </td>
                                                                        <td class="px-3 py-2">
                                                                            @if(!empty($liste['candidats']))
                                                                                <div class="text-xs space-y-1">
                                                                                    @foreach($liste['candidats'] as $c)
                                                                                        <div class="flex items-center gap-1">
                                                                                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-benin-green-600 text-white text-xs font-bold">
                                                                                                {{ $c['position'] }}
                                                                                            </span>
                                                                                            <span class="text-gray-900">{{ $c['titulaire'] }}</span>
                                                                                        </div>
                                                                                    @endforeach
                                                                                </div>
                                                                            @else
                                                                                <span class="text-gray-400 text-xs">—</span>
                                                                            @endif
                                                                        </td>
                                                                    </tr>
                                                                @endif
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>

    {{-- Note sur le PDF --}}
    <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4">
        <div class="flex items-start gap-3">
            <i class="fas fa-info-circle text-2xl text-blue-600 mt-1"></i>
            <div class="flex-1">
                <h3 class="font-bold text-blue-900">📄 À propos du rapport PDF</h3>
                <p class="text-sm text-blue-800 mt-1">
                    Le rapport PDF généré contiendra également :
                </p>
                <ul class="text-sm text-blue-700 mt-2 space-y-1 ml-4">
                    <li class="flex items-center gap-2">
                        <i class="fas fa-check text-blue-600"></i>
                        La liste complète des villages/quartiers non saisis pour le scope sélectionné
                    </li>
                    <li class="flex items-center gap-2">
                        <i class="fas fa-check text-blue-600"></i>
                        Un watermark "VERSION PROVISOIRE - GRAFNET" sur toutes les pages
                    </li>
                    <li class="flex items-center gap-2">
                        <i class="fas fa-check text-blue-600"></i>
                        Tous les détails jusqu'au niveau arrondissement avec les candidats élus
                    </li>
                    <li class="flex items-center gap-2">
                        <i class="fas fa-check text-blue-600"></i>
                        Les méthodes d'attribution et quotients électoraux pour chaque arrondissement
                    </li>
                    <li class="flex items-center gap-2">
                        <i class="fas fa-check text-blue-600"></i>
                        Pagination automatique et mise en page professionnelle
                    </li>
                </ul>
            </div>
        </div>
    </div>

    {{-- Bouton PDF flottant en bas de page --}}
    <div class="flex justify-center">
        <a href="{{ route('rapports.communales.pdf', array_merge(request()->all())) }}"
           target="_blank"
           class="px-8 py-4 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-xl font-bold text-lg hover:shadow-2xl transform hover:scale-105 transition-all inline-flex items-center gap-3 border-4 border-red-800">
            <i class="fas fa-file-pdf text-3xl"></i>
            <div>
                <div class="text-sm font-normal">Télécharger le rapport complet en</div>
                <div class="text-2xl font-bold">PDF</div>
            </div>
            <i class="fas fa-arrow-down animate-bounce"></i>
        </a>
    </div>

</div>

<script>
function rapportApp() {
    return {
        niveau: '{{ $niveau }}',
        showDepartement: false,
        showCommune: false,
        showArrondissement: false,

        init() {
            this.updateVisibility();
        },

        onNiveauChange(niveau) {
            this.niveau = niveau;
            this.updateVisibility();
        },

        onDepartementChange(val) {
            // Auto-update visibility
        },

        onCommuneChange(val) {
            // Auto-update visibility
        },

        updateVisibility() {
            this.showDepartement = ['departement', 'commune', 'arrondissement'].includes(this.niveau);
            this.showCommune = ['commune', 'arrondissement'].includes(this.niveau);
            this.showArrondissement = this.niveau === 'arrondissement';
        }
    }
}
</script>
@endsection