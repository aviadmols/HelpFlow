<x-filament-panels::page>
    @if($conversation)
        <div class="space-y-6">
            <div class="rounded-lg bg-white dark:bg-gray-800 p-4 shadow">
                <h3 class="font-semibold text-lg mb-2">Conversation</h3>
                <dl class="grid grid-cols-2 gap-2 text-sm">
                    <dt>ID</dt><dd>{{ $conversation->id }}</dd>
                    <dt>Customer</dt><dd>{{ $conversation->customer->email ?? $conversation->customer->name ?? $conversation->customer_id }}</dd>
                    <dt>Flow</dt><dd>{{ $conversation->flow->name ?? $conversation->flow_id }}</dd>
                    <dt>Status</dt><dd>{{ $conversation->status }}</dd>
                </dl>
            </div>

            <div class="rounded-lg bg-white dark:bg-gray-800 p-4 shadow">
                <h3 class="font-semibold text-lg mb-2">Messages (timeline)</h3>
                <ul class="space-y-2 divide-y dark:divide-gray-700">
                    @foreach($conversation->messages as $msg)
                        <li class="pt-2">
                            <span class="font-medium text-xs text-gray-500">{{ $msg->role }} · {{ $msg->message_type }}</span>
                            <p class="text-sm">{{ $msg->content }}</p>
                            @if($msg->meta)
                                <p class="text-xs text-gray-400">{{ json_encode($msg->meta) }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="rounded-lg bg-white dark:bg-gray-800 p-4 shadow">
                <h3 class="font-semibold text-lg mb-2">Action runs</h3>
                <table class="min-w-full text-sm">
                    <thead><tr class="border-b"><th class="text-left py-1">ID</th><th class="text-left">Status</th><th class="text-left">Endpoint</th><th class="text-left">HTTP</th><th class="text-left">Duration</th></tr></thead>
                    <tbody>
                        @foreach($conversation->actionRuns as $run)
                            <tr class="border-b">
                                <td class="py-1">{{ $run->id }}</td>
                                <td>{{ $run->status }}</td>
                                <td>{{ $run->endpoint?->name ?? '-' }}</td>
                                <td>{{ $run->http_code ?? '-' }}</td>
                                <td>{{ $run->duration_ms ? $run->duration_ms . ' ms' : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="rounded-lg bg-white dark:bg-gray-800 p-4 shadow">
                <h3 class="font-semibold text-lg mb-2">AI telemetry</h3>
                <table class="min-w-full text-sm">
                    <thead><tr class="border-b"><th class="text-left py-1">ID</th><th class="text-left">Intent</th><th class="text-left">Target block</th><th class="text-left">Confidence</th><th class="text-left">Tokens</th></tr></thead>
                    <tbody>
                        @foreach($conversation->aiTelemetry as $t)
                            <tr class="border-b">
                                <td class="py-1">{{ $t->id }}</td>
                                <td>{{ $t->intent }}</td>
                                <td>{{ $t->target_block_key }}</td>
                                <td>{{ $t->confidence }}</td>
                                <td>{{ ($t->input_tokens ?? 0) + ($t->output_tokens ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-filament-panels::page>
