<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>P-Guard — Rapport de ventes</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #eee; }
        h2 { text-align: center; }
    </style>
</head>
<body>

    <h2>RAPPORT DE VENTE - P-Guard</h2>

    <p><strong>Adresse :</strong> Cococodji </p>
    <p><strong>Date :</strong> {{ now()->format('d/m/Y H:i') }}</p>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Client</th>
                <th>Montant</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sales as $sale)
                <tr>
                    <td>{{ $sale->id }}</td>
                    <td>{{ $sale->financingPlan->registrationToken->client->full_name }}</td>
                    <td>{{ number_format($sale->amount, 0, ',', ' ') }} FCFA</td>
                    <td>{{ $sale->created_at->format('d/m/Y H:i') }}</td>
                </tr>
            @endforeach
            <tr>
                <td></td>
                <td></td>
                <td><strong>Total</strong></td>
                <td><strong>{{ number_format($sales->sum('amount'), 0, ',', ' ') }} FCFA</strong></td>
            </tr>
        </tbody>
    </table>

</body>
</html>
