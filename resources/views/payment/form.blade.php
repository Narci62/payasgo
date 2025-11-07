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
            <h1 class="text-2xl font-bold text-gray-800">💳 Paiement sécurisé</h1>
            <p class="text-gray-500 text-sm">Entrez votre identifiant et le montant à régler</p>
        </div>

        @if(session('success'))
            <div class="bg-green-50 text-green-800 p-3 rounded-md mb-4 text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="bg-red-50 text-red-800 p-3 rounded-md mb-4 text-sm">
                <ul class="list-disc pl-4">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('payment.process') }}" method="POST" class="space-y-4">
            @csrf

            <div>
                <label for="reference" class="block text-sm font-medium text-gray-700">Identifiant du client / paiement</label>
                <input type="text" id="reference" name="reference"
                       value="{{ old('reference') }}"
                       class="mt-1 w-full border-gray-300 rounded-xl focus:ring-indigo-500 focus:border-indigo-500 text-gray-800 p-2.5" style="border:1px solid black" required>
            </div>

            <div>
                <label for="amount" class="block text-sm font-medium text-gray-700">Montant à payer (CFA)</label>
                <input type="number" id="amount" name="amount"
                       value="{{ old('amount') }}"
                       class="mt-1 w-full border-gray-300  rounded-xl focus:ring-indigo-500 focus:border-indigo-500 text-gray-800 p-2.5" style="border:1px solid black" required>
            </div>

            <button type="submit"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-xl font-medium shadow-sm transition duration-200">
                Valider le paiement
            </button>
        </form>

        <p class="text-xs text-gray-400 text-center mt-6">
            Propulsé par <strong>P-Guard</strong> — Sécurité et fiabilité
        </p>
    </div>
</body>
</html>
