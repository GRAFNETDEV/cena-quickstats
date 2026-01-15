<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Statistiques CENA</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { color: #1E40AF; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #1E40AF; color: white; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>CENA - Commission Électorale Nationale Autonome</h1>
        <h2>{{ $election->nom }}</h2>
        <p>Généré le {{ $date }}</p>
    </div>

    @if($type == 'national' && isset($stats['totaux']))
    <h3>Statistiques Nationales</h3>
    <table>
        <tr>
            <th>Indicateur</th>
            <th>Valeur</th>
        </tr>
        <tr>
            <td>PV Validés</td>
            <td>{{ number_format($stats['totaux']['nombre_pv_valides']) }}</td>
        </tr>
        <tr>
            <td>Inscrits CENA</td>
            <td>{{ number_format($stats['totaux']['inscrits_cena']) }}</td>
        </tr>
        <tr>
            <td>Inscrits Comptabilisés</td>
            <td>{{ number_format($stats['totaux']['inscrits_comptabilises']) }}</td>
        </tr>
        <tr>
            <td>Votants</td>
            <td>{{ number_format($stats['totaux']['nombre_votants']) }}</td>
        </tr>
        <tr>
            <td>Taux Participation Global</td>
            <td>{{ number_format($stats['totaux']['taux_participation_global'], 2) }}%</td>
        </tr>
    </table>
    @endif

    <div class="footer">
        <p>© {{ date('Y') }} CENA - Bénin</p>
    </div>
</body>
</html>