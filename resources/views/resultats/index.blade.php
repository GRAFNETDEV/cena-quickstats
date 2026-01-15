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

            <button @click="exporterCSV" class="px-4 py-2 bg-benin-green-600 text-white rounded-lg hover:bg-benin-green-700">
                <i class="fas fa-file-csv mr-2"></i>CSV
            </button>
        </div>
    </div>

    <!-- Légende -->
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-700">Seuils d'éligibilité</h3>
            <div class="flex items-center gap-4 text-sm">
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded bg-red-500"></div>
                    <span>< 10%</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded bg-yellow-500"></div>
                    <span>10% - 19.99%</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded bg-green-500"></div>
                    <span>≥ 20% (Éligible)</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Étape 0 : Matrice des résultats --}}
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">📊 Matrice des Résultats par Circonscription</h3>
            <p class="text-sm text-gray-600 mt-1">Voix et pourcentages par circonscription et par entité politique (24 circonscriptions, Diaspora exclue)</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 sticky top-0">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase sticky left-0 bg-gray-50 z-10">
                            Circonscription
                        </th>
                        @foreach($data['entites'] as $entite)
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                <div class="font-bold">{{ $entite->sigle ?: $entite->nom }}</div>
                                {{-- ✅ ENLEVÉ : N°{{ $entite->numero_liste }} --}}
                            </th>
                        @endforeach
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase bg-gray-100">
                            Total
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($data['circonscriptions'] as $circ)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 sticky left-0 bg-white">
                                {{-- ✅ CHANGÉ : Format ordinal --}}
                                <div>
                                    @if($circ->numero == 1)
                                        1ère circonscription
                                    @else
                                        {{ $circ->numero }}ème circonscription
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500">{{ $circ->nombre_sieges_total }} sièges ({{ $circ->nombre_sieges_femmes }} F)</div>
                            </td>
                            @foreach($data['entites'] as $entite)
                                @php
                                    $result = $data['matrice'][$circ->id]['resultats'][$entite->id] ?? ['voix' => 0, 'pourcentage' => 0];
                                    $voix = $result['voix'];
                                    $pct = $result['pourcentage'];
                                    
                                    // Déterminer la couleur
                                    if ($pct >= 20) {
                                        $bgColor = 'bg-green-100 text-green-800';
                                        $borderColor = 'border-green-500';
                                    } elseif ($pct >= 10) {
                                        $bgColor = 'bg-yellow-100 text-yellow-800';
                                        $borderColor = 'border-yellow-500';
                                    } else {
                                        $bgColor = 'bg-red-100 text-red-800';
                                        $borderColor = 'border-red-500';
                                    }
                                @endphp
                                <td class="px-4 py-3 text-center">
                                    <div class="inline-block px-3 py-2 rounded-lg border-2 {{ $bgColor }} {{ $borderColor }}">
                                        <div class="font-bold text-sm">{{ number_format($voix) }}</div>
                                        <div class="text-xs">({{ number_format($pct, 2) }}%)</div>
                                    </div>
                                </td>
                            @endforeach
                            <td class="px-4 py-3 text-right font-semibold text-gray-900 bg-gray-50">
                                {{ number_format($data['matrice'][$circ->id]['total_voix']) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-100 font-bold">
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">TOTAL NATIONAL</td>
                        @foreach($data['entites'] as $entite)
                            @php
                                $totalVoix = $data['totaux_par_entite'][$entite->id]['voix'];
                                $pctMoyen = $data['totaux_par_entite'][$entite->id]['pourcentage_moyen'];
                            @endphp
                            <td class="px-4 py-3 text-center text-sm">
                                <div>{{ number_format($totalVoix) }}</div>
                                <div class="text-xs text-gray-600">({{ number_format($pctMoyen, 2) }}%)</div>
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
        {{-- ✅ Bouton Réinitialiser --}}
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
        {{-- ✅ Compilation avec "fake" temps d'attente 20s --}}
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

        {{-- état normal --}}
        <span x-show="!isCompiling" class="inline-flex items-center">
            <i class="fas fa-calculator mr-3"></i>
            COMPILER LES RÉSULTATS
        </span>

        {{-- état loading (sans 20s) --}}
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
        {{-- ÉTAPE 1 : Éligibilité --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50">
                <h3 class="text-xl font-bold text-gray-900">
                    <i class="fas fa-check-circle text-blue-600 mr-2"></i>
                    ÉTAPE 1 : Vérification d'Éligibilité
                </h3>
                <p class="text-sm text-gray-600 mt-1">Seuil : ≥ 20% dans les 24 circonscriptions (Diaspora exclue)</p>
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
                                    <span class="font-semibold">{{ number_format($elig['pourcentage_national'], 2) }}%</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Total voix</span>
                                    <span class="font-semibold">{{ number_format($elig['total_voix']) }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Circonscriptions qualifiées</span>
                                    <span class="font-semibold {{ $elig['nb_circonscriptions_qualifiees'] == 24 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $elig['nb_circonscriptions_qualifiees'] }} / 24
                                    </span>
                                </div>
                            </div>

                            @if(!$elig['eligible'])
                                <div class="mt-3 pt-3 border-t border-red-200">
                                    <p class="text-xs text-red-700 font-medium">Circonscriptions non qualifiées :</p>
                                    <p class="text-xs text-red-600 mt-1">
                                        {{ implode(', ', array_slice($elig['circonscriptions_non_qualifiees'], 0, 3)) }}
                                        @if(count($elig['circonscriptions_non_qualifiees']) > 3)
                                            et {{ count($elig['circonscriptions_non_qualifiees']) - 3 }} autres...
                                        @endif
                                    </p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ÉTAPE 2 & 3 : Répartition des Sièges --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-purple-50 to-pink-50">
                <h3 class="text-xl font-bold text-gray-900">
                    <i class="fas fa-chair text-purple-600 mr-2"></i>
                    ÉTAPES 2 & 3 : Répartition des Sièges
                </h3>
                <p class="text-sm text-gray-600 mt-1">Étape 2 : Sièges ordinaires (Quotient) • Étape 3 : Sièges femmes (Winner Takes All)</p>
            </div>

            {{-- Tableau récapitulatif --}}
            <div class="p-6 border-b border-gray-200">
                <h4 class="font-bold text-lg text-gray-900 mb-4">📊 Récapitulatif National</h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entité Politique</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Sièges Ordinaires</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Sièges Femmes</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase bg-benin-green-50">Total Sièges</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @php
                                $totalOrdinaires = 0;
                                $totalFemmes = 0;
                                $totalGeneral = 0;
                            @endphp
                            @foreach($compilation['sieges_totaux'] as $entiteId => $sieges)
                                @if($sieges['total_sieges'] > 0)
                                    @php
                                        $entite = collect($compilation['data']['entites'])->firstWhere('id', $entiteId);
                                        $totalOrdinaires += $sieges['sieges_ordinaires'];
                                        $totalFemmes += $sieges['sieges_femmes'];
                                        $totalGeneral += $sieges['total_sieges'];
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 font-medium text-gray-900">
                                            {{ $entite->nom }}
                                            <span class="text-sm text-gray-500">({{ $entite->sigle }})</span>
                                        </td>
                                        <td class="px-6 py-4 text-center font-semibold text-blue-600">
                                            {{ $sieges['sieges_ordinaires'] }}
                                        </td>
                                        <td class="px-6 py-4 text-center font-semibold text-pink-600">
                                            {{ $sieges['sieges_femmes'] }}
                                        </td>
                                        <td class="px-6 py-4 text-center text-xl font-bold text-benin-green-600 bg-benin-green-50">
                                            {{ $sieges['total_sieges'] }}
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100 font-bold">
                            <tr>
                                <td class="px-6 py-4 text-gray-900">TOTAL</td>
                                <td class="px-6 py-4 text-center text-blue-600">{{ $totalOrdinaires }}</td>
                                <td class="px-6 py-4 text-center text-pink-600">{{ $totalFemmes }}</td>
                                <td class="px-6 py-4 text-center text-xl text-benin-green-600 bg-benin-green-100">{{ $totalGeneral }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="mt-4 flex justify-center">
                    <a href="{{ route('resultats.export.sieges.csv') }}" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        <i class="fas fa-download mr-2"></i>
                        Exporter les Sièges (CSV)
                    </a>
                </div>
            </div>

            {{-- Détails par circonscription --}}
            <div class="p-6">
                <h4 class="font-bold text-lg text-gray-900 mb-4">🗺️ Détails par Circonscription</h4>
                
                <div class="space-y-4">
                    @foreach($compilation['repartition'] as $circId => $rep)
                        <div class="border rounded-lg p-4 bg-gray-50">
                            <div class="flex items-center justify-between mb-3">
                                <h5 class="font-bold text-gray-900">
                                    @if($rep['info']->numero == 1)
                                        1ère circonscription
                                    @else
                                        {{ $rep['info']->numero }}ème circonscription
                                    @endif
                                </h5>
                                <span class="text-sm text-gray-600">
                                    {{ $rep['info']->nombre_sieges_total }} sièges 
                                    ({{ $rep['info']->nombre_sieges_total - $rep['info']->nombre_sieges_femmes }} ordinaires + {{ $rep['info']->nombre_sieges_femmes }} femme)
                                </span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {{-- Sièges ordinaires --}}
                                <div class="bg-white rounded-lg p-3 border">
                                    <h6 class="text-sm font-semibold text-blue-600 mb-2">Sièges Ordinaires (Quotient)</h6>
                                    <div class="text-xs text-gray-600 mb-2">QE = {{ number_format($rep['quotient_electoral'], 2) }}</div>
                                    <div class="space-y-1">
                                        @foreach($rep['sieges_ordinaires'] as $entiteId => $nbSieges)
                                            @if($nbSieges > 0)
                                                @php
                                                    $entite = collect($compilation['data']['entites'])->firstWhere('id', $entiteId);
                                                @endphp
                                                <div class="flex justify-between text-sm">
                                                    <span>{{ $entite->sigle ?: $entite->nom }}</span>
                                                    <span class="font-semibold">{{ $nbSieges }}</span>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Siège femme --}}
                                <div class="bg-white rounded-lg p-3 border">
                                    <h6 class="text-sm font-semibold text-pink-600 mb-2">Siège Femme (Winner Takes All)</h6>
                                    @if($rep['siege_femme'])
                                        <div class="flex items-center justify-between">
                                            <span class="font-medium">{{ $rep['siege_femme']['entite_nom'] }}</span>
                                            <span class="px-2 py-1 bg-pink-100 text-pink-800 rounded-full text-xs font-semibold">
                                                1 siège
                                            </span>
                                        </div>
                                        <div class="text-xs text-gray-600 mt-1">
                                            {{ number_format($rep['siege_femme']['voix']) }} voix
                                        </div>
                                    @else
                                        <p class="text-sm text-gray-500">Aucun siège femme pour cette circonscription</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

</div>

<script>
function resultatsApp() {
    return {
        exporterCSV() {
            window.location.href = '{{ route("resultats.export.csv") }}';
        }
    }
}
</script>

<script>
function resultatsApp() {
    return {
        isCompiling: false,
        countdown: 20,
        timer: null,

        exporterCSV() {
            window.location.href = '{{ route("resultats.export.csv") }}';
        },

        lancerCompilation() {
            if (this.isCompiling) return;

            this.isCompiling = true;
            this.countdown = 20;

            // sécurité : si un timer existe déjà
            if (this.timer) clearInterval(this.timer);

            this.timer = setInterval(() => {
                this.countdown--;

                if (this.countdown <= 0) {
                    clearInterval(this.timer);
                    this.timer = null;

                    // Soumission réelle après 20s
                    this.$refs.compileForm.submit();
                }
            }, 1000);
        }
    }
}
</script>

@endsection