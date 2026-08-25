define([], function() {
    const escapeHtml = value => String(value).replace(/[&<>'"]/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
    })[character]);

    const render = (config, state) => {
        const status = document.getElementById('livecourse-status');
        const stage = document.getElementById('livecourse-stage');
        const contentStage = document.getElementById('livecourse-content-stage');
        if (!status || !stage || !contentStage) {
            return;
        }
        if (!state.active) {
            status.textContent = config.strings.closed;
            stage.innerHTML = '';
            contentStage.innerHTML = '';
            return;
        }
        if (state.material) {
            const material = state.material;
            let materialHtml = `<div class="livecourse-slide-counter">${material.position} / ${material.total}</div>` +
                `<h2>${escapeHtml(material.title)}</h2>${material.description || ''}`;
            if (material.type === 'page') {
                materialHtml += `<div class="livecourse-page-content">${material.content || ''}</div>`;
            } else if (material.embedurl) {
                materialHtml += `<iframe class="livecourse-material-video" src="${escapeHtml(material.embedurl)}" ` +
                    `allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" ` +
                    `allowfullscreen title="${escapeHtml(material.title)}"></iframe>`;
            } else if (material.url) {
                materialHtml += `<a class="btn btn-outline-primary" target="_blank" rel="noopener noreferrer" ` +
                    `href="${escapeHtml(material.url)}">${escapeHtml(config.strings.openmaterial)}</a>`;
            }
            contentStage.innerHTML = materialHtml;
        } else {
            contentStage.innerHTML = `<div class="livecourse-empty-slide">${escapeHtml(config.strings.waitingmaterial)}</div>`;
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

    const bindTeacherControls = config => {
        document.querySelectorAll('.livecourse-realtime-form').forEach(form => {
            form.addEventListener('submit', async event => {
                event.preventDefault();
                const button = form.querySelector('button[type="submit"]');
                if (button) {
                    button.disabled = true;
                }
                try {
                    await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        credentials: 'same-origin'
                    });
                    await refresh(config);
                } finally {
                    if (button) {
                        button.disabled = false;
                    }
                }
            });
        });
    };

    return {
        init: config => {
            refresh(config);
            connect(config);
            if (config.teacher) {
                bindTeacherControls(config);
            }
        }
    };
});
