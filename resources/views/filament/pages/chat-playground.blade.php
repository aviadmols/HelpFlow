<x-filament-panels::page>
    <div
        x-data="{
            flows: @js($flows),
            selectedFlowKey: '',
            conversationId: null,
            messages: [],
            currentBlock: null,
            loading: false,
            error: null,
            conversationViewerUrlBase: '/admin/conversations',

            async startConversation() {
                if (! this.selectedFlowKey) return;
                this.loading = true;
                this.error = null;
                this.messages = [];
                this.currentBlock = null;
                this.conversationId = null;
                try {
                    const res = await fetch('/api/chat/start', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ flow_key: this.selectedFlowKey, name: 'Test User' })
                    });
                    const data = await res.json();
                    if (!res.ok) throw new Error(data.error || res.statusText);
                    this.conversationId = data.conversation_id;
                    if (data.block && data.block.bot_message) {
                        this.messages.push({ role: 'assistant', content: data.block.bot_message });
                    }
                    this.currentBlock = data.block || null;
                } catch (e) {
                    this.error = e.message || 'Failed to start conversation';
                } finally {
                    this.loading = false;
                }
            },

            async sendMessage(text) {
                if (! this.conversationId || ! text || ! text.trim()) return;
                this.messages.push({ role: 'user', content: text.trim() });
                this.loading = true;
                this.error = null;
                const body = text.trim();
                try {
                    const res = await fetch(`/api/chat/${this.conversationId}/message`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ message: body })
                    });
                    const data = await res.json();
                    if (!res.ok) throw new Error(data.error || res.statusText);
                    (data.messages || []).forEach(m => this.messages.push({ role: m.role || 'assistant', content: m.content || '' }));
                    if ((!data.messages || data.messages.length === 0) && data.block && data.block.bot_message) {
                        this.messages.push({ role: 'assistant', content: data.block.bot_message });
                    }
                    this.currentBlock = data.block || this.currentBlock;
                } catch (e) {
                    this.error = e.message || 'Failed to send message';
                } finally {
                    this.loading = false;
                }
            },

            async clickOption(optionId, label) {
                if (! this.conversationId) return;
                this.messages.push({ role: 'user', content: label || 'Option ' + optionId });
                this.loading = true;
                this.error = null;
                try {
                    const res = await fetch(`/api/chat/${this.conversationId}/option`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ option_id: optionId })
                    });
                    const data = await res.json();
                    if (!res.ok) throw new Error(data.error || res.statusText);
                    (data.messages || []).forEach(m => this.messages.push({ role: m.role || 'assistant', content: m.content || '' }));
                    this.currentBlock = data.block || this.currentBlock;
                } catch (e) {
                    this.error = e.message || 'Failed to send option';
                } finally {
                    this.loading = false;
                }
            }
        }"
        class="space-y-6"
    >
        <div class="rounded-lg bg-white dark:bg-gray-800 p-4 shadow">
            <h3 class="font-semibold text-lg mb-3">Test a flow</h3>
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-[200px]">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Flow</label>
                    <select
                        x-model="selectedFlowKey"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm"
                    >
                        <option value="">— Select flow —</option>
                        <template x-for="f in flows" :key="f.key">
                            <option :value="f.key" x-text="f.name + ' (' + f.key + ')'"></option>
                        </template>
                    </select>
                </div>
                <button
                    type="button"
                    @click="startConversation()"
                    :disabled="!selectedFlowKey || loading"
                    class="filament-button filament-button-size-sm inline-flex items-center justify-center rounded-lg font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:pointer-events-none filament-page-button-primary bg-primary-600 hover:bg-primary-500 focus:ring-primary-500 text-white shadow px-4 py-2 text-sm disabled:opacity-70"
                >
                    <span x-text="loading ? 'Starting…' : 'Start conversation'"></span>
                </button>
            </div>
        </div>

        <div x-show="error" x-text="error" class="rounded-lg bg-danger-50 dark:bg-danger-900/20 text-danger-700 dark:text-danger-400 p-3 text-sm"></div>

        <template x-if="conversationId">
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-lg">Chat</h3>
                    <a
                        :href="conversationViewerUrlBase + '/' + conversationId"
                        target="_blank"
                        class="text-sm text-primary-600 dark:text-primary-400 hover:underline"
                    >
                        View full conversation
                    </a>
                </div>

                <div class="rounded-lg bg-white dark:bg-gray-800 shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="p-4 space-y-3 max-h-[360px] overflow-y-auto">
                        <template x-for="(msg, i) in messages" :key="i">
                            <div
                                :class="msg.role === 'user' ? 'ml-8 text-right' : 'mr-8 text-left'"
                            >
                                <span
                                    class="text-xs font-medium text-gray-500 dark:text-gray-400"
                                    x-text="msg.role === 'user' ? 'You' : 'Bot'"
                                ></span>
                                <p class="text-sm mt-0.5 break-words" x-text="msg.content"></p>
                            </div>
                        </template>
                    </div>

                    <div class="p-4 border-t border-gray-200 dark:border-gray-700 space-y-3">
                        <template x-if="currentBlock && currentBlock.options && currentBlock.options.length">
                            <div class="flex flex-wrap gap-2">
                                <template x-for="opt in currentBlock.options" :key="opt.id">
                                    <button
                                        type="button"
                                        @click="clickOption(opt.id, opt.label)"
                                        :disabled="loading"
                                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50"
                                        x-text="opt.label"
                                    ></button>
                                </template>
                            </div>
                        </template>
                        <form @submit.prevent="sendMessage($refs.input.value); $refs.input.value = ''" class="flex gap-2">
                            <input
                                x-ref="input"
                                type="text"
                                placeholder="Type a message…"
                                :disabled="loading"
                                class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            />
                            <button
                                type="submit"
                                :disabled="loading"
                                class="filament-button filament-button-size-sm inline-flex items-center justify-center rounded-lg font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:pointer-events-none filament-page-button-primary bg-primary-600 hover:bg-primary-500 focus:ring-primary-500 text-white shadow px-4 py-2 text-sm disabled:opacity-70"
                            >
                                Send
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </template>
    </div>
</x-filament-panels::page>
