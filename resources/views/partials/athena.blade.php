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
            <button type="button" @click="open = false" class="rounded-lg p-2 text-white/80 hover:bg-white/10 hover:text-white" aria-label="Close Athena">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </header>

        <div x-ref="conversation" class="flex-1 space-y-4 overflow-y-auto bg-slate-50 p-4">
            <div x-show="messages.length === 0" class="rounded-xl border border-blue-100 bg-white p-4 shadow-sm">
                <p class="font-semibold text-slate-800">Hello! I’m Athena.</p>
                <p class="mt-1 text-sm leading-6 text-slate-600">Ask me about CBED operations, request an aggregate data summary, or ask me to draft a report.</p>
                <p class="mt-3 text-xs text-slate-500">Your question and aggregate CBED summaries are sent to the configured AI provider. Individual names and animal records are not included automatically; avoid entering sensitive personal information.</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" @click="ask('Summarize the current CBED data.')"
                            class="rounded-full border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-800 hover:bg-blue-100">Summarize CBED data</button>
                    <button type="button" @click="ask('Draft a concise CBED system report using the available aggregate data.')"
                            class="rounded-full border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-800 hover:bg-blue-100">Draft a report</button>
                </div>
            </div>

            <template x-for="(message, index) in messages" :key="index">
                <div class="flex" :class="message.role === 'user' ? 'justify-end' : 'justify-start'">
                    <div class="max-w-[90%] rounded-2xl px-4 py-3 text-sm leading-6 shadow-sm"
                         :class="message.role === 'user' ? 'rounded-br-md bg-[#0b1f3a] text-white' : 'rounded-bl-md border border-slate-200 bg-white text-slate-700'">
                        <div class="whitespace-pre-wrap break-words" x-text="message.content"></div>
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
            <label for="athena-question" class="sr-only">Ask Athena a question</label>
            <div class="flex items-end gap-2 rounded-2xl border border-slate-300 bg-white p-2 focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-100">
                <textarea id="athena-question" x-ref="question" x-model="question" rows="2" maxlength="2000"
                          @keydown.enter.prevent="if (!$event.shiftKey) send()"
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
            messages: [],
            ask(prompt) {
                this.question = prompt;
                this.send();
            },
            async send() {
                const content = this.question.trim();
                if (!content || this.busy) return;

                this.messages.push({ role: 'user', content });
                this.question = '';
                this.busy = true;
                this.scrollToEnd();

                try {
                    const response = await fetch(@json(route('athena.chat')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        },
                        body: JSON.stringify({ messages: this.messages.slice(-12) })
                    });
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.message || 'Athena could not answer right now.');
                    this.messages.push({ role: 'assistant', content: result.message });
                } catch (error) {
                    this.messages.push({ role: 'assistant', content: error.message || 'Athena could not answer right now.' });
                } finally {
                    this.busy = false;
                    this.scrollToEnd();
                }
            },
            scrollToEnd() {
                this.$nextTick(() => this.$refs.end?.scrollIntoView({ behavior: 'smooth' }));
            }
        };
    }
</script>
