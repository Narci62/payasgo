<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement - P-Guard</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md bg-white shadow-xl rounded-2xl p-8">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">💳 Statut de votre Paiement</h1>
        </div>

        @if( $status === "success" )
            <div class="bg-green-50 text-green-800 p-3 rounded-md mb-4 text-sm">
                {{  "Paiement effectué avec succès. Merci pour votre confiance !"  }}
            </div>
            @else
            <div class="bg-red-50 text-red-800 p-3 rounded-md mb-4 text-sm">
                {{  "Le paiement a échoué ou a été annulé. Veuillez réessayer."  }}
            </div>
        @endif
        <div class="text-center mt-6">
            <a href="{{ route('payment.form') }}"
               class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 px-6 rounded-xl font-medium shadow-sm transition duration-200">
                Retour au formulaire de paiement
            </a>
        </div>
    </div>
</body>
</html>
