<x-filament-panels::page>
    <div class="rounded-lg bg-white dark:bg-gray-800 shadow overflow-hidden">
        <table class="min-w-full divide-y dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Conversation</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Intent</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Target block</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Confidence</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tokens</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                </tr>
            </thead>
            <tbody class="divide-y dark:divide-gray-700">
                @foreach($this->getTelemetry() as $t)
                    <tr>
                        <td class="px-4 py-2 text-sm">{{ $t->id }}</td>
                        <td class="px-4 py-2 text-sm">{{ $t->conversation_id }}</td>
                        <td class="px-4 py-2 text-sm">{{ $t->intent }}</td>
                        <td class="px-4 py-2 text-sm">{{ $t->target_block_key }}</td>
                        <td class="px-4 py-2 text-sm">{{ $t->confidence }}</td>
                        <td class="px-4 py-2 text-sm">{{ ($t->input_tokens ?? 0) + ($t->output_tokens ?? 0) }}</td>
                        <td class="px-4 py-2 text-sm">{{ $t->created_at->diffForHumans() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="px-4 py-2">
            {{ $this->getTelemetry()->links() }}
        </div>
    </div>
</x-filament-panels::page>
