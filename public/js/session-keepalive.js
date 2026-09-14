(function () {
    const keepAliveUrl = document.querySelector('meta[name="keep-alive-url"]')?.getAttribute('content');
    if (!keepAliveUrl) {
        return;
    }

    let keepAliveInFlight = false;
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
            return Promise.resolve(null);
        }

        keepAliveInFlight = true;

        return nativeFetch(keepAliveUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
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
            keepAliveInFlight = false;
        });
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
        headers.set('X-CSRF-TOKEN', token);
        next.headers = headers;

        if (next.body instanceof FormData) {
            next.body.set('_token', token);
        }

        return next;
    }

    window.fetch = function (input, init) {
        const firstInit = withCsrfHeaders(init);

        return nativeFetch(input, firstInit).then(function (res) {
            if (res.status !== 419) {
                return res;
            }

            return keepAlive().then(function (token) {
                if (!token) {
                    return res;
                }
                return nativeFetch(input, retryInit(init, token));
            });
        });
    };

    if (window.jQuery) {
        window.jQuery.ajaxPrefilter(function (options) {
            if (options._mbsRetried) {
                return;
            }

            const originalError = options.error;
            options.error = function (xhr, status, error) {
                if (xhr && xhr.status === 419) {
                    keepAlive().then(function (token) {
                        if (!token) {
                            if (typeof originalError === 'function') {
                                originalError.apply(this, [xhr, status, error]);
                            }
                            return;
                        }
                        const retryOptions = window.jQuery.extend(true, {}, options);
                        retryOptions._mbsRetried = true;
                        retryOptions.error = originalError;
                        window.jQuery.ajax(retryOptions);
                    });
                    return;
                }

                if (typeof originalError === 'function') {
                    originalError.apply(this, arguments);
                }
            };
        });
    }

    setInterval(keepAlive, 4 * 60 * 1000);
})();
