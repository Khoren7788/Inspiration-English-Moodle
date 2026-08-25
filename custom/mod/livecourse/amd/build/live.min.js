define([], function() {
    const escapeHtml = value => String(value).replace(/[&<>'"]/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
    })[character]);

    const render = (config, state) => {
        const status = document.getElementById('livecourse-status');
        const stage = document.getElementById('livecourse-stage');
        if (!status || !stage) {
            return;
        }
        if (!state.active) {
            status.textContent = config.strings.closed;
            stage.innerHTML = '';
            return;
        }
        if (!state.question) {
            status.textContent = config.strings.waiting;
            stage.innerHTML = '';
            return;
        }

        const question = state.question;
        status.textContent = question.answer ? config.strings.submitted : '';
        let html = `<h3>${escapeHtml(question.text)}</h3>`;
        if (config.teacher && Number.isInteger(question.responsecount)) {
            html += `<p class="livecourse-results">${escapeHtml(config.strings.responses)}: ` +
                `<strong>${question.responsecount}</strong> · ${escapeHtml(config.strings.correct)}: ` +
                `<strong>${question.correctcount}</strong></p>`;
        }
        Object.entries(question.options || {}).forEach(([key, label]) => {
            const count = config.teacher && question.counts ?
                `<span class="livecourse-count">${question.counts[key]}</span>` : '';
            const disabled = config.teacher || question.answer ? ' disabled' : '';
            const selected = question.answer === key ? ' btn-primary' : ' btn-outline-primary';
            html += `<button class="btn${selected} livecourse-option" data-answer="${key}"${disabled}>` +
                `<strong>${key.toUpperCase()}.</strong> ${escapeHtml(label)}${count}</button>`;
        });
        if (!config.teacher && !question.answer && ['shortanswer', 'gapfill'].includes(question.type)) {
            html += `<input class="form-control mb-2" id="livecourse-text-answer" maxlength="10000">` +
                `<button class="btn btn-primary" data-submit-text>${escapeHtml(config.strings.submit)}</button>`;
        }
        if (!config.teacher && !question.answer && question.type === 'matching') {
            question.pairs.left.forEach((left, index) => {
                html += `<label class="form-label" for="livecourse-match-${index}">${escapeHtml(left)}</label>` +
                    `<select class="form-select mb-2" id="livecourse-match-${index}" data-match="${escapeHtml(left)}">` +
                    `<option value="">${escapeHtml(config.strings.choose)}</option>` +
                    question.pairs.right.map(right => `<option value="${escapeHtml(right)}">${escapeHtml(right)}</option>`).join('') +
                    `</select>`;
            });
            html += `<button class="btn btn-primary" data-submit-match>${escapeHtml(config.strings.submit)}</button>`;
        }
        stage.innerHTML = html;

        if (!config.teacher && !question.answer) {
            const submit = async answer => {
                stage.querySelectorAll('button, input, select').forEach(item => item.disabled = true);
                const body = new URLSearchParams({
                    id: config.cmid, action: 'respond', answer, sesskey: config.sesskey
                });
                await fetch(`${M.cfg.wwwroot}/mod/livecourse/api.php`, {
                    method: 'POST', body, credentials: 'same-origin'
                });
                await refresh(config);
            };
            stage.querySelectorAll('[data-answer]').forEach(button => {
                button.addEventListener('click', () => submit(button.dataset.answer));
            });
            stage.querySelector('[data-submit-text]')?.addEventListener('click', () => {
                const value = stage.querySelector('#livecourse-text-answer').value.trim();
                if (value) {
                    submit(value);
                }
            });
            stage.querySelector('[data-submit-match]')?.addEventListener('click', () => {
                const answer = {};
                let complete = true;
                stage.querySelectorAll('[data-match]').forEach(select => {
                    answer[select.dataset.match] = select.value;
                    complete = complete && Boolean(select.value);
                });
                if (complete) {
                    submit(JSON.stringify(answer));
                }
            });
        }
    };

    const refresh = async config => {
        try {
            const response = await fetch(`${M.cfg.wwwroot}/mod/livecourse/api.php?id=${config.cmid}`, {
                credentials: 'same-origin',
                cache: 'no-store'
            });
            if (response.ok) {
                render(config, await response.json());
            }
        } catch (error) {
            // A WebSocket reconnect or the next event will retry the state request.
        }
    };

    const connect = config => {
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const url = `${protocol}//${window.location.host}/livecourse-ws?token=${encodeURIComponent(config.wstoken)}`;
        let reconnectDelay = 1000;

        const openSocket = () => {
            const socket = new WebSocket(url);
            socket.addEventListener('open', () => {
                reconnectDelay = 1000;
            });
            socket.addEventListener('message', event => {
                try {
                    const message = JSON.parse(event.data);
                    if (message.type === 'refresh' || message.type === 'connected') {
                        refresh(config);
                    }
                } catch (error) {
                    // Ignore malformed gateway messages.
                }
            });
            socket.addEventListener('close', () => {
                window.setTimeout(openSocket, reconnectDelay);
                reconnectDelay = Math.min(reconnectDelay * 2, 30000);
            });
            socket.addEventListener('error', () => socket.close());
        };
        openSocket();
    };

    return {
        init: config => {
            refresh(config);
            connect(config);
        }
    };
});
