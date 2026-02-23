<x-filament-panels::page>
    @php
        $urls = $this->getUrls();
    @endphp
    <div class="space-y-6">
        <p class="text-gray-600 dark:text-gray-400 text-sm">
            Manage your chat building blocks here. Use the cards below to add or edit Blocks, Flows, Endpoints, and view Tickets.
        </p>

        <div class="grid gap-4 md:grid-cols-2">
            {{-- Blocks --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
                <h3 class="font-semibold text-lg text-gray-900 dark:text-white mb-2">Blocks</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Each block shows a message and buttons to the customer. Edit a block to add <strong>Options</strong> (actions: API call, next step, confirm, handoff, or AI prompt).
                </p>
                <div class="flex flex-wrap gap-2">
                    <a
                        href="{{ $urls['blocks_index'] }}"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600"
                    >
                        View blocks
                    </a>
                    <a
                        href="{{ $urls['blocks_create'] }}"
                        class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500"
                    >
                        Add block
                    </a>
                </div>
            </div>

            {{-- Flows --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
                <h3 class="font-semibold text-lg text-gray-900 dark:text-white mb-2">Flows</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    A flow defines the conversation steps. Edit a flow to add <strong>Steps</strong>, set allowed next steps, expected answers (transition rules), and AI prompts per step.
                </p>
                <div class="flex flex-wrap gap-2">
                    <a
                        href="{{ $urls['flows_index'] }}"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600"
                    >
                        View flows
                    </a>
                    <a
                        href="{{ $urls['flows_create'] }}"
                        class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500"
                    >
                        Add flow
                    </a>
                </div>
            </div>

            {{-- Endpoints --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
                <h3 class="font-semibold text-lg text-gray-900 dark:text-white mb-2">Endpoints</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    External APIs used by block options (action type <strong>API_CALL</strong>). Configure URL, method, and request/response mappers.
                </p>
                <div class="flex flex-wrap gap-2">
                    <a
                        href="{{ $urls['endpoints_index'] }}"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600"
                    >
                        View endpoints
                    </a>
                    <a
                        href="{{ $urls['endpoints_create'] }}"
                        class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500"
                    >
                        Add endpoint
                    </a>
                </div>
            </div>

            {{-- Tickets --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
                <h3 class="font-semibold text-lg text-gray-900 dark:text-white mb-2">Tickets</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Created when a customer chooses human handoff. Assign agents, set status, and add internal notes.
                </p>
                <div class="flex flex-wrap gap-2">
                    <a
                        href="{{ $urls['tickets_index'] }}"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600"
                    >
                        View tickets
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
