<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport Présidentielle - {{ $titre }}</title>
    <style>
        @page { margin: 14mm 12mm 16mm 12mm; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 9pt;
            color: #1f2937;
            line-height: 1.3;
        }
        .watermark {
            position: fixed;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 34pt;
            color: rgba(0, 0, 0, 0.04);
            font-weight: 900;
            z-index: -1;
            width: 170mm;
            text-align: center;
        }
        .header {
            border-bottom: 3px solid #991b1b;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .title {
            font-size: 18pt;
            font-weight: 900;
            color: #991b1b;
            text-transform: uppercase;
            margin: 0;
        }
        .subtitle {
            font-size: 11pt;
            margin-top: 3px;
            color: #374151;
        }
        .meta {
            font-size: 8pt;
            color: #6b7280;
            margin-top: 4px;
        }
        .section {
            margin-top: 10px;
        }
        .section-title {
            margin: 0 0 6px 0;
            padding: 6px 8px;
            background: #fee2e2;
            border-left: 4px solid #991b1b;
            font-size: 11pt;
            font-weight: 900;
            color: #991b1b;
        }
        .stats {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .stats td {
            border: 1px solid #e5e7eb;
            padding: 6px 8px;
            font-size: 8.5pt;
        }
        .stats td.label {
            width: 55%;
            background: #f9fafb;
            font-weight: 700;
            color: #4b5563;
        }
        .stats td.value {
            text-align: right;
            font-weight: 900;
            color: #111827;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            table-layout: fixed;
            page-break-inside: auto;
        }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th {
            background: #991b1b;
            color: #fff;
            font-size: 7.7pt;
            font-weight: 900;
            border: 1px solid #7f1d1d;
            padding: 4px 5px;
            text-align: left;
        }
        td {
            border: 1px solid #e5e7eb;
            font-size: 7.8pt;
            padding: 4px 5px;
            vertical-align: middle;
            word-break: break-word;
        }
        tbody tr:nth-child(even) { background: #f9fafb; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 7pt;
            font-weight: 900;
        }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-amber { background: #fef3c7; color: #92400e; }
        .badge-gray { background: #f3f4f6; color: #374151; }
        .small-note {
            font-size: 7.5pt;
            color: #6b7280;
            margin-top: 6px;
        }
        .page-break-before { page-break-before: always; }
    </style>
</head>
<body>
    <div class="watermark">VERSION PROVISOIRE - GRAFNET</div>

    <div class="header">
        <h1 class="title">Rapport des Résultats Présidentiels</h1>
        <div class="subtitle">{{ $election->nom ?? 'Élection présidentielle' }} - {{ $titre }}</div>
        <div class="meta">
            Généré le {{ now()->format('d/m/Y à H:i') }} • {{ $mention_provisoire }}
        </div>
    </div>

    <div class="section">
        <h2 class="section-title">Décision Nationale et Paramètres Juridiques</h2>
        <table class="stats">
            <tr>
                <td class="label">Décision de compilation</td>
                <td class="value">{{ $compilation['decision_label'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Total voix du scope</td>
                <td class="value">{{ number_format($totalVoixScope, 0, ',', ' ') }}</td>
            </tr>
            <tr>
                <td class="label">Majorité absolue du scope (voix)</td>
                <td class="value">{{ number_format($majoriteAbsolueScopeVoix, 0, ',', ' ') }}</td>
            </tr>
            <tr>
                <td class="label">Seuil premier tour</td>
                <td class="value">{{ number_format((float)($regles['seuil_premier_tour_pourcent'] ?? 50), 2, ',', '') }}%</td>
            </tr>
            <tr>
                <td class="label">Duos admis au second tour</td>
                <td class="value">{{ (int)($regles['nombre_candidats_second_tour'] ?? 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Classement des Duos (Scope)</h2>
        <table>
            <thead>
                <tr>
                    <th style="width:6%;">Rang</th>
                    <th style="width:30%;">Duo</th>
                    <th class="text-right" style="width:12%;">Voix scope</th>
                    <th class="text-right" style="width:10%;">% scope</th>
                    <th class="text-right" style="width:12%;">Communes gagnées</th>
                    <th class="text-center" style="width:10%;">Rang nat.</th>
                    <th class="text-center" style="width:20%;">Statut nat.</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tableScope as $row)
                    @php
                        $statut = $row['statut_national'] ?? 'non_qualifie';
                        $label = 'Non qualifié';
                        $badge = 'badge-gray';
                        if ($statut === 'elu_premier_tour') {
                            $label = 'Élu';
                            $badge = 'badge-green';
                        } elseif ($statut === 'qualifie_second_tour') {
                            $label = 'Qualifié';
                            $badge = 'badge-amber';
                        }
                    @endphp
                    <tr>
                        <td>{{ $row['rang_scope'] }}</td>
                        <td>
                            <strong>{{ $row['entite']->ticket ?? ($row['entite']->sigle ?: $row['entite']->nom) }}</strong><br>
                            <span style="font-size:7pt;color:#6b7280;">{{ $row['entite']->sigle ?: $row['entite']->nom }}</span>
                        </td>
                        <td class="text-right">{{ number_format($row['voix_scope'], 0, ',', ' ') }}</td>
                        <td class="text-right">{{ number_format($row['pct_scope'], 2, ',', '') }}%</td>
                        <td class="text-right">{{ number_format($row['communes_gagnees'], 0, ',', ' ') }}</td>
                        <td class="text-center">{{ $row['rang_national'] ?: '—' }}</td>
                        <td class="text-center"><span class="badge {{ $badge }}">{{ $label }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="small-note">Le statut national est déterminé sur l'ensemble du territoire national conformément à l'article 130.</p>
    </div>

    <div class="section page-break-before">
        <h2 class="section-title">Matrice Synthèse par Département</h2>
        <table>
            <thead>
                <tr>
                    <th style="width:22%;">Département</th>
                    @foreach($entites as $entite)
                        <th class="text-right">{{ $entite->sigle ?: $entite->nom }}</th>
                    @endforeach
                    <th class="text-right" style="width:11%;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($matriceDepartementsScope as $dep)
                    <tr>
                        <td><strong>{{ $dep['info']->nom }}</strong></td>
                        @foreach($entites as $entite)
                            @php $r = $dep['resultats'][$entite->id] ?? ['voix' => 0]; @endphp
                            <td class="text-right">{{ number_format((int)$r['voix'], 0, ',', ' ') }}</td>
                        @endforeach
                        <td class="text-right"><strong>{{ number_format($dep['total_voix'], 0, ',', ' ') }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Matrice Détaillée par Commune</h2>
        <table>
            <thead>
                <tr>
                    <th style="width:20%;">Commune</th>
                    @foreach($entites as $entite)
                        <th class="text-right">{{ $entite->sigle ?: $entite->nom }}</th>
                    @endforeach
                    <th class="text-right" style="width:10%;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($matriceCommunesScope as $commune)
                    <tr>
                        <td>
                            <strong>{{ $commune['info']->nom }}</strong><br>
                            <span style="font-size:7pt;color:#6b7280;">{{ $commune['info']->departement_nom ?? '' }}</span>
                        </td>
                        @foreach($entites as $entite)
                            @php $r = $commune['resultats'][$entite->id] ?? ['voix' => 0]; @endphp
                            <td class="text-right">{{ number_format((int)$r['voix'], 0, ',', ' ') }}</td>
                        @endforeach
                        <td class="text-right"><strong>{{ number_format($commune['total_voix'], 0, ',', ' ') }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Rappel Légal</h2>
        <p style="font-size:8.5pt; margin: 0;">
            Article 130 de la loi n°2019-43: le duo président/vice-président est élu à la majorité absolue
            des suffrages exprimés au premier tour. À défaut, un second tour est organisé avec les deux duos
            ayant recueilli le plus grand nombre de suffrages.
        </p>
    </div>
</body>
</html>

