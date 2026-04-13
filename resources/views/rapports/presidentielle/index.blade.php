@extends('layouts.admin')

@section('title', 'Rapports - Élection Présidentielle')

@section('breadcrumb')
    <span class="text-gray-400">Rapports</span>
    <i class="fas fa-chevron-right text-xs text-gray-400"></i>
    <span class="text-gray-900 font-semibold">Élection Présidentielle</span>
@endsection

@section('content')
@php
    $seuilPremierTour = (float) ($regles['seuil_premier_tour_pourcent'] ?? 50);
    $nbSecondTour = (int) ($regles['nombre_candidats_second_tour'] ?? 2);
@endphp

<style>
    .pr-page {
        --pr-primary: #8a1420;
        --pr-primary-2: #b71e2d;
        --pr-accent: #f2a900;
        --pr-ink: #14213d;
        --pr-muted: #5f6b7a;
        --pr-bg-soft: #f7f8fb;
        --pr-border: #dde2ea;
    }

    .pr-card {
        background: #fff;
        border: 1px solid var(--pr-border);
        border-radius: 14px;
        box-shadow: 0 8px 28px rgba(15, 23, 42, 0.06);
    }

    .pr-hero {
        border-radius: 16px;
        background: linear-gradient(110deg, var(--pr-primary) 0%, var(--pr-primary-2) 48%, #d34a2c 100%);
        color: #fff;
        box-shadow: 0 16px 34px rgba(138, 20, 32, 0.34);
        overflow: hidden;
    }

    .pr-hero-inner {
        position: relative;
        padding: 26px 28px;
        background:
            radial-gradient(circle at 87% -15%, rgba(255, 255, 255, 0.26) 0%, rgba(255, 255, 255, 0) 42%),
            radial-gradient(circle at -12% 120%, rgba(255, 205, 86, 0.18) 0%, rgba(255, 205, 86, 0) 55%);
    }

    .pr-title {
        font-size: 30px;
        line-height: 1.12;
        font-weight: 900;
        letter-spacing: 0.2px;
        margin: 0;
        color: #fff;
    }

    .pr-subtitle {
        color: rgba(255, 255, 255, 0.92);
        margin-top: 8px;
        font-size: 14px;
    }

    .pr-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(255, 255, 255, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.26);
        color: #fff;
        border-radius: 999px;
        padding: 7px 13px;
        font-weight: 700;
        font-size: 12px;
    }

    .pr-btn {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        border-radius: 12px;
        padding: 11px 16px;
        font-weight: 800;
        transition: all .2s ease;
        text-decoration: none;
    }

    .pr-btn-pdf {
        background: #fff;
        color: var(--pr-primary);
        border: 1px solid #fff;
    }

    .pr-btn-pdf:hover {
        background: #f8fafc;
        transform: translateY(-1px);
    }

    .pr-btn-main {
        background: linear-gradient(135deg, var(--pr-primary), #a51728);
        color: #fff;
        border: 1px solid #7d111c;
    }

    .pr-btn-main:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 22px rgba(138, 20, 32, 0.25);
    }

    .pr-btn-alt {
        background: #1e293b;
        color: #fff;
        border: 1px solid #0f172a;
    }

    .pr-btn-alt:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.25);
    }

    .pr-headline {
        color: var(--pr-ink);
        font-weight: 900;
        letter-spacing: 0.2px;
    }

    .pr-label {
        color: #334155;
        font-weight: 700;
        margin-bottom: 8px;
        display: block;
        font-size: 13px;
    }

    .pr-field {
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 10px 12px;
        background: #fff;
        color: #0f172a;
        font-size: 14px;
    }

    .pr-field:focus {
        outline: 2px solid rgba(138, 20, 32, 0.2);
        border-color: var(--pr-primary);
    }

    .pr-kpi {
        border-radius: 12px;
        padding: 16px 18px;
        border: 1px solid var(--pr-border);
        background: #fff;
    }

    .pr-kpi-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: var(--pr-muted);
        font-weight: 700;
    }

    .pr-kpi-value {
        margin-top: 6px;
        font-size: 31px;
        line-height: 1;
        color: #0f172a;
        font-weight: 900;
    }

    .pr-kpi-value-red {
        color: var(--pr-primary);
    }

    .pr-section-title {
        color: var(--pr-ink);
        font-size: 20px;
        font-weight: 900;
        margin: 0;
    }

    .pr-section-subtitle {
        color: var(--pr-muted);
        font-size: 13px;
        margin-top: 4px;
    }

    .pr-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .pr-table thead th {
        background: #f1f5f9;
        color: #334155;
        border-bottom: 1px solid #dbe2ea;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 800;
        padding: 12px 14px;
    }

    .pr-table tbody td {
        border-bottom: 1px solid #eef2f7;
        padding: 11px 14px;
        color: #0f172a;
        font-size: 13px;
    }

    .pr-table tbody tr:hover td {
        background: #f8fafc;
    }

    .pr-table tfoot td {
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        color: #0f172a;
        font-weight: 800;
        padding: 12px 14px;
        font-size: 12px;
    }

    .pr-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 4px 10px;
        font-size: 11px;
        font-weight: 800;
        border: 1px solid transparent;
    }

    .pr-badge-gray {
        background: #f1f5f9;
        color: #334155;
        border-color: #e2e8f0;
    }

    .pr-badge-green {
        background: #dcfce7;
        color: #166534;
        border-color: #bbf7d0;
    }

    .pr-badge-amber {
        background: #fef3c7;
        color: #92400e;
        border-color: #fde68a;
    }

    .pr-mini-card {
        border: 1px solid var(--pr-border);
        border-radius: 12px;
        padding: 12px 14px;
        background: #fff;
    }

    .pr-note {
        border-radius: 12px;
        border: 1px solid #f8c9c9;
        background: #fff7f7;
        padding: 14px 16px;
    }
</style>

<div class="pr-page space-y-6" x-data="rapportPresidentielleApp()">
    <div class="pr-hero">
        <div class="pr-hero-inner">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <div class="pr-pill mb-3">
                        <i class="fas fa-scale-balanced"></i>
                        Rapport légal et opérationnel
                    </div>
                    <h1 class="pr-title">Rapport des Résultats Présidentiels</h1>
                    <p class="pr-subtitle">{{ $election->nom ?? 'Élection présidentielle' }}</p>
                    <p class="pr-subtitle">{{ $titre }}</p>
                </div>

                <a href="{{ route('rapports.presidentielle.pdf', array_merge(request()->all())) }}"
                   target="_blank"
                   class="pr-btn pr-btn-pdf">
                    <i class="fas fa-file-pdf text-xl"></i>
                    <span>Télécharger le PDF</span>
                </a>
            </div>
        </div>
    </div>

    <div class="pr-card">
        <div class="p-6 border-b border-slate-200 bg-slate-50 rounded-t-[14px]">
            <h2 class="pr-headline text-xl flex items-center gap-2">
                <i class="fas fa-sliders text-[#8a1420]"></i>
                Configuration du rapport
            </h2>
            <p class="pr-section-subtitle">Choisis le scope d’analyse puis actualise la vue ou génère le PDF.</p>
        </div>

        <form method="GET" action="{{ route('rapports.presidentielle') }}" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="pr-label">Élection présidentielle</label>
                    <select name="election_id" class="pr-field">
                        @foreach($electionsPresidentielles as $elec)
                            <option value="{{ $elec->id }}" {{ (int)$election->id === (int)$elec->id ? 'selected' : '' }}>
                                {{ $elec->nom }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="pr-label">Niveau d'analyse</label>
                    <select name="niveau" @change="onNiveauChange($event.target.value)" class="pr-field">
                        <option value="national" {{ $niveau === 'national' ? 'selected' : '' }}>National</option>
                        <option value="departement" {{ $niveau === 'departement' ? 'selected' : '' }}>Département</option>
                        <option value="commune" {{ $niveau === 'commune' ? 'selected' : '' }}>Commune</option>
                    </select>
                </div>

                <div x-show="showDepartement" x-transition>
                    <label class="pr-label">Département</label>
                    <select name="departement_id" class="pr-field">
                        <option value="">Tous</option>
                        @foreach($departements as $dep)
                            <option value="{{ $dep->id }}" {{ (int)$filters['departement_id'] === (int)$dep->id ? 'selected' : '' }}>
                                {{ $dep->nom }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div x-show="showCommune" x-transition>
                    <label class="pr-label">Commune</label>
                    <select name="commune_id" class="pr-field">
                        <option value="">Toutes</option>
                        @foreach($communesRef as $com)
                            <option value="{{ $com->id }}" {{ (int)$filters['commune_id'] === (int)$com->id ? 'selected' : '' }}>
                                {{ $com->nom }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-3 justify-between items-center">
                <p class="text-sm text-slate-600">
                    Article 130: majorité absolue au 1er tour, sinon second tour entre les deux duos en tête.
                </p>
                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="pr-btn pr-btn-main">
                        <i class="fas fa-arrows-rotate"></i>
                        Actualiser le rapport
                    </button>
                    <a href="{{ route('rapports.presidentielle.pdf', array_merge(request()->all())) }}"
                       target="_blank"
                       class="pr-btn pr-btn-alt">
                        <i class="fas fa-file-pdf"></i>
                        Générer PDF
                    </a>
                </div>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="pr-kpi">
            <div class="pr-kpi-label">Voix du scope</div>
            <div class="pr-kpi-value">{{ number_format($totalVoixScope, 0, ',', ' ') }}</div>
        </div>
        <div class="pr-kpi">
            <div class="pr-kpi-label">Majorité absolue (scope)</div>
            <div class="pr-kpi-value pr-kpi-value-red">{{ number_format($majoriteAbsolueScopeVoix, 0, ',', ' ') }}</div>
        </div>
        <div class="pr-kpi">
            <div class="pr-kpi-label">Duos en lice</div>
            <div class="pr-kpi-value">{{ count($entites) }}</div>
        </div>
        <div class="pr-kpi">
            <div class="pr-kpi-label">Seuil 1er tour</div>
            <div class="pr-kpi-value">{{ number_format($seuilPremierTour, 2, ',', '') }}%</div>
        </div>
    </div>

    <div class="pr-card overflow-hidden">
        <div class="p-6 border-b border-slate-200 bg-gradient-to-r from-[#fff5f5] to-[#fff7eb]">
            <h3 class="pr-section-title flex items-center gap-2">
                <i class="fas fa-gavel text-[#8a1420]"></i>
                {{ $compilation['decision_label'] ?? 'Décision de compilation' }}
            </h3>
            <p class="pr-section-subtitle">
                Seuil premier tour: {{ number_format($seuilPremierTour, 2, ',', '') }}% •
                Duos admis au second tour: {{ $nbSecondTour }}
            </p>
        </div>
        <div class="p-6">
            @if(!empty($compilation['elu_premier_tour']) && !empty($compilation['premier']))
                <div class="pr-mini-card border-green-200 bg-green-50">
                    <div class="text-sm font-semibold text-green-900">Duo élu au premier tour</div>
                    <div class="text-2xl font-extrabold text-green-900 mt-1">{{ $compilation['premier']['ticket'] }}</div>
                    <div class="text-sm text-green-800 mt-1">
                        {{ number_format($compilation['premier']['voix']) }} voix ({{ number_format($compilation['premier']['pourcentage'], 2) }}%)
                    </div>
                </div>
            @elseif(!empty($compilation['second_tour_requis']))
                <div class="pr-mini-card border-amber-200 bg-amber-50">
                    <div class="text-sm font-semibold text-amber-900">Second tour requis</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                        @foreach($compilation['qualifies_second_tour'] ?? [] as $duo)
                            <div class="pr-mini-card border-amber-200">
                                <div class="text-xs text-slate-500">Rang national {{ $duo['rang'] }}</div>
                                <div class="font-bold text-slate-900">{{ $duo['ticket'] }}</div>
                                <div class="text-sm text-slate-700 mt-1">
                                    {{ number_format($duo['voix']) }} voix ({{ number_format($duo['pourcentage'], 2) }}%)
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="pr-card overflow-hidden">
        <div class="p-6 border-b border-slate-200">
            <h3 class="pr-section-title">Classement des duos dans le scope</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="pr-table">
                <thead>
                    <tr>
                        <th class="text-left">Rang</th>
                        <th class="text-left">Duo</th>
                        <th class="text-right">Voix scope</th>
                        <th class="text-right">% scope</th>
                        <th class="text-right">Communes gagnées</th>
                        <th class="text-center">Rang national</th>
                        <th class="text-center">Statut national</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tableScope as $row)
                        @php
                            $statut = $row['statut_national'] ?? 'non_qualifie';
                            $badgeClass = 'pr-badge pr-badge-gray';
                            $badgeLabel = 'Non qualifié';
                            if ($statut === 'elu_premier_tour') {
                                $badgeClass = 'pr-badge pr-badge-green';
                                $badgeLabel = 'Élu';
                            } elseif ($statut === 'qualifie_second_tour') {
                                $badgeClass = 'pr-badge pr-badge-amber';
                                $badgeLabel = 'Qualifié';
                            }
                        @endphp
                        <tr>
                            <td class="font-extrabold">{{ $row['rang_scope'] }}</td>
                            <td>
                                <div class="font-bold text-slate-900">{{ $row['entite']->ticket ?? ($row['entite']->sigle ?: $row['entite']->nom) }}</div>
                                <div class="text-xs text-slate-500">{{ $row['entite']->sigle ?: $row['entite']->nom }}</div>
                            </td>
                            <td class="text-right font-semibold">{{ number_format($row['voix_scope']) }}</td>
                            <td class="text-right font-semibold text-[#8a1420]">{{ number_format($row['pct_scope'], 2) }}%</td>
                            <td class="text-right font-semibold">{{ number_format($row['communes_gagnees']) }}</td>
                            <td class="text-center">{{ $row['rang_national'] ?: '—' }}</td>
                            <td class="text-center"><span class="{{ $badgeClass }}">{{ $badgeLabel }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">TOTAL</td>
                        <td class="text-right">{{ number_format($totalVoixScope) }}</td>
                        <td class="text-right">100,00%</td>
                        <td class="text-right">{{ number_format(array_sum(array_column($tableScope, 'communes_gagnees'))) }}</td>
                        <td></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="pr-card overflow-hidden">
            <div class="p-5 border-b border-slate-200">
                <h3 class="pr-section-title text-lg">Matrice par département</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="pr-table">
                    <thead>
                        <tr>
                            <th class="text-left">Département</th>
                            @foreach($entites as $entite)
                                <th class="text-right">{{ $entite->sigle ?: $entite->nom }}</th>
                            @endforeach
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($matriceDepartementsScope as $dep)
                            <tr>
                                <td class="font-semibold">{{ $dep['info']->nom }}</td>
                                @foreach($entites as $entite)
                                    @php $r = $dep['resultats'][$entite->id] ?? ['voix' => 0, 'pourcentage' => 0]; @endphp
                                    <td class="text-right">{{ number_format($r['voix']) }}</td>
                                @endforeach
                                <td class="text-right font-bold">{{ number_format($dep['total_voix']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="pr-card overflow-hidden">
            <div class="p-5 border-b border-slate-200">
                <h3 class="pr-section-title text-lg">Top 10 communes (voix exprimées)</h3>
            </div>
            <div class="p-4 space-y-3">
                @forelse($topCommunes as $index => $commune)
                    @php
                        $pctScopeCommune = $totalVoixScope > 0 ? ($commune['total_voix'] / $totalVoixScope) * 100 : 0;
                    @endphp
                    <div class="pr-mini-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-xs text-slate-500">#{{ $index + 1 }}</div>
                                <div class="font-semibold text-slate-900">{{ $commune['info']->nom }}</div>
                                <div class="text-xs text-slate-500">{{ $commune['info']->departement_nom ?? '' }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-lg font-extrabold text-[#8a1420]">{{ number_format($commune['total_voix']) }}</div>
                                <div class="text-xs text-slate-500">{{ number_format($pctScopeCommune, 2) }}% du scope</div>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Aucune donnée disponible pour le scope sélectionné.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="pr-card overflow-hidden">
        <div class="p-5 border-b border-slate-200">
            <h3 class="pr-section-title text-lg">Matrice détaillée par commune</h3>
        </div>
        <div class="overflow-x-auto" style="max-height: 640px;">
            <table class="pr-table">
                <thead class="sticky top-0 z-10">
                    <tr>
                        <th class="text-left sticky left-0 z-20">Commune</th>
                        @foreach($entites as $entite)
                            <th class="text-center">{{ $entite->sigle ?: $entite->nom }}</th>
                        @endforeach
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($matriceCommunesScope as $commune)
                        @php
                            $maxVoix = 0;
                            foreach (($commune['resultats'] ?? []) as $resultat) {
                                $maxVoix = max($maxVoix, (int) ($resultat['voix'] ?? 0));
                            }
                        @endphp
                        <tr>
                            <td class="sticky left-0 bg-white">
                                <div class="font-semibold text-slate-900">{{ $commune['info']->nom }}</div>
                                <div class="text-xs text-slate-500">{{ $commune['info']->departement_nom ?? '' }}</div>
                            </td>
                            @foreach($entites as $entite)
                                @php
                                    $r = $commune['resultats'][$entite->id] ?? ['voix' => 0, 'pourcentage' => 0];
                                    $isLeader = (int)$r['voix'] > 0 && (int)$r['voix'] === $maxVoix;
                                @endphp
                                <td class="text-center">
                                    <div style="display:inline-block;padding:6px 10px;border-radius:9px;border:1px solid {{ $isLeader ? '#f5a5ad' : '#d8e0ea' }};background: {{ $isLeader ? '#fff1f2' : '#f8fafc' }};">
                                        <div style="font-weight:800;color:#0f172a;">{{ number_format($r['voix']) }}</div>
                                        <div style="font-size:11px;color:#5f6b7a;">({{ number_format($r['pourcentage'], 2) }}%)</div>
                                    </div>
                                </td>
                            @endforeach
                            <td class="text-right font-bold">{{ number_format($commune['total_voix']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="pr-note">
        <h3 class="font-bold text-[#8a1420]">Base légale de référence</h3>
        <p class="text-sm text-slate-700 mt-1">
            Loi n°2019-43 (article 130): élection du duo au scrutin majoritaire à deux tours.
            La majorité absolue des suffrages exprimés est requise au premier tour; à défaut, second tour entre les deux premiers.
        </p>
        <p class="text-xs text-slate-500 mt-2">{{ $mention_provisoire }}</p>
    </div>

    <div class="flex justify-center">
        <a href="{{ route('rapports.presidentielle.pdf', array_merge(request()->all())) }}"
           target="_blank"
           class="pr-btn pr-btn-main">
            <i class="fas fa-file-pdf text-xl"></i>
            Télécharger le rapport PDF
        </a>
    </div>
</div>

<script>
function rapportPresidentielleApp() {
    return {
        niveau: '{{ $niveau }}',
        showDepartement: false,
        showCommune: false,

        init() {
            this.updateVisibility();
        },

        onNiveauChange(niveau) {
            this.niveau = niveau;
            this.updateVisibility();
        },

        updateVisibility() {
            this.showDepartement = ['departement', 'commune'].includes(this.niveau);
            this.showCommune = this.niveau === 'commune';
        }
    }
}
</script>
@endsection

