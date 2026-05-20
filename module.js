(function () {
    if (window.__czzz_indexnow_pc_page_bound__) {
        return;
    }
    window.__czzz_indexnow_pc_page_bound__ = true;

    function closest(el, selector) {
        while (el && el.nodeType === 1) {
            if (el.matches(selector)) return el;
            el = el.parentElement;
        }
        return null;
    }

    function root() {
        return document.querySelector('.czzz-indexnow-page');
    }

    function apiUrl() {
        var el = root();
        return el ? el.getAttribute('data-api-url') : '';
    }

    function text(value) {
        return value == null ? '' : String(value);
    }

    function escapeHtml(value) {
        return text(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function setMessage(textValue, ok) {
        var el = document.querySelector('.czzz-indexnow-message');
        if (!el) return;
        el.textContent = textValue || '';
        el.className = 'czzz-indexnow-message ' + (ok ? 'is-ok' : 'is-error');
    }

    function request(action, method, data) {
        var options = {
            method: method,
            credentials: 'same-origin',
            headers: {}
        };
        if (method === 'GET') {
            var query = new URLSearchParams(data || {});
            return fetch(apiUrl() + '?action=' + encodeURIComponent(action) + '&' + query.toString(), options)
                .then(function (res) { return res.json(); });
        }
        options.headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
        options.body = new URLSearchParams(data || {}).toString();
        return fetch(apiUrl() + '?action=' + encodeURIComponent(action), options)
            .then(function (res) { return res.json(); });
    }

    function get(action, data) {
        return request(action, 'GET', data);
    }

    function post(action, data) {
        return request(action, 'POST', data);
    }

    var state = {
        isAdmin: root() ? root().getAttribute('data-is-admin') === '1' : false,
        canViewReadonly: root() ? root().getAttribute('data-can-view-readonly') === '1' : false,
        logPage: 1,
        logPages: 1,
        logStatus: root() ? (root().getAttribute('data-default-status') || 'all') : 'all',
        logLimit: root() ? parseInt(root().getAttribute('data-default-limit') || '50', 10) : 50,
        batchMode: 'unsubmitted_or_failed',
        batchCandidates: [],
        batchRunning: false
    };

    function statusBadge(status, httpCode) {
        var cls = 'is-gray';
        var label = status || 'unknown';
        if (status === 'success' || status === 'accepted') cls = 'is-green';
        else if (status === 'failed') cls = 'is-red';
        else if (status === 'deduped') cls = 'is-yellow';
        else if (status === 'filtered' || status === 'disabled') cls = 'is-gray';
        if (httpCode) {
            label += ' · ' + httpCode;
        }
        return '<span class="czzz-indexnow-status-badge ' + cls + '">' + escapeHtml(label) + '</span>';
    }

    function renderLogs(data) {
        var tbody = document.querySelector('.czzz-indexnow-log-body');
        var summary = document.querySelector('.czzz-indexnow-log-summary');
        var pageInfo = document.querySelector('.czzz-indexnow-page-info');
        var prevBtn = document.querySelector('.czzz-indexnow-page-prev');
        var nextBtn = document.querySelector('.czzz-indexnow-page-next');
        if (!tbody || !data) return;
        var items = Array.isArray(data.items) ? data.items : [];
        state.logPage = parseInt(data.page || 1, 10);
        state.logPages = parseInt(data.pages || 1, 10);
        state.logStatus = text(data.status || 'all');
        state.logLimit = parseInt(data.limit || state.logLimit || 50, 10);

        var statusSelect = document.querySelector('.czzz-indexnow-log-status');
        var limitSelect = document.querySelector('.czzz-indexnow-log-limit');
        if (statusSelect) statusSelect.value = state.logStatus;
        if (limitSelect) limitSelect.value = String(state.logLimit);

        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="' + (state.isAdmin ? 9 : 8) + '">暂无提交日志</td></tr>';
        } else {
            tbody.innerHTML = items.map(function (row) {
                var createdAt = row.created_at ? new Date(parseInt(row.created_at, 10) * 1000).toLocaleString('zh-CN', {hour12: false}) : '-';
                var retryHtml = state.isAdmin && row.id ? '<button type="button" class="czzz-indexnow-retry" data-id="' + escapeHtml(row.id) + '">重新提交</button>' : '-';
                return '<tr>'
                    + '<td>' + escapeHtml(createdAt) + '</td>'
                    + '<td><a href="' + escapeHtml(row.url || '') + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(row.url || '') + '</a></td>'
                    + '<td>' + escapeHtml(row.submit_type || '') + '</td>'
                    + '<td>' + escapeHtml(row.endpoint || '') + '</td>'
                    + '<td>' + statusBadge(text(row.status || ''), parseInt(row.http_code || 0, 10)) + '</td>'
                    + '<td>' + escapeHtml(row.http_code || 0) + '</td>'
                    + '<td>' + escapeHtml(row.attempts || 0) + '</td>'
                    + '<td>' + escapeHtml(row.error_message || '-') + '</td>'
                    + (state.isAdmin ? '<td>' + retryHtml + '</td>' : '')
                    + '</tr>';
            }).join('');
        }

        if (summary) {
            summary.textContent = '当前筛选共 ' + (data.total || 0) + ' 条，正在查看第 ' + state.logPage + ' / ' + state.logPages + ' 页。';
        }
        if (pageInfo) {
            pageInfo.textContent = '第 ' + state.logPage + ' / ' + state.logPages + ' 页';
        }
        if (prevBtn) prevBtn.disabled = state.logPage <= 1;
        if (nextBtn) nextBtn.disabled = state.logPage >= state.logPages;
    }

    function loadLogs(page) {
        if (page) state.logPage = page;
        var summary = document.querySelector('.czzz-indexnow-log-summary');
        if (summary) summary.textContent = '正在加载日志...';
        return get('logs', {
            page: String(state.logPage),
            status: state.logStatus,
            limit: String(state.logLimit)
        }).then(function (json) {
            if (json && json.code == 1) {
                renderLogs(json.data || {});
                return;
            }
            if (summary) summary.textContent = json && json.msg ? json.msg : '日志加载失败';
        }).catch(function () {
            if (summary) summary.textContent = '日志加载失败';
        });
    }

    function renderBatchPreview(data) {
        var summary = document.querySelector('.czzz-indexnow-batch-summary');
        var preview = document.querySelector('.czzz-indexnow-batch-preview');
        if (summary) {
            summary.textContent = data.total > 0
                ? data.mode_label + '：共找到 ' + data.total + ' 条候选链接。'
                : data.mode_label + '：当前没有待处理链接。';
        }
        if (!preview) return;
        var urls = Array.isArray(data.urls) ? data.urls : [];
        if (!urls.length) {
            preview.innerHTML = '<div class="czzz-indexnow-empty">暂无候选链接</div>';
            return;
        }
        preview.innerHTML = '<div class="czzz-indexnow-preview-list">'
            + urls.slice(0, 10).map(function (url) {
                return '<div class="czzz-indexnow-preview-item">' + escapeHtml(url) + '</div>';
            }).join('')
            + (urls.length > 10 ? '<div class="czzz-indexnow-preview-more">其余 ' + (urls.length - 10) + ' 条将在批量提交时逐条处理</div>' : '')
            + '</div>';
    }

    function selectedBatchMode() {
        var modeSelect = document.querySelector('.czzz-indexnow-batch-mode');
        return modeSelect ? modeSelect.value : 'never_submitted';
    }

    function previewBatch() {
        state.batchMode = selectedBatchMode();
        return get('batch_candidates', {mode: state.batchMode}).then(function (json) {
            if (!json || json.code != 1) {
                throw new Error(json && json.msg ? json.msg : '候选链接加载失败');
            }
            state.batchCandidates = Array.isArray(json.data && json.data.urls) ? json.data.urls : [];
            renderBatchPreview(json.data || {total: 0, urls: [], mode_label: '候选链接'});
            return state.batchCandidates;
        }).catch(function (err) {
            var summary = document.querySelector('.czzz-indexnow-batch-summary');
            if (summary) summary.textContent = err.message || '候选链接加载失败';
            throw err;
        });
    }

    function appendBatchResult(index, url, result) {
        var tbody = document.querySelector('.czzz-indexnow-batch-results');
        if (!tbody) return;
        if (tbody.getAttribute('data-has-results') !== '1') {
            tbody.innerHTML = '';
            tbody.setAttribute('data-has-results', '1');
        }
        tbody.insertAdjacentHTML('beforeend',
            '<tr>'
            + '<td>' + escapeHtml(index) + '</td>'
            + '<td>' + escapeHtml(url) + '</td>'
            + '<td>' + statusBadge(text(result.status || ''), parseInt(result.http_code || 0, 10)) + '</td>'
            + '<td>' + escapeHtml(result.http_code || 0) + '</td>'
            + '<td>' + escapeHtml(result.attempts || 0) + '</td>'
            + '<td>' + escapeHtml(result.msg || result.status || '') + '</td>'
            + '</tr>'
        );
    }

    function setBatchProgress(textValue) {
        var el = document.querySelector('.czzz-indexnow-batch-progress');
        if (el) el.textContent = textValue || '';
    }

    function setBatchButtons(disabled) {
        var buttons = document.querySelectorAll('.czzz-indexnow-preview-batch, .czzz-indexnow-start-batch, .czzz-indexnow-batch-mode');
        buttons.forEach(function (el) {
            el.disabled = !!disabled;
        });
    }

    function startBatch() {
        if (state.batchRunning) return Promise.resolve();
        var mode = selectedBatchMode();
        var proceed = state.batchCandidates.length && mode === state.batchMode ? Promise.resolve(state.batchCandidates) : previewBatch();
        return proceed.then(function (urls) {
            if (!urls.length) {
                setBatchProgress('没有需要提交的候选链接。');
                return;
            }
            state.batchRunning = true;
            setBatchButtons(true);
            setBatchProgress('开始逐条提交，共 ' + urls.length + ' 条。');
            var chain = Promise.resolve();
            urls.forEach(function (url, idx) {
                chain = chain.then(function () {
                    setBatchProgress('正在提交第 ' + (idx + 1) + ' / ' + urls.length + ' 条：' + url);
                    return post('submit', {
                        url: url,
                        force: state.batchMode === 'never_submitted' ? '0' : '1',
                        submit_type: state.batchMode === 'never_submitted' ? 'batch' : 'batch_retry'
                    }).then(function (json) {
                        var result = (json && json.data) || {};
                        result.msg = json && json.msg ? json.msg : '';
                        appendBatchResult(idx + 1, url, result);
                    });
                });
            });
            return chain.then(function () {
                setBatchProgress('批量逐条提交完成，共处理 ' + urls.length + ' 条。');
                state.batchRunning = false;
                setBatchButtons(false);
                return loadLogs(1);
            }).catch(function () {
                state.batchRunning = false;
                setBatchButtons(false);
                setBatchProgress('批量提交中断，请检查网络或服务端返回。');
                loadLogs(state.logPage);
            });
        }).catch(function () {
            state.batchRunning = false;
            setBatchButtons(false);
        });
    }

    document.addEventListener('submit', function (event) {
        var form = closest(event.target, '.czzz-indexnow-submit-form');
        if (!form) return;
        event.preventDefault();
        var url = form.querySelector('input[name="url"]');
        post('submit', {url: url ? url.value : '', force: '0', submit_type: 'manual'}).then(function (json) {
            setMessage(json.msg || '已提交', json.code == 1);
            loadLogs(1);
        }).catch(function () {
            setMessage('请求失败', false);
        });
    }, false);

    document.addEventListener('click', function (event) {
        var retry = closest(event.target, '.czzz-indexnow-retry');
        if (retry) {
            event.preventDefault();
            post('retry', {id: retry.getAttribute('data-id') || '0'}).then(function (json) {
                setMessage(json.msg || '已重试', json.code == 1);
                loadLogs(1);
            }).catch(function () {
                setMessage('请求失败', false);
            });
            return;
        }

        var maintain = closest(event.target, '.czzz-indexnow-maintain-key');
        if (maintain) {
            event.preventDefault();
            post('maintain_key_file', {}).then(function (json) {
                setMessage(json.msg || '已维护验证文件', json.code == 1);
            }).catch(function () {
                setMessage('请求失败', false);
            });
            return;
        }

        var regen = closest(event.target, '.czzz-indexnow-regenerate-key');
        if (regen) {
            event.preventDefault();
            if (!window.confirm('重新生成 Key 会删除旧验证文件，确认继续？')) return;
            post('regenerate_key', {}).then(function (json) {
                setMessage(json.msg || 'Key 已重新生成', json.code == 1);
                if (json.code == 1) window.location.reload();
            }).catch(function () {
                setMessage('请求失败', false);
            });
            return;
        }

        if (closest(event.target, '.czzz-indexnow-refresh-logs')) {
            event.preventDefault();
            loadLogs(state.logPage);
            return;
        }

        if (closest(event.target, '.czzz-indexnow-page-prev')) {
            event.preventDefault();
            if (state.logPage > 1) loadLogs(state.logPage - 1);
            return;
        }

        if (closest(event.target, '.czzz-indexnow-page-next')) {
            event.preventDefault();
            if (state.logPage < state.logPages) loadLogs(state.logPage + 1);
            return;
        }

        if (closest(event.target, '.czzz-indexnow-preview-batch')) {
            event.preventDefault();
            previewBatch();
            return;
        }

        if (closest(event.target, '.czzz-indexnow-start-batch')) {
            event.preventDefault();
            startBatch();
            return;
        }

        if (closest(event.target, '.czzz-indexnow-repair-recent')) {
            event.preventDefault();
            post('repair_recent', {source_limit: '20', submit_limit: '5'}).then(function (json) {
                setMessage(json.msg || '最近内容补扫完成', json.code == 1);
                loadLogs(1);
            }).catch(function () {
                setMessage('请求失败', false);
            });
        }
    }, false);

    document.addEventListener('change', function (event) {
        if (closest(event.target, '.czzz-indexnow-log-status')) {
            state.logStatus = event.target.value || 'all';
            loadLogs(1);
            return;
        }
        if (closest(event.target, '.czzz-indexnow-log-limit')) {
            state.logLimit = parseInt(event.target.value || '50', 10) || 50;
            loadLogs(1);
        }
    }, false);

    window.addEventListener('message', function (event) {
        var data = event.data || {};
        if (event.origin && event.origin !== window.location.origin) return;
        if (!data || data.type !== 'czzz-indexnow-refresh-logs') return;
        if (root() && state.canViewReadonly && document.querySelector('.czzz-indexnow-log-body')) {
            loadLogs(1);
        }
    }, false);

    if (root() && state.canViewReadonly && document.querySelector('.czzz-indexnow-log-body')) {
        loadLogs(1);
    }
})();
