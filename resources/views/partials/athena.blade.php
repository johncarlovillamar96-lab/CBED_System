<div x-data="athenaAssistant()" x-cloak>
    <button type="button" @click="open = true; $nextTick(() => $refs.question.focus())"
            class="fixed bottom-5 right-5 z-40 inline-flex items-center gap-2 rounded-full bg-[#0b1f3a] px-5 py-3 text-sm font-bold text-white shadow-xl transition hover:bg-[#122b4d]"
            aria-label="Open Athena AI assistant">
        <i class="fa-solid fa-wand-magic-sparkles text-amber-300"></i>
        <span>Ask Athena</span>
    </button>

    <div x-show="open" x-transition.opacity @click="open = false"
         class="fixed inset-0 z-50 bg-slate-950/40" aria-hidden="true"></div>

    <aside x-show="open" x-transition:enter="transform transition ease-out duration-300"
           x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
           x-transition:leave="transform transition ease-in duration-200"
           x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
           @keydown.escape.window="open = false"
           class="fixed inset-y-0 right-0 z-[60] flex w-full max-w-md flex-col border-l border-slate-200 bg-white shadow-2xl"
           role="dialog" aria-modal="true" aria-labelledby="athena-title">
        <header class="flex items-center justify-between bg-[#0b1f3a] px-5 py-4 text-white">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/10 text-xl text-amber-300">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                </div>
                <div>
                    <h2 id="athena-title" class="font-bold">Athena</h2>
                    <p class="mt-0.5 text-xs text-blue-100">CBED AI assistant</p>
                </div>
            </div>
            <button type="button" @click="open = false" class="cbed-close-button" aria-label="Close Athena">
                <span aria-hidden="true">&times;</span>
            </button>
        </header>

        <div x-ref="conversation" class="flex-1 space-y-4 overflow-y-auto bg-slate-50 p-4">
            <div x-show="messages.length === 0" class="rounded-xl border border-blue-100 bg-white p-4 shadow-sm">
                <p class="font-semibold text-slate-800">Hello! I’m Athena.</p>
                <p class="mt-1 text-sm leading-6 text-slate-600">Ask a general question, get help with CBED operations, or request a data summary or report.</p>
                <p class="mt-3 text-xs text-slate-500">Athena sends your question and relevant query totals to the AI provider. Record lists are displayed directly from CBED.</p>
                @if (\App\Support\AthenaData::authorized(auth()->user()))
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" @click="ask('Summarize the current CBED data.')"
                            class="rounded-full border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-800 hover:bg-blue-100">Summarize CBED data</button>
                    <button type="button" @click="ask('Draft a concise CBED system report using the available aggregate data.')"
                            class="rounded-full border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-800 hover:bg-blue-100">Draft a report</button>
                </div>
                @else
                    <button type="button" @click="ask('Show the public dashboard totals.')"
                            class="mt-4 rounded-full border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-800 hover:bg-blue-100">Show dashboard totals</button>
                @endif
            </div>

            <template x-for="(message, index) in messages" :key="index">
                <div class="flex" :class="message.role === 'user' ? 'justify-end' : 'justify-start'">
                    <div class="max-w-[90%] rounded-2xl px-4 py-3 text-sm leading-6 shadow-sm"
                         :class="message.role === 'user' ? 'rounded-br-md bg-[#0b1f3a] text-white' : 'rounded-bl-md border border-slate-200 bg-white text-slate-700'">
                        <div class="whitespace-pre-wrap break-words" x-text="message.content"></div>
                        <template x-for="(result, resultIndex) in message.data || []" :key="resultIndex">
                            <details class="mt-3 rounded-lg border border-slate-200 p-3" :open="!!result.page || result.source === 'Dashboard'">
                                <summary class="cursor-pointer font-semibold" x-text="result.source"></summary>
                                <p class="text-xs text-slate-500" x-text="'As of ' + result.as_of + ' (Asia/Manila)'"></p>
                                <p x-show="Object.keys(result.filters).length" class="mt-1 text-xs" x-text="formatEntries(result.filters)"></p>
                                <p x-show="result.record_dates?.earliest" class="mt-1 text-xs"
                                   x-text="result.record_dates?.earliest + ' to ' + result.record_dates?.latest"></p>
                                <dl class="mt-2 space-y-1">
                                    <template x-for="[name, value] in Object.entries(result.totals)" :key="name">
                                        <div class="flex justify-between gap-4 text-xs">
                                            <dt x-text="label(name)"></dt><dd class="font-semibold" x-text="formatValue(value)"></dd>
                                        </div>
                                    </template>
                                </dl>
                                <template x-for="[name, totals] in Object.entries(result.breakdown || {})" :key="name">
                                    <p x-show="Object.keys(totals).length" class="mt-2 text-xs">
                                        <strong x-text="label(name)"></strong>
                                        <span x-text="formatEntries(totals)"></span>
                                    </p>
                                </template>
                                <p x-show="result.note" x-text="result.note" class="mt-2 text-xs text-slate-500"></p>
                                <a x-show="result.url" :href="result.url" class="mt-2 inline-block text-xs font-semibold text-blue-700 underline">Open module</a>
                                <template x-if="result.page">
                                    <div class="mt-3 border-t border-slate-200 pt-2">
                                        <p class="text-xs font-semibold" x-text="result.page.total + ' matching results'"></p>
                                        <ul class="divide-y divide-slate-100">
                                            <template x-for="(row, rowIndex) in result.page.rows" :key="rowIndex">
                                                <li class="py-1 text-xs" x-text="formatEntries(row)"></li>
                                            </template>
                                        </ul>
                                        <div x-show="result.page.total > result.page.size" class="mt-2 flex items-center justify-between gap-2 text-xs">
                                            <button type="button" class="text-blue-700 disabled:text-slate-400"
                                                    :disabled="result.loading || result.page.number <= 1"
                                                    @click="loadRecords(result, result.page.number - 1)">Previous</button>
                                            <span x-text="'Page ' + result.page.number"></span>
                                            <button type="button" class="text-blue-700 disabled:text-slate-400"
                                                    :disabled="result.loading || result.page.number * result.page.size >= result.page.total"
                                                    @click="loadRecords(result, result.page.number + 1)">Next</button>
                                        </div>
                                        <p x-show="result.error" x-text="result.error" role="alert" class="mt-1 text-xs text-red-700"></p>
                                    </div>
                                </template>
                            </details>
                        </template>
                    </div>
                </div>
            </template>

            <div x-show="busy" class="flex justify-start">
                <div class="rounded-2xl rounded-bl-md border border-slate-200 bg-white px-4 py-3 text-sm text-slate-500 shadow-sm">
                    <i class="fa-solid fa-circle-notch fa-spin mr-2 text-blue-600"></i>Athena is thinking…
                </div>
            </div>
            <div x-ref="end"></div>
        </div>

        <form @submit.prevent="send()" class="border-t border-slate-200 bg-white p-4">
            <p x-show="error" x-text="error" role="alert" class="mb-2 text-sm text-red-700"></p>
            <label for="athena-question" class="sr-only">Ask Athena a question</label>
            <div class="flex items-end gap-2 rounded-2xl border border-slate-300 bg-white p-2 focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-100">
                <textarea id="athena-question" x-ref="question" x-model="question" rows="2" maxlength="2000"
                          @keydown.enter="if (!$event.shiftKey && !$event.isComposing) { $event.preventDefault(); send(); }"
                          placeholder="Ask a question or request a report…"
                          class="max-h-32 min-h-12 flex-1 resize-y border-0 px-2 py-2 text-sm text-slate-800 outline-none focus:ring-0"></textarea>
                <button type="submit" :disabled="busy || !question.trim()"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#0b1f3a] text-white hover:bg-[#122b4d] disabled:cursor-not-allowed disabled:opacity-40"
                        aria-label="Send message">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </div>
            <p class="mt-2 text-center text-[11px] text-slate-400">AI responses can be inaccurate. Verify important reports.</p>
        </form>
    </aside>
</div>

<script>
    function athenaAssistant() {
        return {
            open: false,
            busy: false,
            question: '',
            error: '',
            messages: [],
            ask(prompt) {
                if (this.busy) return;
                this.question = prompt;
                this.send();
            },
            async send() {
                const content = this.question.trim();
                if (!content || this.busy) return;

                this.messages.push({ role: 'user', content });
                this.question = '';
                this.error = '';
                this.busy = true;
                this.scrollToEnd();

                try {
                    const response = await fetch(@json(route('athena.chat', [], false)), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        },
                        body: JSON.stringify({
                            messages: this.messages.slice(-11).map(({ role, content }) => ({
                                role,
                                content: Array.from(content).slice(0, 2000).join('').trimEnd()
                            }))
                        })
                    });
                    const result = await response.json().catch(() => null);
                    if ([401, 419].includes(response.status)) {
                        throw new Error('Your session expired. Refresh the page and sign in again.');
                    }
                    if (!response.ok || !result?.message) {
                        throw new Error(result?.message || 'Athena could not answer right now.');
                    }
                    this.messages.push({ role: 'assistant', content: result.message, data: result.data });
                } catch (error) {
                    this.messages.pop();
                    if (!this.question) this.question = content;
                    this.error = error.message || 'Athena could not answer right now.';
                } finally {
                    this.busy = false;
                    this.scrollToEnd();
                }
            },
            label(key) {
                const label = key.replaceAll('_', ' ');
                return label.charAt(0).toUpperCase() + label.slice(1);
            },
            formatValue(value) {
                return typeof value === 'number' ? value.toLocaleString() : (value ?? 'Unavailable');
            },
            formatEntries(entries) {
                return Object.entries(entries).map(([key, value]) => this.label(key) + ' · ' + this.formatValue(value)).join(' | ');
            },
            async loadRecords(result, page) {
                result.loading = true;
                result.error = '';
                try {
                    const response = await fetch(@json(route('athena.records', [], false)), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        },
                        body: JSON.stringify({ token: result.page.token, page })
                    });
                    const data = await response.json().catch(() => null);
                    if (!response.ok || !data?.page) throw new Error();
                    result.page = { ...data.page, token: result.page.token };
                } catch {
                    result.error = 'The records could not be loaded. Ask Athena again to refresh the list.';
                } finally {
                    result.loading = false;
                }
            },
            scrollToEnd() {
                this.$nextTick(() => this.$refs.end?.scrollIntoView({ behavior: 'smooth' }));
            }
        };
    }
</script>
