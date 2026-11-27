(() => {
    const dbName = 'cbed-monitoring';
    const storeName = 'entries';
    const shellCache = 'cbed-monitoring-v7';
    const rosterMaxAge = 15 * 60 * 1000;
    const rosterVersion = 3;
    let database;

    function openDatabase() {
        database ??= new Promise((resolve, reject) => {
            const request = indexedDB.open(dbName, 1);
            request.onupgradeneeded = () => request.result.createObjectStore(storeName);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        }).catch(error => { database = null; throw error; });
        return database;
    }

    async function entry(owner, key, change) {
        const db = await openDatabase();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, change ? 'readwrite' : 'readonly');
            const store = tx.objectStore(storeName);
            const request = store.get([owner, key]);
            let result;
            request.onsuccess = () => {
                result = request.result;
                if (!change) return;
                try {
                    result = change(result);
                    if (result === undefined) store.delete([owner, key]);
                    else store.put(result, [owner, key]);
                } catch (error) {
                    reject(error);
                    tx.abort();
                }
            };
            tx.oncomplete = () => resolve(result);
            tx.onabort = () => reject(tx.error || new Error('Monitoring data could not be saved.'));
        });
    }

    async function pending(owner) {
        const db = await openDatabase();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName);
            const range = IDBKeyRange.bound([owner, 'pending:'], [owner, 'pending:\uffff']);
            const request = tx.objectStore(storeName).getAll(range);
            request.onsuccess = () => resolve(request.result.sort((a, b) => a.savedAt - b.savedAt));
            request.onerror = () => reject(request.error);
        });
    }

    class MonitoringOffline {
        constructor({account, contextUrl, syncUrl, shell, onChange}) {
            Object.assign(this, {account, contextUrl, syncUrl, shell, onChange});
            this.online = navigator.onLine && !shell;
            this.locked = false;
            this.rosters = new Map();
            this.submitting = new Set();
            this.enabled = Boolean(window.isSecureContext && window.indexedDB && window.crypto?.randomUUID);
            this.channel = this.enabled && window.BroadcastChannel ? new BroadcastChannel('cbed-monitoring-' + account) : null;
            this.channel?.addEventListener('message', () => this.refresh().catch(() => {}));
        }

        async start() {
            if (!this.enabled) {
                this.onChange({online: navigator.onLine, drafts: [], lastSync: null, enabled: false});
                return;
            }
            try {
                await openDatabase();
                if (this.shell) {
                    const response = await (await caches.open(shellCache)).match('/monitoring/offline-shell');
                    this.locked = response?.headers.get('X-CBED-Monitoring-Account') !== this.account;
                }
            } catch {
                this.enabled = false;
                this.onChange({online: navigator.onLine, drafts: [], lastSync: null, enabled: false});
                return;
            }
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.addEventListener('message', event => {
                    if (event.data?.type === 'monitoring-locked' && (!event.data.account || event.data.account === this.account)) {
                        this.locked = true;
                        this.context = null;
                        this.rosters.clear();
                        this.refresh().catch(() => {});
                    }
                });
                this.registration = navigator.serviceWorker.register('/monitoring-sw.js')
                    .then(() => navigator.serviceWorker.ready).catch(() => navigator.serviceWorker.getRegistration());
            }
            window.addEventListener('online', () => this.run());
            window.addEventListener('offline', () => {
                this.online = false;
                this.refresh().catch(() => {});
            });
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) this.run();
            });
            setInterval(() => { if (!document.hidden) this.run(); }, 30000);
            await this.refresh();
            if (navigator.onLine) this.run();
        }

        async refresh() {
            const drafts = this.locked ? [] : await pending(this.account);
            const lastSync = this.locked ? null : await entry(this.account, 'lastSync');
            this.onChange({online: this.online, locked: this.locked, drafts, lastSync, enabled: this.enabled, syncing: Boolean(this.syncing), preparing: Boolean(this.preparing), message: this.message});
        }

        async request(url, options = {}) {
            const timeout = new AbortController();
            const timer = setTimeout(() => timeout.abort(), 25000);
            const cancel = () => timeout.abort();
            if (options.signal?.aborted) cancel();
            options.signal?.addEventListener('abort', cancel, {once: true});
            try {
                const response = await fetch(url, {
                    ...options, signal: timeout.signal, cache: 'no-store', credentials: 'same-origin',
                    headers: {Accept: 'application/json', 'X-CBED-Monitoring-Account': this.account, ...options.headers},
                });
                const body = await response.json().catch(() => ({}));
                if (!response.ok || response.redirected) {
                    const error = new Error(Object.values(body.errors ?? {}).flat()[0] || body.message || 'The server could not complete the monitoring request.');
                    error.status = response.redirected ? 401 : response.status;
                    error.reportId = body.report_id;
                    error.closed = body.closed === true;
                    throw error;
                }
                return body;
            } finally {
                clearTimeout(timer);
                options.signal?.removeEventListener('abort', cancel);
            }
        }

        async authorize() {
            if (this.locked) throw new Error('Sign in and reopen Monitoring with the account that saved these records.');
            const context = await this.request(this.contextUrl);
            if (context.account !== this.account) {
                const error = new Error('Sign in with the account that saved these records.');
                error.status = 403;
                throw error;
            }
            this.online = true;
            this.context = context;
            const {csrf, ...lookups} = context;
            await entry(this.account, 'context', () => lookups);
            return context;
        }

        authError(error) {
            return [401, 403, 419].includes(error.status);
        }

        async lock() {
            this.locked = true;
            this.context = null;
            this.rosters.clear();
            (await this.registration)?.active?.postMessage({type: 'lock-monitoring', account: this.account});
        }

        async roster(url, signal) {
            if (this.locked) throw new Error('Sign in and reopen Monitoring to unlock this account.');
            const cacheKey = 'roster:' + url;
            const cached = this.enabled ? await entry(this.account, cacheKey) : null;
            if (!navigator.onLine && cached) return this.useRoster(url, cached);
            try {
                const roster = await this.request(url + '?offline=1', {signal});
                if (!Array.isArray(roster.animals) || this.locked) throw new Error('Invalid or unavailable cooperative roster.');
                const saved = {...roster, version: rosterVersion, savedAt: Date.now()};
                if (this.enabled) await entry(this.account, cacheKey, () => saved);
                return this.useRoster(url, saved);
            } catch (error) {
                if (this.authError(error)) await this.lock();
                if (!signal?.aborted && !this.locked && !error.status && cached) return this.useRoster(url, cached);
                if (!error.status && !cached && !signal?.aborted) throw new Error('This cooperative is not yet available offline. Open Monitoring while connected to prepare its roster.');
                throw error;
            }
        }

        useRoster(url, roster) {
            this.rosters.set(url, roster);
            return roster.animals;
        }

        matingHistory(rosterUrl, animal) {
            return this.rosters.get(rosterUrl)?.matings?.[animal] ?? null;
        }

        async lookup(key) {
            if (this.locked) throw new Error('Sign in and reopen Monitoring to unlock this account.');
            return this.context?.[key] ?? (this.enabled ? (await entry(this.account, 'context'))?.[key] : null);
        }

        async prepare(context, force) {
            if (this.preparing || (!force && this.preparedAt && Date.now() - this.preparedAt < rosterMaxAge)) return;
            this.preparing = true;
            await this.refresh();
            try {
                const registration = await this.registration;
                if (!this.locked) registration?.active?.postMessage({type: 'prepare-monitoring', account: this.account});
                const jobs = context.cooperatives.slice();
                let failed = false;
                await Promise.all(Array.from({length: 3}, async () => {
                    while (jobs.length && !this.locked && navigator.onLine) {
                        const cooperative = jobs.shift();
                        const url = '/monitoring/cooperatives/' + cooperative.id + '/animals';
                        const cached = await entry(this.account, 'roster:' + url);
                        if (!force && cached?.version === rosterVersion && Date.now() - cached.savedAt < rosterMaxAge) continue;
                        try {
                            await this.roster(url);
                        } catch (error) {
                            failed = true;
                            if (this.authError(error)) {
                                this.message = 'Sign in again to update offline data.';
                                jobs.length = 0;
                            }
                        }
                    }
                }));
                if (!failed && !jobs.length) this.preparedAt = Date.now();
            } finally {
                this.preparing = false;
                await this.refresh();
            }
        }

        async save(snapshot, original = null) {
            if (this.locked) throw new Error('Sign in and reopen Monitoring to unlock this account.');
            const id = original?.id || crypto.randomUUID();
            const draft = {id, revision: crypto.randomUUID(), savedAt: original?.savedAt ?? Date.now(), ...JSON.parse(JSON.stringify(snapshot))};
            await entry(this.account, 'pending:' + id, current => {
                if (original && (!current || current.revision !== original.revision)) {
                    throw new Error('This local report changed in another tab. Close the editor and reopen it to review the latest version.');
                }
                return draft;
            });
            this.message = 'Monitoring report saved on this device.';
            this.channel?.postMessage('changed');
            await this.refresh();
            return draft;
        }

        async discardConfirmed(draft) {
            await entry(this.account, 'pending:' + draft.id, current =>
                current?.revision === draft.revision && (current.reportId || current.closed) ? undefined : current);
            this.channel?.postMessage('changed');
            await this.refresh();
        }

        run(manual = false) {
            if (!this.enabled || this.syncing || this.locked || !navigator.onLine) return this.syncing;
            this.syncing = this.synchronize(manual).finally(() => {
                this.syncing = null;
                this.refresh().catch(() => {});
            });
            return this.syncing;
        }

        async synchronize(manual) {
            try {
                const context = await this.authorize();
                if (this.locked) return;
                let synced = 0;
                for (const draft of await pending(this.account)) {
                    if (this.locked || !navigator.onLine) break;
                    if (draft.id === this.editing || draft.reportId || draft.closed || (draft.error && !manual)) continue;
                    this.submitting.add(draft.id);
                    try {
                        await this.request(this.syncUrl, {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': context.csrf},
                            body: JSON.stringify({...draft.payload, submission_id: draft.id}),
                        });
                        await entry(this.account, 'pending:' + draft.id, current =>
                            current?.revision === draft.revision ? undefined : current);
                        await entry(this.account, 'lastSync', () => Date.now());
                        synced++;
                    } catch (error) {
                        if (![404, 409, 422].includes(error.status)) throw error;
                        await entry(this.account, 'pending:' + draft.id, current => current?.revision === draft.revision
                            ? {...current, error: error.message, reportId: error.reportId, closed: error.closed} : current);
                    } finally {
                        this.submitting.delete(draft.id);
                        this.channel?.postMessage('changed');
                        await this.refresh();
                    }
                }
                if (synced) {
                    this.message = synced + (synced === 1 ? ' monitoring report synced.' : ' monitoring reports synced.');
                    window.dispatchEvent(new Event('monitoring-reports-synced'));
                }
                else if (manual) this.message = 'Sync checked. Review any pending reports shown below.';
                this.prepare(context, Boolean(synced) || manual).catch(() => {});
            } catch (error) {
                if (this.authError(error)) {
                    this.message = 'Sign in again to sync. Pending reports are stored on this device.';
                    await this.lock();
                } else {
                    this.online = Boolean(error.status);
                    this.message = error.status ? 'Sync could not complete. Your reports are still pending.' : 'Offline. Pending reports will sync when a connection is available.';
                }
            }
        }
    }

    window.MonitoringOffline = MonitoringOffline;
})();
