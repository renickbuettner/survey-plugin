/*
 * Loads and drives the survey block of Renick.Content. The block is a static shell; the form
 * (CSRF token, voted state) is fetched per visitor. Idempotent: a second include or a second
 * init() on the same element does nothing.
 */
(function () {
    'use strict';

    if (window.RenickSurveyEmbed) {
        window.RenickSurveyEmbed.init();
        return;
    }

    var STYLE_ID = 'rc-survey-style';

    function loadStyle(href) {
        if (!href || document.getElementById(STYLE_ID)) {
            return;
        }
        var link = document.createElement('link');
        link.id = STYLE_ID;
        link.rel = 'stylesheet';
        link.href = href;
        document.head.appendChild(link);
    }

    function focusNotice(body) {
        var target = body.querySelector('[data-rc-survey-focus]');
        if (target) {
            target.focus();
        }
    }

    function setBody(root, html, focus) {
        var body = root.querySelector('.rc-survey__body');
        body.innerHTML = html;
        root.removeAttribute('aria-busy');
        bindForm(root, body);
        if (focus) {
            focusNotice(body);
        }
    }

    function bindForm(root, body) {
        var form = body.querySelector('[data-rc-survey-form]');
        if (!form) {
            return;
        }

        var submit = form.querySelector('.rc-survey__submit');
        var error = form.querySelector('.rc-survey__error');
        var privacy = form.querySelector('input[name="user_privacy"]');

        function showError(message) {
            error.textContent = message;
            error.hidden = false;
            error.scrollIntoView({block: 'nearest'});
        }

        function ready() {
            var chosen = form.querySelector('input[name="choices[]"]:checked') !== null;
            return chosen && (!privacy || privacy.checked);
        }

        function refresh() {
            submit.disabled = !ready();
        }

        form.addEventListener('change', refresh);
        refresh();

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (!ready()) {
                return;
            }
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            error.hidden = true;
            submit.disabled = true;
            root.setAttribute('aria-busy', 'true');

            fetch(form.getAttribute('action'), {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
            }).then(function (response) {
                return response.json().catch(function () {
                    return {ok: false};
                }).then(function (json) {
                    return {status: response.status, json: json};
                });
            }).then(function (result) {
                root.removeAttribute('aria-busy');
                if (result.json.ok) {
                    setBody(root, result.json.html, true);
                } else if (result.json.html) {
                    setBody(root, result.json.html, false);
                    var again = root.querySelector('.rc-survey__error');
                    if (again) {
                        again.textContent = result.json.message || '';
                        again.hidden = false;
                    }
                } else {
                    showError(result.json.message || form.getAttribute('data-error') || 'Error');
                    refresh();
                }
            }).catch(function () {
                root.removeAttribute('aria-busy');
                showError(error.getAttribute('data-fallback') || 'Error');
                refresh();
            });
        });
    }

    function load(root) {
        var url = root.getAttribute('data-endpoint') + '?' + root.getAttribute('data-query');
        loadStyle(root.getAttribute('data-style'));

        fetch(url, {credentials: 'same-origin', headers: {'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest'}})
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('survey');
                }
                return response.text();
            })
            .then(function (html) {
                setBody(root, html, false);
            })
            .catch(function () {
                root.removeAttribute('aria-busy');
                var loading = root.querySelector('.rc-survey__loading');
                if (loading) {
                    loading.hidden = true;
                }
            });
    }

    function init() {
        var roots = document.querySelectorAll('[data-rc-survey]:not([data-rc-survey-init])');
        Array.prototype.forEach.call(roots, function (root) {
            // The editor preview renders a passive card without an endpoint query
            if (!root.getAttribute('data-query')) {
                return;
            }
            root.setAttribute('data-rc-survey-init', '1');
            load(root);
        });
    }

    window.RenickSurveyEmbed = {init: init};

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
