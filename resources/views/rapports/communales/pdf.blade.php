<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport Communales - {{ $titre }}</title>

    <style>
        @page { margin: 14mm 12mm 18mm 12mm; }

        * { box-sizing: border-box; }

        body{
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 9pt;
            color:#1a1a1a;
            line-height: 1.25;
        }

        /* Watermark */
        .watermark{
            position: fixed;
            top: 50%;
            left: 50%;
            width: 170mm;
            transform: translate(-50%, -50%) rotate(-35deg);
            text-align: center;
            font-size: 42pt;
            font-weight: 900;
            color: rgba(0,0,0,0.030);
            z-index: -1;
            pointer-events: none;
            white-space: normal;
            word-break: break-word;
            letter-spacing: 1px;
        }

        /* Header */
        .header{
            text-align:center;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 3px solid #2d5016;
        }
        .header-title{ font-size: 10pt; color:#555; margin:0 0 4px 0; }
        .main-title{
            font-size: 18pt; font-weight: 900; margin: 6px 0;
            color:#2d5016; text-transform: uppercase;
        }
        .subtitle{ font-size: 12pt; font-weight: 700; margin: 4px 0; color:#444; }
        .metadata{ font-size: 8pt; color:#666; margin-top: 4px; }

        /* Sections */
        .section{ margin-top: 10px; }
        .section-title{
            font-size: 12pt;
            font-weight: 900;
            color:#2d5016;
            margin: 10px 0 6px 0;
            padding: 6px 9px;
            background: #f0f4ed;
            border-left: 4px solid #2d5016;
        }

        .page-break-after{ page-break-after: always; }
        .page-break-before{ page-break-before: always; }

        /* Info box */
        .info-box{
            background:#f8f9fa;
            border:1px solid #dee2e6;
            border-radius: 4px;
            padding: 9px 10px;
            margin: 6px 0;
        }
        .info-row{ margin: 2px 0; font-size: 9pt; }
        .info-label{ color:#666; font-weight: 700; }
        .info-value{ font-weight: 900; color:#000; }

        /* Tables */
        table{
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
            font-size: 8.1pt;
            table-layout: fixed;
            page-break-inside: auto;
        }
        thead{ display: table-header-group; }
        tfoot{ display: table-footer-group; }

        th{
            background:#2d5016;
            color:#fff;
            font-weight: 900;
            padding: 4px 5px;
            text-align:left;
            border: 1px solid #1a3810;
            font-size: 7.8pt;
            white-space: nowrap;
        }
        td{
            padding: 4px 5px;
            border:1px solid #e0e0e0;
            vertical-align: middle;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        tr{ page-break-inside: avoid; }

        tbody tr:nth-child(even){ background:#f8faf6; }

        .text-right{ text-align:right; }
        .text-center{ text-align:center; }
        .font-bold{ font-weight: 900; }

        .elig-yes{ font-weight: 900; color:#0d6832; }
        .elig-no{ font-weight: 900; color:#9b1c1c; }

        .highlight-green{ background:#e6f4ea; font-weight: 900; }

        hr.separator{
            border:none;
            border-top: 2px solid #2d5016;
            margin: 10px 0;
        }

        /* Commune block */
        .commune-block{
            border: 2px solid #ddd;
            border-radius: 6px;
            padding: 0;
            background:#fff;
            page-break-inside: auto;
            margin-bottom: 10px;
        }
        .commune-break{ page-break-before: always; }

        .commune-head{
            padding: 10px 10px 0 10px;
            page-break-after: avoid;
        }

        .arr-block{
            margin: 10px;
            padding: 8px;
            border:1px solid #e0e0e0;
            border-radius: 4px;
            background:#fafafa;
            page-break-inside: auto;
        }

        .arr-head{
            width: 100%;
            margin: 0 0 6px 0;
            page-break-after: avoid;
        }

        /* Table détails arrondissement */
        .table-arr{
            font-size: 7.4pt;
            margin-top: 6px;
            table-layout: fixed;
            page-break-inside: auto;
            width: 100%;
        }
        .table-arr th{ font-size: 7.2pt; padding: 3px 3px; }
        .table-arr td{ padding: 3px 3px; vertical-align: top; }

        /* ✅ LARGEURS OPTIMISÉES: Candidats élus TRÈS LARGE */
        .table-arr col.c1{ width: 5%; }   /* Parti - très serré */
        .table-arr col.c2{ width: 7%; }   /* Voix - serré */
        .table-arr col.c3{ width: 7%; }   /* % Arr. - serré */
        .table-arr col.c4{ width: 5%; }   /* Sièges - très serré */
        .table-arr col.c5{ width: 76%; }  /* Candidats élus - TRÈS LARGE */
       
        /* alignements */
        .table-arr td:nth-child(2),
        .table-arr td:nth-child(3),
        .table-arr td:nth-child(4),
        .table-arr th:nth-child(2),
        .table-arr th:nth-child(3),
        .table-arr th:nth-child(4){
            text-align: right;
            white-space: nowrap;
        }

        .table-arr td:nth-child(1),
        .table-arr th:nth-child(1){
            white-space: nowrap;
        }

        .candidate-list{ font-size: 7.2pt; line-height: 1.32; }
        .candidate-item{ margin: 2px 0; white-space: nowrap; }
        .candidate-item strong{ white-space: nowrap; }

        .pos{
            display:inline-block;
            width: 12px; height: 12px;
            line-height: 12px;
            text-align:center;
            background:#2d5016;
            color:#fff;
            border-radius: 50%;
            font-size: 6.8pt;
            font-weight: 900;
            margin-right: 3px;
        }

        .no-data{
            text-align:center;
            padding: 10px;
            color:#999;
            font-style: italic;
            font-size: 9pt;
        }

        /* Footer */
        .footer{
            position: fixed;
            bottom: 8mm;
            left: 12mm;
            right: 12mm;
            font-size: 7.5pt;
            color:#888;
            border-top:1px solid #ddd;
            padding-top: 4px;
        }
        .footer-left{ float:left; }
        .footer-right{ float:right; }
        .clearfix{ clear: both; }
    </style>
</head>

<body>
@php
    $entitesById = collect($entites ?? [])->keyBy('id');
@endphp

<div class="watermark">VERSION PROVISOIRE - GRAFNET</div>

{{-- ========================= PAGE DE GARDE ========================= --}}
<div class="header">
    <div class="header-title">RÉPUBLIQUE DU BÉNIN</div>
    <h1 class="main-title">RAPPORT DES RÉSULTATS</h1>
    <h2 class="subtitle">Élections Communales</h2>
    <div class="metadata">
        <strong>Scope :</strong> {{ $titre }} — {{ $mention_provisoire }}
        @if(!empty($election->date_scrutin))
            — <strong>Date scrutin :</strong> {{ \Carbon\Carbon::parse($election->date_scrutin)->format('d/m/Y') }}
        @endif
    </div>
</div>

<div class="section">
    <div class="info-box">
        <div class="info-row"><span class="info-label">Niveau de localisation :</span> <span class="info-value">{{ ucfirst($niveau) }}</span></div>
        <div class="info-row"><span class="info-label">Total des voix (scope) :</span> <span class="info-value">{{ number_format($totalVoixScope, 0, ',', ' ') }}</span></div>
        <div class="info-row"><span class="info-label">Nombre de communes :</span> <span class="info-value">{{ count($communesBlocs) }}</span></div>
        <div class="info-row"><span class="info-label">Total des sièges (scope) :</span> <span class="info-value">{{ array_sum(array_column($tableScope, 'sieges')) }}</span></div>
    </div>
</div>

<div class="page-break-after"></div>

{{-- ========================= PAGE 2 : Résultats par entité ========================= --}}
<div class="section">
    <h3 class="section-title">📊 Résultats par Entité Politique</h3>

    <table>
        <colgroup>
            <col style="width: 34%;">
            <col style="width: 14%;">
            <col style="width: 12%;">
            <col style="width: 14%;">
            <col style="width: 12%;">
            <col style="width: 14%;">
        </colgroup>
        <thead>
        <tr>
            <th>Entité</th>
            <th class="text-right">Voix</th>
            <th class="text-right">% Scope</th>
            <th class="text-center">Éligible</th>
            <th class="text-right">% Nat.</th>
            <th class="text-right">Sièges</th>
        </tr>
        </thead>
        <tbody>
        @foreach($tableScope as $row)
            <tr>
                <td class="font-bold">{{ $row['entite']->sigle ?: $row['entite']->nom }}</td>
                <td class="text-right">{{ number_format($row['voix'], 0, ',', ' ') }}</td>
                <td class="text-right font-bold">{{ number_format($row['pct'], 2, ',', '') }}%</td>
                <td class="text-center">
                    @if($row['eligible_national'])
                        <span class="elig-yes">✓ Oui</span>
                    @else
                        <span class="elig-no">✗ Non</span>
                    @endif
                </td>
                <td class="text-right">{{ number_format($row['pct_national'], 2, ',', '') }}%</td>
                <td class="text-right highlight-green">{{ (int)$row['sieges'] }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
        <tr style="background:#e6f4ea; font-weight:900;">
            <td>TOTAL</td>
            <td class="text-right">{{ number_format($totalVoixScope, 0, ',', ' ') }}</td>
            <td class="text-right">100,00%</td>
            <td></td>
            <td></td>
            <td class="text-right">{{ array_sum(array_column($tableScope, 'sieges')) }}</td>
        </tr>
        </tfoot>
    </table>
</div>

<div class="page-break-after"></div>

{{-- ========================= PAGE 3+ : Détails par commune ========================= --}}
@if(!empty($communesBlocs))
    <div class="section">
        <h3 class="section-title">🏘️ Détails par Commune ({{ count($communesBlocs) }} commune(s))</h3>

        @foreach($communesBlocs as $cb)
            <div class="commune-block {{ !$loop->first ? 'commune-break' : '' }}">

                {{-- ✅ En-tête commune (anti-orphan) --}}
                <div class="commune-head">
                    <div style="width:100%;">
                        <div style="width:50%; display:inline-block; vertical-align:top;">
                            <span style="font-weight:900; font-size:11pt; color:#1a4d2e;">{{ $cb['commune']->nom }}</span>
                            <span style="font-weight:400; font-size:9pt; color:#666; margin-left:6px;">({{ $cb['commune']->departement_nom ?? '' }})</span>
                        </div>
                        <div style="width:49%; display:inline-block; text-align:right; vertical-align:top; white-space:nowrap;">
                            <span style="font-size:9pt; color:#333; font-weight:700;">
                                Population : {{ number_format($cb['population'], 0, ',', ' ') }} &nbsp;|&nbsp;
                                Sièges : {{ (int)$cb['sieges'] }} &nbsp;|&nbsp;
                                Quotient : {{ number_format($cb['quotient_communal'], 2, ',', ' ') }}
                            </span>
                        </div>
                    </div>
                </div>

                @foreach(($cb['arrondissements'] ?? []) as $arr)
                    @php
                        $listes = $arr['listes'] ?? [];
                        $gagnants = [];
                        foreach($listes as $entiteId => $liste){
                            if ((int)($liste['sieges'] ?? 0) > 0) $gagnants[$entiteId] = $liste;
                        }
                    @endphp

                    <div class="arr-block">

                        {{-- ✅ Titre arrondissement (anti-orphan) --}}
                        <div class="arr-head">
                            <div style="width:100%;">
                                <div style="width:55%; display:inline-block; vertical-align:middle;">
                                    <span style="font-weight:900; font-size:9.5pt; color:#1a4d2e;">📍 {{ $arr['arrondissement_nom'] }}</span>
                                </div>
                                <div style="width:44%; display:inline-block; text-align:right; vertical-align:middle; white-space:nowrap;">
                                    <span style="font-size:8.5pt; color:#666; font-weight:700;">
                                        Sièges : {{ (int)$arr['sieges_arrondissement'] }} &nbsp;|&nbsp;
                                        Voix : {{ number_format($arr['total_voix'], 0, ',', ' ') }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        @if(!empty($gagnants))
                            <table class="table-arr">
                                <thead>
                                <tr>
                                    <th style="width: 9%;">Parti</th>
                                    <th style="width: 9%;" class="text-right">Voix</th>
                                    <th style="width: 9%;" class="text-right">% Arr.</th>
                                    <th style="width: 9%;" class="text-right">Sièges</th>
                                    <th style="width: 64%;">Candidats élus</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($gagnants as $entiteId => $liste)
                                    @php
                                        $ent = $entitesById->get((int)$entiteId);
                                        $label = $ent ? ($ent->sigle ?: $ent->nom) : "Entité $entiteId";
                                    @endphp
                                    <tr>
                                        <td style="width: 9%;" class="font-bold">{{ $label }}</td>
                                        <td style="width: 9%;" class="text-right">{{ number_format($liste['voix'], 0, ',', ' ') }}</td>
                                        <td style="width: 9%;" class="text-right font-bold">{{ number_format($liste['pourcentage'], 2, ',', '') }}%</td>
                                        <td style="width: 9%;" class="text-right highlight-green">{{ (int)$liste['sieges'] }}</td>
                                        <td style="width: 64%;">
                                            @if(!empty($liste['candidats']))
                                                <div class="candidate-list">
                                                    @foreach($liste['candidats'] as $c)
                                                        <div class="candidate-item">
                                                            <span class="pos">{{ $c['position'] }}</span>
                                                            <strong>{{ $c['titulaire'] }}</strong>
                                                            @if(!empty($c['suppleant']))
                                                                <span style="color:#666; font-size:7.0pt;">
                                                                    (Suppléant: {{ $c['suppleant'] }})
                                                                </span>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span style="color:#999;">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="no-data">Aucun siège attribué dans cet arrondissement</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
@else
    <div class="section"><div class="no-data">Aucune commune trouvée pour ce scope</div></div>
@endif

<hr class="separator">

{{-- Villages non saisis --}}
<div class="section page-break-before">
    <h3 class="section-title">⚠️ Villages / Quartiers Non Saisis</h3>
    <p style="font-size:8.5pt; color:#666; margin:6px 0;">
        Liste des villages/quartiers du référentiel qui n'ont pas encore été saisis.
    </p>

    @if(!empty($villagesNonSaisis))
        <table>
            <colgroup>
                <col style="width: 18%;">
                <col style="width: 22%;">
                <col style="width: 25%;">
                <col style="width: 35%;">
            </colgroup>
            <thead>
            <tr>
                <th>Département</th>
                <th>Commune</th>
                <th>Arrondissement</th>
                <th>Village/Quartier</th>
            </tr>
            </thead>
            <tbody>
            @foreach($villagesNonSaisis as $v)
                <tr>
                    <td>{{ $v->departement_nom }}</td>
                    <td>{{ $v->commune_nom }}</td>
                    <td>{{ $v->arrondissement_nom }}</td>
                    <td class="font-bold">{{ $v->village_quartier_nom }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
            <tr style="background:#fff9e6; font-weight:900;">
                <td colspan="3">TOTAL NON SAISIS</td>
                <td class="text-right">{{ count($villagesNonSaisis) }}</td>
            </tr>
            </tfoot>
        </table>
    @else
        <div class="no-data">✓ Tous les villages/quartiers du scope ont été saisis</div>
    @endif>
</div>

{{-- Notes finales --}}
<div class="section" style="margin-top:12px; padding:10px; background:#f0f4ed; border-left:4px solid #2d5016;">
    <p style="font-size:8pt; color:#555; margin:0; line-height:1.55;">
        <strong>Notes importantes :</strong><br>
        • Ce rapport est une version provisoire générée par la plateforme de compilation GRAFNET.<br>
        • Les résultats sont basés sur les PV validés/publiés avec déduplication par arrondissement et village.<br>
        • La répartition des sièges suit les Articles 183-187 de la loi électorale béninoise.<br>
        • Seuil d'éligibilité nationale : 10% des suffrages exprimés au plan national (Art.184).<br>
        • Date de génération : {{ date('d/m/Y à H:i:s') }}
    </p>
</div>

{{-- Footer texte --}}
<div class="footer">
    <div class="footer-left">
        Plateforme GRAFNET — Communales — Version Provisoire
        <strong>{{ strtoupper($niveau) }}</strong> — {{ $titre }}
    </div>
    <div class="footer-right"></div>
    <div class="clearfix"></div>
</div>

{{-- Pagination DomPDF --}}
<script type="text/php">
if (isset($pdf)) {
    $font = $fontMetrics->get_font("DejaVu Sans", "normal");
    $size = 8;
    $y = $pdf->get_height() - 24;
    $pdf->page_text(430, $y, "Page {PAGE_NUM} / {PAGE_COUNT}", $font, $size, array(0.45,0.45,0.45));
}
</script>

</body>
</html>