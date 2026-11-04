(() => {
    'use strict';
    const newId = () => globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    const safeUrl = value => { try { const url = new URL(value, location.href); return ['http:', 'https:'].includes(url.protocol) ? url.href : null; } catch { return null; } };

    function initialize(root) {
        if (root.dataset.ready) return;
        root.dataset.ready = 'true';
        const api = root.dataset.apiUrl.replace(/\/$/, '');
        const contentApi = root.dataset.contentApiUrl.replace(/\/$/, '');
        const locale = root.dataset.locale;
        const storageKey = `ai-chatbot:${api}:${locale}`;
        const launcher = root.querySelector('.ai-chatbot-widget__launcher');
        const panel = root.querySelector('.ai-chatbot-widget__panel');
        const messages = root.querySelector('.ai-chatbot-widget__messages');
        const choices = root.querySelector('.ai-chatbot-widget__choices');
        const typing = root.querySelector('.ai-chatbot-widget__typing');
        const form = root.querySelector('.ai-chatbot-widget__form');
        const input = form.querySelector('textarea');
        const send = form.querySelector('button');
        const reset = root.querySelector('.ai-chatbot-widget__reset');
        let busy = false;
        let failedRequest = null;
        let categories = [];
        let state = {};
        try { state = JSON.parse(sessionStorage.getItem(storageKey) || '{}') || {}; } catch { state = {}; }
        const persist = () => { try { sessionStorage.setItem(storageKey, JSON.stringify({ id: state.id, token: state.token })); } catch {} };

        function addMessage(role, text, sources = [], failed = false) {
            const item = document.createElement('div');
            item.className = `ai-chatbot-widget__message ai-chatbot-widget__message--${role}${failed ? ' ai-chatbot-widget__message--error' : ''}`;
            item.setAttribute('role', failed ? 'alert' : 'article');
            const body = document.createElement('div');
            body.textContent = String(text || '');
            item.append(body);
            const validSources = Array.isArray(sources) ? sources.map(source => ({ source, href: safeUrl(source?.url) })).filter(item => item.href) : [];
            if (validSources.length) {
                const label = document.createElement('strong');
                label.textContent = root.dataset.sourcesLabel;
                const list = document.createElement('ul');
                list.className = 'ai-chatbot-widget__sources';
                validSources.forEach(({ source, href }) => {
                    const link = document.createElement('a');
                    link.href = href;
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    link.textContent = String(source.title || href);
                    const entry = document.createElement('li');
                    entry.append(link);
                    list.append(entry);
                });
                item.append(label, list);
            }
            if (failed) {
                const retry = document.createElement('button');
                retry.type = 'button';
                retry.className = 'ai-chatbot-widget__retry';
                retry.textContent = root.dataset.retryLabel;
                retry.addEventListener('click', () => failedRequest && submit(failedRequest.text, failedRequest.id));
                item.append(retry);
            }
            messages.append(item);
            messages.scrollTop = messages.scrollHeight;
            return item;
        }

        function actionButton(label, handler, secondary = false) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `ai-chatbot-widget__choice${secondary ? ' ai-chatbot-widget__choice--secondary' : ''}`;
            button.textContent = String(label || '');
            button.addEventListener('click', handler);
            return button;
        }

        function showChoices(items, options = {}) {
            choices.replaceChildren();
            const list = document.createElement('div');
            list.className = 'ai-chatbot-widget__choice-list';
            items.forEach(item => list.append(actionButton(item.label, item.action)));
            if (options.back) list.append(actionButton(root.dataset.backLabel, options.back, true));
            if (options.main) list.append(actionButton(root.dataset.mainMenuLabel, showMainMenu, true));
            choices.append(list);
        }

        function showLoading() {
            choices.replaceChildren();
            const status = document.createElement('div');
            status.className = 'ai-chatbot-widget__choice-status';
            status.setAttribute('role', 'status');
            status.textContent = root.dataset.loadingLabel;
            choices.append(status);
        }

        async function request(base, path, options = {}) {
            const headers = { Accept: 'application/json', 'Content-Type': 'application/json', ...(options.headers || {}) };
            if (base === api && state.token) headers['X-AI-Chatbot-Token'] = state.token;
            const response = await fetch(`${base}/${path}`, { credentials: 'same-origin', ...options, headers });
            let payload = {};
            try { payload = await response.json(); } catch {}
            if (!response.ok) { const error = new Error(payload?.error?.code || `http_${response.status}`); error.status = response.status; throw error; }
            return payload;
        }

        async function ensureConversation() {
            if (state.id) return;
            const payload = await request(api, 'conversations', { method: 'POST', body: JSON.stringify({ locale }) });
            state = { id: payload.data.id, token: payload.visitor_token || null };
            persist();
        }

        async function loadHistory() {
            if (!state.id) return false;
            try {
                const payload = await request(api, `conversations/${encodeURIComponent(state.id)}/messages?limit=50`);
                payload.data.forEach(message => addMessage(message.role, message.content, message.sources));
                return payload.data.length > 0;
            } catch (error) {
                if ([404, 410].includes(error.status)) { state = {}; persist(); return false; }
                addMessage('error', root.dataset.fallback, [], true);
                return true;
            }
        }

        function showMainMenu() {
            if (!categories.length) { choices.replaceChildren(); return; }
            showChoices(categories.map(category => ({ label: category.title, action: () => showCategory(category) })));
        }

        async function showCategory(category) {
            if (busy) return;
            busy = true;
            showLoading();
            try {
                const payload = await request(contentApi, `categories/${encodeURIComponent(category.id)}/questions?locale=${encodeURIComponent(locale)}`);
                const questions = Array.isArray(payload.data) ? payload.data : [];
                if (!questions.length) {
                    choices.replaceChildren();
                    const status = document.createElement('div');
                    status.className = 'ai-chatbot-widget__choice-status';
                    status.textContent = root.dataset.noQuestionsLabel;
                    choices.append(status, actionButton(root.dataset.backLabel, showMainMenu, true));
                    return;
                }
                showChoices(questions.map(question => ({ label: question.question, action: () => submitPredefined(question) })), { back: showMainMenu });
            } catch {
                choices.replaceChildren(actionButton(root.dataset.mainMenuLabel, showMainMenu, true));
                addMessage('error', root.dataset.fallback);
            } finally {
                busy = false;
            }
        }

        async function loadMenu(showWelcome) {
            showLoading();
            try {
                const [configPayload, categoriesPayload] = await Promise.all([
                    request(contentApi, `config?locale=${encodeURIComponent(locale)}`),
                    request(contentApi, `categories?locale=${encodeURIComponent(locale)}`),
                ]);
                const config = configPayload.data || {};
                if (showWelcome) addMessage('assistant', config.opening_message || root.dataset.welcome);
                categories = config.predefined_questions_enabled && Array.isArray(categoriesPayload.data) ? categoriesPayload.data : [];
                showMainMenu();
            } catch {
                choices.replaceChildren();
                if (showWelcome) addMessage('assistant', root.dataset.welcome);
            }
        }

        async function submitPredefined(question, requestId = newId()) {
            if (!question?.id || busy) return;
            busy = true; send.disabled = true; typing.hidden = false; choices.replaceChildren();
            const userMessage = addMessage('user', question.question);
            try {
                await ensureConversation();
                const payload = await request(api, 'messages', { method: 'POST', body: JSON.stringify({ conversation_id: state.id, idempotency_key: requestId, message: question.question, predefined_question_id: question.id, locale }) });
                addMessage('assistant', payload.data.content, payload.data.sources);
                const followups = Array.isArray(payload.data.followups) ? payload.data.followups.filter((item, index, all) => item?.id && all.findIndex(other => other.id === item.id) === index) : [];
                showChoices(followups.map(item => ({ label: item.question, action: () => submitPredefined(item) })), { main: true });
            } catch {
                userMessage.remove();
                addMessage('error', root.dataset.errorLabel || root.dataset.fallback);
                showMainMenu();
            } finally {
                busy = false; send.disabled = false; typing.hidden = true; input.focus();
            }
        }

        async function submit(value, requestId = newId()) {
            const text = String(value || '').trim();
            if (!text || busy) return;
            failedRequest = { text, id: requestId };
            busy = true; send.disabled = true; typing.hidden = false; choices.replaceChildren();
            const userMessage = addMessage('user', text);
            input.value = '';
            try {
                await ensureConversation();
                const payload = await request(api, 'messages', { method: 'POST', body: JSON.stringify({ conversation_id: state.id, idempotency_key: requestId, message: text, locale }) });
                addMessage('assistant', payload.data.content, payload.data.sources);
                failedRequest = null;
                showMainMenu();
            } catch {
                userMessage.remove();
                addMessage('error', root.dataset.errorLabel || root.dataset.fallback, [], true);
                showMainMenu();
            } finally {
                busy = false; send.disabled = false; typing.hidden = true; input.focus();
            }
        }

        async function resetChat() {
            if (busy) return;
            busy = true;
            reset.disabled = true;
            send.disabled = true;
            typing.hidden = false;
            try {
                if (state.id) await request(api, `conversations/${encodeURIComponent(state.id)}`, { method: 'DELETE' });
            } catch {
            } finally {
                state = {};
                try { sessionStorage.removeItem(storageKey); } catch {}
                messages.replaceChildren();
                choices.replaceChildren();
                failedRequest = null;
                input.value = '';
                busy = false;
                reset.disabled = false;
                send.disabled = false;
                typing.hidden = true;
                await loadMenu(true);
                input.focus();
            }
        }

        const open = () => { panel.hidden = false; launcher.hidden = true; launcher.setAttribute('aria-expanded', 'true'); input.focus(); };
        const hide = () => { panel.hidden = true; launcher.hidden = false; launcher.setAttribute('aria-expanded', 'false'); launcher.focus(); };
        launcher.addEventListener('click', open);
        root.querySelector('.ai-chatbot-widget__minimize').addEventListener('click', hide);
        root.querySelector('.ai-chatbot-widget__close').addEventListener('click', hide);
        reset.addEventListener('click', resetChat);
        panel.addEventListener('keydown', event => { if (event.key === 'Escape') hide(); });
        form.addEventListener('submit', event => { event.preventDefault(); submit(input.value); });
        input.addEventListener('keydown', event => { if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); form.requestSubmit(); } });
        loadHistory().then(hasHistory => loadMenu(!hasHistory));
    }

    const start = () => document.querySelectorAll('[data-ai-chatbot-widget]').forEach(initialize);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
    document.addEventListener('ai-chatbot:init', start);
})();
