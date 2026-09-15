(function () {
    const keepAliveUrl = document.querySelector('meta[name="keep-alive-url"]')?.getAttribute('content');
    if (!keepAliveUrl) {
        return;
    }

    let keepAliveInFlight = null;
    const nativeFetch = window.fetch.bind(window);

    function currentToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function applyToken(token) {
        if (!token) {
            return;
        }

        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) {
            meta.setAttribute('content', token);
        }

        document.querySelectorAll('input[name="_token"]').forEach(function (input) {
            input.value = token;
        });

        if (window.jQuery) {
            window.jQuery.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': token
                }
            });
        }
    }

    function keepAlive() {
        if (keepAliveInFlight) {
            return keepAliveInFlight;
        }

        const url = keepAliveUrl + (keepAliveUrl.indexOf('?') === -1 ? '?' : '&') + '_ts=' + Date.now();

        keepAliveInFlight = nativeFetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Cache-Control': 'no-cache'
            }
        }).then(function (res) {
            if (!res.ok) {
                return null;
            }
            return res.json();
        }).then(function (data) {
            if (data && data.token) {
                applyToken(data.token);
                return data.token;
            }
            return null;
        }).catch(function () {
            return null;
        }).finally(function () {
            keepAliveInFlight = null;
        });

        return keepAliveInFlight;
    }

    function withCsrfHeaders(init) {
        const next = Object.assign({}, init || {});
        const headers = new Headers(next.headers || {});
        const token = currentToken();
        if (token && !headers.has('X-CSRF-TOKEN') && !headers.has('X-XSRF-TOKEN')) {
            headers.set('X-CSRF-TOKEN', token);
        }
        next.headers = headers;
        return next;
    }

    function retryInit(init, token) {
        const next = withCsrfHeaders(init);
        const headers = new Headers(next.headers || {});
        if (token) {
            headers.set('X-CSRF-TOKEN', token);
        }
        next.headers = headers;

        if (next.body instanceof FormData && token) {
            next.body.set('_token', token);
        }

        return next;
    }

    function shouldRetryStatus(status) {
        return status === 419 || status === 401;
    }

    window.fetch = function (input, init) {
        const firstInit = withCsrfHeaders(init);

        return nativeFetch(input, firstInit).then(function (res) {
            if (!shouldRetryStatus(res.status)) {
                return res;
            }

            return keepAlive().then(function (token) {
                if (!token && res.status === 401) {
                    return res;
                }
                return nativeFetch(input, retryInit(init, token || currentToken()));
            });
        });
    };

    function patchJQueryAjax($) {
        if (!$ || $.ajax.__mbsPatched) {
            return;
        }

        const originalAjax = $.ajax.bind($);

        $.ajax = function (url, settings) {
            if (settings === undefined && typeof url === 'object') {
                settings = url;
            } else {
                settings = settings || {};
                if (url !== undefined) {
                    settings.url = url;
                }
            }

            settings = $.extend(true, {}, settings);

            if (settings._mbsRetried) {
                return originalAjax(settings);
            }

            const dfd = $.Deferred();
            const first = originalAjax(settings);

            first.done(function () {
                dfd.resolveWith(this, arguments);
            }).fail(function (jqXHR, textStatus, errorThrown) {
                if (!jqXHR || !shouldRetryStatus(jqXHR.status)) {
                    dfd.rejectWith(this, arguments);
                    return;
                }

                keepAlive().then(function (token) {
                    if (!token && jqXHR.status === 401) {
                        dfd.rejectWith(this, [jqXHR, textStatus, errorThrown]);
                        return;
                    }

                    const retrySettings = $.extend(true, {}, settings);
                    retrySettings._mbsRetried = true;
                    retrySettings.headers = $.extend({}, retrySettings.headers, {
                        'X-CSRF-TOKEN': token || currentToken()
                    });

                    originalAjax(retrySettings).done(function () {
                        dfd.resolveWith(this, arguments);
                    }).fail(function () {
                        dfd.rejectWith(this, arguments);
                    });
                }.bind(this));
            });

            return dfd.promise(first);
        };

        $.ajax.__mbsPatched = true;
    }

    if (window.jQuery) {
        patchJQueryAjax(window.jQuery);
    } else {
        document.addEventListener('DOMContentLoaded', function () {
            if (window.jQuery) {
                patchJQueryAjax(window.jQuery);
            }
        });
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            keepAlive();
        }
    });

    window.addEventListener('focus', function () {
        keepAlive();
    });

    setInterval(keepAlive, 2 * 60 * 1000);
    keepAlive();
})();
