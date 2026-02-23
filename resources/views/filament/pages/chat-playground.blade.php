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

            scrollToBottom() {
                this.$nextTick(() => {
                    const el = this.$refs.messagesContainer;
                    if (el) el.scrollTop = el.scrollHeight;
                });
            },

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
                    this.scrollToBottom();
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
                    this.scrollToBottom();
                } catch (e) {
                    this.error = e.message || 'Failed to send message';
                } finally {
                    this.loading = false;
                }
            },

            async clickOption(opt) {
                if (! this.conversationId) return;
                this.messages.push({ role: 'user', content: opt.label || 'Option ' + opt.id });
                this.loading = true;
                this.error = null;
                const body = opt.option_source === 'step'
                    ? { step_option_id: opt.id }
                    : { option_id: opt.id };
                try {
                    const res = await fetch(`/api/chat/${this.conversationId}/option`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify(body)
                    });
                    const data = await res.json();
                    if (!res.ok) throw new Error(data.error || res.statusText);
                    (data.messages || []).forEach(m => this.messages.push({ role: m.role || 'assistant', content: m.content || '' }));
                    this.currentBlock = data.block || this.currentBlock;
                    this.scrollToBottom();
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
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
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
                    <h3 class="font-semibold text-lg text-gray-900 dark:text-gray-100">Chat</h3>
                    <a
                        :href="conversationViewerUrlBase + '/' + conversationId"
                        target="_blank"
                        class="text-sm text-primary-600 dark:text-primary-400 hover:underline"
                    >
                        View full conversation
                    </a>
                </div>

                <div class="rounded-xl bg-white dark:bg-gray-800 shadow border border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col min-h-[320px] max-h-[520px]">
                    <div
                        x-ref="messagesContainer"
                        class="flex flex-col p-4 space-y-3 min-h-[240px] max-h-[400px] overflow-y-auto"
                    >
                        <template x-for="(msg, i) in messages" :key="i">
                            <div
                                :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start'"
                            >
                                <div
                                    :class="msg.role === 'user'
                                        ? 'max-w-[85%] sm:max-w-[75%] rounded-2xl rounded-br-md px-4 py-2.5 bg-primary-600 text-white dark:bg-primary-500 shadow-sm'
                                        : 'max-w-[85%] sm:max-w-[75%] rounded-2xl rounded-bl-md px-4 py-2.5 bg-gray-200 text-gray-900 dark:bg-gray-600 dark:text-gray-100 shadow-sm'"
                                >
                                    <p class="text-sm break-words whitespace-pre-wrap m-0" x-text="msg.content"></p>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 space-y-3 flex-shrink-0">
                        <template x-if="currentBlock && currentBlock.options && currentBlock.options.length">
                            <div class="space-y-1.5">
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Options</span>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="opt in currentBlock.options" :key="opt.id">
                                        <button
                                            type="button"
                                            @click="clickOption(opt)"
                                            :disabled="loading"
                                            class="inline-flex items-center justify-center rounded-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 disabled:opacity-50 transition-colors shadow-sm"
                                            x-text="opt.label"
                                        ></button>
                                    </template>
                                </div>
                            </div>
                        </template>
                        <form @submit.prevent="sendMessage($refs.input.value); $refs.input.value = ''" class="flex gap-0 rounded-lg overflow-hidden border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 shadow-sm focus-within:ring-2 focus-within:ring-primary-500 focus-within:border-primary-500">
                            <input
                                x-ref="input"
                                type="text"
                                placeholder="Type a message…"
                                :disabled="loading"
                                class="flex-1 min-w-0 px-4 py-2.5 bg-transparent border-0 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 text-sm focus:ring-0 focus:outline-none"
                            />
                            <button
                                type="submit"
                                :disabled="loading"
                                class="inline-flex items-center justify-center rounded-r-lg bg-primary-600 hover:bg-primary-500 text-white px-4 py-2.5 text-sm font-medium disabled:opacity-70 transition-colors"
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
