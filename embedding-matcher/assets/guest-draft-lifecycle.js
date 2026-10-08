(function () {
    'use strict';

    var config = window.guestDraftLifecycle;
    if (!config) {
        return;
    }

    var storageKey = 'curriculumGuestTabId';
    var channelName = 'curriculum-guest-draft';
    var channel = typeof BroadcastChannel === 'function' ? new BroadcastChannel(channelName) : null;
    var instanceId = createId();
    var tabId;

    function createId() {
        var bytes = new Uint8Array(32);
        window.crypto.getRandomValues(bytes);
        return Array.prototype.map.call(bytes, function (byte) {
            return byte.toString(16).padStart(2, '0');
        }).join('');
    }

    function clearRenderedDraft() {
        Array.prototype.forEach.call(
            document.querySelectorAll('.draft-item, .enhancement-history-item'),
            function (item) {
                item.remove();
            }
        );
    }

    function announceClear() {
        if (channel) {
            channel.postMessage({ type: 'draft-cleared', tabId: tabId });
        }
    }

    function requestClearAndReload(showError) {
        var url = new URL(window.location.href);
        url.searchParams.set('guest_draft_clear', '1');
        if (showError) {
            url.searchParams.set('guest_lifecycle_error', '1');
        }
        document.documentElement.classList.add('guest-lifecycle-pending');
        window.location.replace(url.href);
    }

    function claimTab() {
        var params = new URLSearchParams();
        params.set('csrf_token', config.csrfToken);
        params.set('tab_id', tabId);

        return window.fetch(config.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body: params.toString()
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Guest draft status could not be confirmed.');
            }
            return response.json();
        });
    }

    function finishSetup() {
        document.documentElement.classList.remove('guest-lifecycle-pending');
        var url = new URL(window.location.href);
        var lifecycleError = url.searchParams.has('guest_lifecycle_error');
        if (url.searchParams.has('guest_draft_clear')) {
            url.searchParams.delete('guest_draft_clear');
        }
        url.searchParams.delete('guest_lifecycle_error');
        window.history.replaceState(null, '', url.pathname + url.search + url.hash);

        Array.prototype.forEach.call(
            document.querySelectorAll('a[href*="signup.php"]'),
            function (link) {
                var signupUrl = new URL(link.href, window.location.href);
                signupUrl.searchParams.set('guest_tab_id', tabId);
                link.href = signupUrl.href;
            }
        );

        Array.prototype.forEach.call(
            document.querySelectorAll('form.generation-form, form.enhancement-form'),
            function (form) {
                var field = form.querySelector('input[name="guest_tab_id"]');
                if (field) {
                    field.value = tabId;
                }
            }
        );

        if (lifecycleError) {
            showLifecycleError('Guest draft status could not be confirmed. The temporary draft was cleared; please try again.');
        }
    }

    function initialize() {
        try {
            tabId = window.sessionStorage.getItem(storageKey);
            if (!/^[a-f0-9]{64}$/.test(tabId || '')) {
                tabId = createId();
                window.sessionStorage.setItem(storageKey, tabId);
            }
        } catch (error) {
            if (new URL(window.location.href).searchParams.has('guest_draft_clear')) {
                showLifecycleError('This browser could not create temporary guest storage. The temporary draft was cleared.');
            } else {
                requestClearAndReload(true);
            }
            return;
        }

        if (new URL(window.location.href).searchParams.has('guest_draft_clear')) {
            finishSetup();
            return;
        }

        if (channel) {
            channel.onmessage = function (event) {
                var data = event.data || {};
                if (data.type === 'tab-probe' && data.tabId === tabId && data.instanceId !== instanceId) {
                    channel.postMessage({
                        type: 'tab-collision',
                        tabId: tabId,
                        instanceId: instanceId,
                        targetInstanceId: data.instanceId
                    });
                } else if (data.type === 'tab-collision' && data.targetInstanceId === instanceId) {
                    tabId = createId();
                    window.sessionStorage.setItem(storageKey, tabId);
                } else if (data.type === 'draft-cleared' && data.tabId !== tabId) {
                    requestClearAndReload();
                }
            };
            channel.postMessage({ type: 'tab-probe', tabId: tabId, instanceId: instanceId });
        }

        window.setTimeout(function () {
            claimTab().then(function (result) {
                if (result.draft_cleared) {
                    announceClear();
                    requestClearAndReload();
                    return;
                }

                finishSetup();
            }).catch(function (error) {
                if (new URL(window.location.href).searchParams.has('guest_draft_clear')) {
                    showLifecycleError(error.message || 'Guest draft status could not be confirmed.');
                } else {
                    requestClearAndReload(true);
                }
            });
        }, channel ? 100 : 0);
    }

    function showLifecycleError(message) {
        clearRenderedDraft();
        document.documentElement.classList.remove('guest-lifecycle-pending');
        var notice = document.createElement('p');
        notice.className = 'notice error';
        notice.setAttribute('role', 'alert');
        notice.textContent = message;
        var main = document.querySelector('.dashboard-main') || document.querySelector('main');
        if (main) {
            main.insertBefore(notice, main.firstChild);
        }
    }

    document.documentElement.classList.add('guest-lifecycle-pending');
    if (window.guestDraftReloadRedirecting) {
        return;
    }

    initialize();
}());
