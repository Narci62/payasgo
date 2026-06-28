<!-- lock-history -->

<div class="flex flex-col items-center justify-center space-y-4 p-4">

    <table class="w-full border-collapse border border-gray-300">
        <thead>
            <tr class="bg-gray-200">
                <th class="border border-gray-300 px-4 py-2">Date</th>
                <th class="border border-gray-300 px-4 py-2">Action</th>
                <th class="border border-gray-300 px-4 py-2">Raison</th>
                <th class="border border-gray-300 px-4 py-2">Remaining balance</th>
                <th class="border border-gray-300 px-4 py-2">User</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lockHistory as $history)
                <tr>
                    <td class="border border-gray-300 px-4 py-2">{{ App\Helpers\Helper::formatDate($history->created_at) ?? '' }}</td>
                    <td class="border border-gray-300 px-4 py-2">{{ $history->action  ?? ''}}</td>
                    <td class="border border-gray-300 px-4 py-2">{{ $history->reason ?? '' }}</td>
                    <td class="border border-gray-300 px-4 py-2">{{ $history->remaining_balance ?? '' }}</td>
                    <td class="border border-gray-300 px-4 py-2">{{ $history->fiancingPlan->client->name ?? 'N/A' }}</td>
                </tr>
            @endforeach
        </tbody>

    </table>


</div>
