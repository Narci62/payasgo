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

        @if(isset($client_name))
        <div class="bg-gray-50 rounded-xl p-4 mb-6">
            <p class="text-sm text-gray-500 mb-1">Client</p>
            <p class="text-lg font-semibold text-gray-800">{{ $client_name }}</p>
        </div>
        @endif

        @if(isset($total_price) && $total_price > 0)
        <div class="bg-indigo-50 rounded-xl p-4 mb-6">
            <h3 class="text-sm font-medium text-indigo-700 mb-3">État du Contract</h3>
            <div class="grid grid-cols-3 gap-3 text-center">
                <div>
                    <p class="text-xs text-gray-500">Total</p>
                    <p class="text-sm font-bold text-gray-800">{{ number_format($total_price, 0, ',', ' ') }} F</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Payé</p>
                    <p class="text-sm font-bold text-green-600">{{ number_format($paid_amount, 0, ',', ' ') }} F</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Reste à payer</p>
                    <p class="text-sm font-bold text-red-600">{{ number_format($remaining_amount, 0, ',', ' ') }} F</p>
                </div>
            </div>
            <div class="mt-3">
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-indigo-600 h-2 rounded-full" style="width: {{ $total_price > 0 ? round(($paid_amount / $total_price) * 100) : 0 }}%"></div>
                </div>
                <p class="text-xs text-gray-500 text-center mt-1">{{ $total_price > 0 ? round(($paid_amount / $total_price) * 100) : 0 }}% payé</p>
            </div>
        </div>
        @endif

        @if($is_late)
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
            <div class="flex items-center mb-2">
                <span class="text-red-500 text-lg mr-2">⚠️</span>
                <h3 class="text-sm font-bold text-red-700">Retard de paiement</h3>
            </div>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-red-600">Jours de retard :</span>
                    <span class="font-bold text-red-700">{{ $days_late }} jour(s)</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-red-600">Échéance :</span>
                    <span class="font-bold text-red-700">{{ $due_date }}</span>
                </div>
                <hr class="border-red-200">
                <div class="flex justify-between">
                    <span class="text-red-600">Montant échéance :</span>
                    <span class="font-bold text-red-700">{{ number_format($total_due - $penalty_amount, 0, ',', ' ') }} F</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-red-600">Pénalité :</span>
                    <span class="font-bold text-red-700">{{ number_format($penalty_amount, 0, ',', ' ') }} F</span>
                </div>

                @if($penalty_breakdown && $penalty_breakdown['total'] > 0)
                <details class="mt-2">
                    <summary class="text-xs text-red-500 cursor-pointer hover:text-red-700 select-none">
                        Comment est calculée la pénalité ?
                    </summary>
                    <div class="mt-2 p-3 bg-red-100/50 rounded-lg text-xs text-red-700 space-y-2">
                        @if($penalty_breakdown['tier'] == 2)
                            <p>Vous êtes au <strong>palier 2</strong> (8 à 15 jours de retard).</p>
                            <p>Frais fixe : <strong>5 000 F</strong></p>
                        @elseif($penalty_breakdown['tier'] == 3)
                            <p>Vous êtes au <strong>palier 3</strong> (16 jours et plus de retard).</p>
                            @if($penalty_breakdown['fixed_amount'] > 0)
                                <p>Frais fixes : <strong>{{ $penalty_breakdown['blocs_30j'] }} bloc(s) de 30 jours</strong> × 10 000 F = <strong>{{ number_format($penalty_breakdown['fixed_amount'], 0, ',', ' ') }} F</strong></p>
                            @endif
                            @if($penalty_breakdown['variable_amount'] > 0)
                                <p>Frais variables : <strong>{{ $penalty_breakdown['cycles_14j'] }} cycle(s) de 14 jours</strong> × 5% × {{ number_format($installment_amount, 0, ',', ' ') }} F = <strong>{{ number_format($penalty_breakdown['variable_amount'], 0, ',', ' ') }} F</strong></p>
                            @endif
                            <p class="font-bold border-t border-red-200 pt-1">Total : {{ number_format($penalty_breakdown['fixed_amount'], 0, ',', ' ') }} + {{ number_format($penalty_breakdown['variable_amount'], 0, ',', ' ') }} = {{ number_format($penalty_breakdown['total'], 0, ',', ' ') }} F</p>
                        @endif
                    </div>
                </details>
                @endif

                <hr class="border-red-200">
                <div class="flex justify-between text-base">
                    <span class="text-red-700 font-bold">Total à payer :</span>
                    <span class="font-extrabold text-red-800">{{ number_format($total_due, 0, ',', ' ') }} F</span>
                </div>
            </div>
        </div>
        @endif

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
                <label for="reference" class="block text-sm font-medium text-gray-700">Identifiant du client</label>
                <input type="text" id="reference" name="reference"
                       value="{{ old('reference') ?? $imat ?? '' }}"
                       class="mt-1 w-full border-gray-300 rounded-xl focus:ring-indigo-500 focus:border-indigo-500 text-gray-800 p-2.5" style="border:1px solid black" required>
            </div>

            <div>
                <label for="amount" class="block text-sm font-medium text-gray-700">Montant à payer (CFA)</label>
                <input type="number" id="amount" name="amount"
                       value="{{ old('amount') ?? ($is_late ? intval($total_due) : intval($installment_amount)) ?? '' }}"
                       class="mt-1 w-full border-gray-300  rounded-xl focus:ring-indigo-500 focus:border-indigo-500 text-gray-800 p-2.5" style="border:1px solid black" required>
                @if($is_late)
                <p class="text-xs text-red-500 mt-1">Minimum requis : {{ number_format($total_due, 0, ',', ' ') }} F (échéance + pénalité)</p>
                @endif
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
