(function () {
    'use strict';
    const number = value => typeof value === 'number' && Number.isFinite(value) && value >= 0 ? value : null;
    function recommend(encoders) {
        return encoders.filter(e => e.state === 'online' && number(e.metrics?.queue) !== null && number(e.metrics?.capacity) > 0)
            .sort((a, b) => (a.metrics.queue / a.metrics.capacity - b.metrics.queue / b.metrics.capacity) ||
                b.metrics.capacity - a.metrics.capacity || (a.responseMs ?? Infinity) - (b.responseMs ?? Infinity) ||
                (b.metrics.memoryFree ?? 0) - (a.metrics.memoryFree ?? 0) || a.id - b.id)[0] || null;
    }
    function totals(encoders, key) {
        const online = encoders.filter(e => e.state === 'online');
        const reporting = online.filter(e => number(e.metrics?.[key]) !== null);
        return {value: reporting.length ? reporting.reduce((sum, e) => sum + e.metrics[key], 0) : null, reporting: reporting.length, total: encoders.length};
    }
    if (typeof module !== 'undefined' && module.exports) module.exports = {recommend, totals};
    if (typeof document === 'undefined') return;
    const config = JSON.parse(document.getElementById('networkConfig').textContent);
    const byId = id => document.getElementById(id);
    const text = (id, value) => { byId(id).textContent = value; };
    const node = (tag, content, className) => {
        const item = document.createElement(tag);
        if (content !== undefined && content !== null) item.textContent = content;
        if (className) item.className = className;
        return item;
    };
    const display = value => number(value) === null ? '—' : value.toLocaleString('en-US');
    const bytes = value => {
        if (number(value) === null) return 'Not reported';
        if (value === 0) return '0 B';
        const index = Math.min(4, Math.floor(Math.log(value) / Math.log(1024)));
        return (value / Math.pow(1024, index)).toLocaleString('en-US', {maximumFractionDigits: 1}) + ' ' + ['B', 'KB', 'MB', 'GB', 'TB'][index];
    };
    const time = value => new Date(value).toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit', second: '2-digit'});
    const labels = {checking: 'Checking', online: 'Online', unavailable: 'Unavailable', restricted: 'Access denied', timeout: 'Timed out'};
    const login = byId('loginForm');
    if (login) {
        let encodedPass = config.autoLogin;
        byId('inputPassword').addEventListener('input', () => { encodedPass = false; });
        let signingIn = false;
        login.addEventListener('submit', async event => {
            event.preventDefault();
            if (signingIn || !login.reportValidity()) return;
            signingIn = true;
            const button = login.querySelector('button');
            button.disabled = true; button.textContent = 'Signing in…';
            byId('loginError').hidden = true;
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 25000);
            try {
                const response = await fetch(config.base + 'login', {method: 'POST', credentials: 'same-origin', signal: controller.signal,
                    body: new URLSearchParams({user: byId('inputUser').value, pass: byId('inputPassword').value, siteURL: byId('siteURL').value, encodedPass: String(encodedPass)})});
                const data = await response.json();
                if (!response.ok || !data || data.error || !data.streamer || !data.isLogged || !data.canUpload) throw new Error('Sign-in was not confirmed. Check the site URL, username, password, and upload permission.');
                const destination = new URL(config.base);
                destination.searchParams.set('justLogin', '1');
                if (data.PHPSESSID) destination.searchParams.set('PHPSESSID', data.PHPSESSID);
                location.assign(destination.href);
            } catch (error) {
                text('loginError', error.name === 'AbortError' ? 'Sign-in timed out. Check the video site connection and try again.' : error instanceof TypeError ? 'Unable to reach the sign-in service. Check the connection and try again.' : (error instanceof SyntaxError ? 'The sign-in service returned an invalid response. Check the server logs.' : error.message));
                byId('loginError').hidden = false;
            } finally { clearTimeout(timeout); signingIn = false; button.disabled = false; button.textContent = 'Sign in →'; }
        });
        if (config.autoLogin) login.requestSubmit();
        return;
    }
    const encoders = config.encoders.map(e => ({...e, state: 'checking', message: 'Waiting for the first status check.', metrics: null, responseMs: null, history: []}));
    // Prefer a registered encoder on the same host; remote sign-in requires public access to the AVideo site.
    const localEncoder = encoders.find(e => e.url && new URL(e.url).hostname === new URL(config.base).hostname);
    let selectedId = localEncoder?.id ?? encoders[0]?.id ?? null;
    let recommended = null;
    let manualSelection = false;
    let initialSelectionMade = false;
    let refreshing = false;
    let timer;
    let sessionExpired = false;
    let workspaceId = null;
    const workspaces = new Map();
    const showGlobalError = message => { text('globalError', message); byId('globalError').hidden = false; };
    const badge = encoder => node('span', labels[encoder.state] || 'Unavailable', 'status-badge ' + encoder.state);
    function renderSummary() {
        const online = encoders.filter(e => e.state === 'online').length;
        const checked = encoders.filter(e => e.state !== 'checking').length;
        text('onlineCount', checked || !encoders.length ? online : '—');
        text('availabilityNote', !encoders.length ? 'No encoders registered' : checked < encoders.length ? 'Checking ' + (encoders.length - checked) + ' server(s)' : online === encoders.length ? 'All encoders are responding' : (encoders.length - online) + ' encoder(s) need attention');
        for (const [key, valueId, noteId, description] of [['queue', 'queueCount', 'queueNote', 'Reported queue size'], ['encoding', 'encodingCount', 'encodingNote', 'Active encoding jobs'], ['capacity', 'capacityCount', 'capacityNote', 'Parallel encoding slots']]) {
            const total = totals(encoders, key);
            text(valueId, display(total.value));
            text(noteId, total.reporting ? description + ' · ' + total.reporting + '/' + total.total + ' servers' : 'Waiting for reported metrics');
        }
        text('fleetCoverage', checked + '/' + encoders.length + ' checked · ' + online + ' responding');
        recommended = recommend(encoders);
        byId('useRecommended').disabled = !recommended;
        if (recommended) {
            text('recommendedName', recommended.name);
            text('recommendedReason', recommended.metrics.queue + ' queued · ' + recommended.metrics.capacity + ' concurrent slots · ' + display(recommended.responseMs) + ' ms response. Best queue-to-capacity balance among reporting servers.');
        } else {
            text('recommendedName', checked < encoders.length ? 'Finding your best available encoder' : 'No recommendation available yet');
            text('recommendedReason', !encoders.length ? 'Register an encoder to begin monitoring your network.' : checked < encoders.length ? 'Comparing queue pressure, capacity, and response time.' : 'A confirmed status response with queue and capacity metrics is needed. Review the encoder details below.');
        }
    }
    function renderRows() {
        const search = byId('encoderSearch').value.toLowerCase().trim();
        const filter = byId('statusFilter').value;
        const visible = encoders.filter(e => (e.name + ' ' + e.host).toLowerCase().includes(search) &&
            (filter === 'all' || (filter === 'online' ? e.state === 'online' : ['unavailable', 'restricted', 'timeout'].includes(e.state))));
        const body = byId('encoderRows');
        body.replaceChildren();
        for (const encoder of visible) {
            const row = node('tr', null, selectedId === encoder.id ? 'selected' : '');
            const identityCell = node('td');
            const identity = node('div', null, 'encoder-identity');
            const info = node('div');
            const name = node('button', encoder.name, 'encoder-name encoder-name-button'); name.type = 'button'; name.title = 'Open ' + encoder.name; name.addEventListener('click', () => openEncoder(encoder.id));
            if (recommended?.id === encoder.id) name.append(node('span', 'Recommended', 'best-tag'));
            info.append(name, node('div', encoder.host, 'encoder-domain'));
            identity.append(node('span', '▤', 'server-icon'), info); identityCell.append(identity);
            const statusCell = node('td'); statusCell.append(badge(encoder)); statusCell.title = encoder.message;
            const loadCell = node('td');
            const queue = encoder.state === 'online' ? encoder.metrics?.queue : null;
            const capacity = encoder.state === 'online' ? encoder.metrics?.capacity : null;
            const load = node('div', display(queue), 'queue-value'); load.append(node('small', ' / ' + display(capacity)));
            const meter = node('div', null, 'mini-meter' + (queue > capacity && capacity ? ' busy' : ''));
            const fill = node('span'); fill.style.width = number(queue) !== null && capacity ? Math.min(100, queue / capacity * 100) + '%' : '0%';
            meter.append(fill); const queueButton = node('button', 'View queue', 'queue-button'); queueButton.type = 'button'; queueButton.setAttribute('aria-label', 'View queue on ' + encoder.name); queueButton.addEventListener('click', () => openEncoder(encoder.id, true)); loadCell.append(load, meter, queueButton);
            const responseCell = node('td', encoder.state === 'online' && encoder.responseMs !== null ? display(encoder.responseMs) + ' ms' : '—', 'metric-text');
            const uploadCell = node('td', encoder.state === 'online' ? (encoder.metrics?.uploadLimit || 'Not reported') : '—', 'metric-text');
            const actionCell = node('td');
            const select = node('button', 'Encoder & queue', 'button secondary quick-open');
            select.type = 'button'; select.setAttribute('aria-label', 'Open ' + encoder.name);
            select.addEventListener('click', () => openEncoder(encoder.id));
            const direct = node('a', '↗', 'direct-encoder');
            const directURL = new URL(encoder.launchURL || encoder.url || config.base); directURL.searchParams.delete('noNavbar');
            direct.href = directURL.href; direct.target = '_blank'; direct.rel = 'noopener noreferrer';
            direct.setAttribute('aria-label', 'Open ' + encoder.name + ' directly in a new tab'); direct.title = 'Open directly without an iframe';
            actionCell.append(select, direct);
            row.append(identityCell, statusCell, loadCell, responseCell, uploadCell, actionCell);
            row.addEventListener('click', event => { if (!event.target.closest('button, a')) openEncoder(encoder.id); });
            body.append(row);
        }
        byId('emptyFleet').hidden = visible.length > 0;
        text('emptyFleetText', encoders.length ? 'Try another search or status filter.' : 'No encoders are registered. Ask your administrator to add servers using the encoder management tool.');
    }
    function renderChart(encoder) {
        const container = byId('responseChart'); container.replaceChildren();
        const history = encoder.history;
        if (!history.length) {
            container.append(node('span', 'No successful checks yet', 'chart-placeholder'));
            container.setAttribute('aria-label', 'No successful response time samples yet');
            return;
        }
        const ns = 'http://www.w3.org/2000/svg';
        const svg = document.createElementNS(ns, 'svg'); svg.setAttribute('viewBox', '0 0 500 130'); svg.setAttribute('preserveAspectRatio', 'none');
        const max = Math.max(100, ...history.map(h => h.value)) * 1.15;
        const points = history.map((h, i) => [(history.length === 1 ? 250 : i / (history.length - 1) * 480 + 10), 115 - h.value / max * 100]);
        if (history.length > 1) {
            const area = document.createElementNS(ns, 'polygon');
            area.setAttribute('points', points[0][0] + ',130 ' + points.map(p => p.join(',')).join(' ') + ' ' + points.at(-1)[0] + ',130');
            area.setAttribute('fill', 'var(--chart-fill, #edf6fb)'); svg.append(area);
            const line = document.createElementNS(ns, 'polyline'); line.setAttribute('points', points.map(p => p.join(',')).join(' ')); line.setAttribute('fill', 'none'); line.setAttribute('stroke', 'var(--blue, #009fe2)'); line.setAttribute('stroke-width', '2'); line.setAttribute('vector-effect', 'non-scaling-stroke'); svg.append(line);
        }
        for (let i = 0; i < points.length; i++) {
            const dot = document.createElementNS(ns, 'circle'); dot.setAttribute('cx', points[i][0]); dot.setAttribute('cy', points[i][1]); dot.setAttribute('r', '3'); dot.setAttribute('fill', 'var(--accent, #006c9b)');
            const title = document.createElementNS(ns, 'title'); title.textContent = history[i].value + ' ms at ' + time(history[i].at); dot.append(title); svg.append(dot);
        }
        container.append(svg);
        container.setAttribute('aria-label', history.length + ' successful checks. Latest: ' + history.at(-1).value + ' milliseconds.');
    }
    function renderDetail() {
        const encoder = encoders.find(e => e.id === selectedId);
        byId('encoderDetail').hidden = !encoder;
        if (!encoder) return;
        const metrics = encoder.state === 'online' ? encoder.metrics || {} : {};
        text('selectedName', encoder.name);
        const host = byId('selectedHost'); host.textContent = encoder.host + ' ↗';
        if (encoder.url) host.href = encoder.url; else host.removeAttribute('href');
        text('selectedState', labels[encoder.state]); byId('selectedState').className = 'status-badge ' + encoder.state;
        text('selectedNotice', encoder.message + (encoder.checkedAt ? ' Checked at ' + time(encoder.checkedAt) + '.' : ''));
        byId('selectedNotice').className = 'detail-notice' + (encoder.state !== 'online' && encoder.state !== 'checking' ? ' warning' : '');
        for (const [id, key] of [['selectedQueue', 'queue'], ['selectedCapacity', 'capacity'], ['selectedEncoding', 'encoding'], ['selectedDownloading', 'downloading'], ['selectedTransferring', 'transferring']]) text(id, display(metrics[key]));
        text('selectedUpload', metrics.uploadLimit || '—');
        text('memoryPercent', number(metrics.memoryUsedPercent) !== null ? metrics.memoryUsedPercent + '% used' : 'Not reported');
        byId('memoryBar').style.width = number(metrics.memoryUsedPercent) !== null ? metrics.memoryUsedPercent + '%' : '0%';
        text('memoryCaption', number(metrics.memoryTotal) !== null ? bytes(metrics.memoryFree) + ' free of ' + bytes(metrics.memoryTotal) : 'Memory metrics are not available for this encoder.');
        text('encoderVersion', metrics.version ? 'Encoder v' + metrics.version : 'Version not reported');
        text('currentResponse', encoder.state === 'online' ? display(encoder.responseMs) + ' ms' : '—');
        text('responseCaption', encoder.history.length ? encoder.history.length + ' successful check(s) this visit' : 'Waiting for a successful check');
        byId('openWorkspace').disabled = !encoder.launchURL;
        renderChart(encoder);
    }
    function render() { renderSummary(); renderRows(); renderDetail(); if (workspaceId !== null) workspaceMessage(workspaces.get(workspaceId)); }
    function selectEncoder(id, userSelected) {
        selectedId = id;
        if (userSelected) manualSelection = true;
        renderRows(); renderDetail();

    }
    async function checkEncoder(encoder) {
        const controller = new AbortController(); const timeout = setTimeout(() => controller.abort(), 22000);
        try {
            const response = await fetch(config.base + 'view/networkStatus.json.php?id=' + encoder.id, {credentials: 'same-origin', cache: 'no-store', signal: controller.signal});
            if (response.status === 403) {
                sessionExpired = true;
                showGlobalError('Your session has expired or no longer has upload access. Reload the page to sign in again.');
                throw new Error('Sign-in is required to check status.');
            }
            const data = await response.json();
            if (!response.ok || data.error || !labels[data.state]) throw new Error(data.msg || 'The network server could not complete this check.');
            encoder.state = data.state; encoder.message = data.message; encoder.metrics = data.metrics;
            encoder.responseMs = number(data.responseMs); encoder.checkedAt = data.checkedAt;
            if (encoder.state === 'online' && encoder.responseMs !== null) {
                encoder.history.push({value: encoder.responseMs, at: encoder.checkedAt});
                if (encoder.history.length > 20) encoder.history.shift();
            }
        } catch (error) {
            encoder.state = error.name === 'AbortError' ? 'timeout' : 'unavailable';
            encoder.message = error.name === 'AbortError' ? 'The network status check timed out. Try refreshing the status.' : error instanceof SyntaxError ? 'The network server returned an invalid response. Review its PHP log.' : error instanceof TypeError ? 'The network status service could not be reached. Check your connection.' : error.message;
            encoder.metrics = null; encoder.responseMs = null; encoder.checkedAt = new Date().toISOString();
        } finally { clearTimeout(timeout); render(); }
    }
    async function refresh() {
        if (refreshing || sessionExpired || document.hidden) return;
        clearTimeout(timer); refreshing = true; byId('refreshButton').disabled = true;
        text('refreshStatus', 'Checking encoder status…');
        let cursor = 0;
        async function worker() { while (cursor < encoders.length && !sessionExpired) await checkEncoder(encoders[cursor++]); }
        await Promise.all(Array.from({length: Math.min(4, encoders.length)}, worker));
        if (!initialSelectionMade) {
            if (!manualSelection && recommended && workspaceId === null) selectEncoder(recommended.id, false);
            initialSelectionMade = true;
        }
        refreshing = false; byId('refreshButton').disabled = sessionExpired;
        text('refreshStatus', sessionExpired ? 'Sign-in required' : 'Last checked ' + time(new Date()));
        if (!sessionExpired) timer = setTimeout(refresh, 30000);
    }
    byId('refreshButton').addEventListener('click', refresh);
    byId('encoderSearch').addEventListener('input', renderRows);
    byId('statusFilter').addEventListener('change', renderRows);
    function workspaceMessage(entry) {
        const encoder = encoders.find(e => e.id === entry.id);
        text('workspaceStatus', entry.loaded ? 'Encoder page opened. Complete sign-in inside the encoder if prompted.' : 'Connecting to ' + encoder.host + '…');
        const warning = byId('workspaceWarning');
        const failed = ['restricted', 'timeout', 'unavailable'].includes(encoder.state);
        warning.hidden = !entry.slow && !failed;
        warning.textContent = failed ? encoder.message + ' You can also use Open directly to check the encoder in its own tab.' : 'Still blank or signing in? Open directly to avoid iframe cookie restrictions. If the new tab also fails, check the encoder connection or sign in there.';
    }
    function renderWorkspaceTabs() {
        const tabs = byId('workspaceTabs'); tabs.replaceChildren();
        for (const encoder of encoders) {
            const button = node('button', encoder.name, 'workspace-tab');
            button.type = 'button'; button.id = 'workspace-tab-' + encoder.id;
            button.setAttribute('role', 'tab'); button.setAttribute('aria-selected', String(workspaceId === encoder.id));
            if (workspaces.has(encoder.id)) button.setAttribute('aria-controls', 'workspace-frame-' + encoder.id);
            button.addEventListener('click', () => openEncoder(encoder.id)); tabs.append(button);
        }
    }
    function openEncoder(id, queue = false, scroll = true) {
        const encoder = encoders.find(e => e.id === id);
        if (!encoder?.launchURL) return;
        selectEncoder(id, true); workspaceId = id;
        let entry = workspaces.get(id);
        if (!entry) {
            const frame = document.createElement('iframe');
            frame.id = 'workspace-frame-' + id; frame.title = encoder.name + ' encoder and sharing queue';
            frame.referrerPolicy = 'no-referrer'; frame.allow = 'fullscreen';
            const launch = new URL(encoder.launchURL); if (queue) launch.hash = 'encoding';
            entry = {id, frame, loaded: false, slow: false}; workspaces.set(id, entry);
            frame.addEventListener('load', () => { clearTimeout(entry.timer); entry.loaded = true; entry.slow = false; if (workspaceId === id) workspaceMessage(entry); });
            frame.addEventListener('error', () => { entry.slow = true; if (workspaceId === id) workspaceMessage(entry); });
            entry.timer = setTimeout(() => { entry.slow = true; if (workspaceId === id) workspaceMessage(entry); }, 15000);
            frame.src = launch.href; byId('frameContainer').append(frame);
        }
        for (const [otherId, other] of workspaces) other.frame.hidden = otherId !== id;
        byId('workspace').hidden = false;
        text('workspaceName', encoder.name);
        const direct = new URL(encoder.launchURL); direct.searchParams.delete('noNavbar'); if (queue) direct.hash = 'encoding';
        byId('workspaceExternal').href = direct.href;
        workspaceMessage(entry); renderWorkspaceTabs();
        if (scroll) byId('workspace').scrollIntoView({behavior: 'smooth', block: 'start'});
    }
    byId('useRecommended').addEventListener('click', () => { if (recommended) openEncoder(recommended.id); });
    byId('openWorkspace').addEventListener('click', () => openEncoder(selectedId));
    byId('retryWorkspace').addEventListener('click', () => {
        const entry = workspaces.get(workspaceId); if (!entry) return;
        // Reload is explicit: switching tabs never resets an upload or queue view.
        const id = workspaceId; clearTimeout(entry.timer); entry.frame.remove(); workspaces.delete(id); openEncoder(id);
    });
    byId('closeWorkspace').addEventListener('click', () => {
        const entry = workspaces.get(workspaceId); if (entry) { clearTimeout(entry.timer); entry.frame.remove(); workspaces.delete(workspaceId); }
        workspaceId = null;
        const next = workspaces.keys().next().value;
        if (next !== undefined) openEncoder(next, false, false);
        else { byId('workspace').hidden = true; byId('encoderSearch').focus(); }
    });
    document.addEventListener('visibilitychange', () => {
        clearTimeout(timer);
        if (document.hidden) text('refreshStatus', 'Auto-refresh paused while this tab is inactive'); else refresh();
    });
    render();
    if (selectedId !== null) openEncoder(selectedId, true, false);
    refresh();
})();
