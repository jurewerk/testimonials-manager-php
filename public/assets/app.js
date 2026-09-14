/**
 * Testimonials Manager — plain JavaScript, no framework.
 *
 * The whole admin is one page: a tiny history-API router swaps between the
 * login screen, the product library and a product workspace. All rendering goes
 * through escaped template strings, so testimonial text is never interpreted
 * as markup.
 */
(function () {
    'use strict';

    var BASE = window.APP.basePath || '';
    var CSRF = window.APP.csrfToken;
    var root = document.getElementById('app');

    /* ------------------------------------------------------------------ *
     * Utilities
     * ------------------------------------------------------------------ */

    function esc(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function url(path) { return BASE + path; }

    function qs(sel, scope) { return (scope || document).querySelector(sel); }
    function qsa(sel, scope) { return Array.prototype.slice.call((scope || document).querySelectorAll(sel)); }

    function on(scope, selector, event, handler) {
        qsa(selector, scope).forEach(function (el) { el.addEventListener(event, handler); });
    }

    var toastStack = document.createElement('div');
    toastStack.className = 'toast-stack';
    document.body.appendChild(toastStack);

    function toast(message, isError) {
        var el = document.createElement('div');
        el.className = 'toast' + (isError ? ' error' : '');
        el.setAttribute('role', 'status');
        el.textContent = message;
        toastStack.appendChild(el);
        setTimeout(function () { el.remove(); }, 4200);
    }

    /* ------------------------------------------------------------------ *
     * API client
     * ------------------------------------------------------------------ */

    function ApiError(message, status, errors) {
        this.message = message; this.status = status; this.errors = errors || {};
    }
    ApiError.prototype = Object.create(Error.prototype);

    function api(path, options) {
        options = options || {};
        var headers = { 'Accept': 'application/json', 'X-CSRF-Token': CSRF };
        var body = options.body;

        if (body && !(body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(body);
        }

        return fetch(url('/api' + path), {
            method: options.method || 'GET',
            headers: headers,
            body: body,
            credentials: 'same-origin'
        }).then(function (response) {
            if (response.status === 204) return null;

            return response.text().then(function (text) {
                var payload = null;
                try { payload = text ? JSON.parse(text) : null; } catch (e) { payload = null; }

                if (!response.ok) {
                    if (response.status === 401 && state.user) {
                        state.user = null;
                        navigate('/login');
                    }
                    throw new ApiError(
                        (payload && payload.message) || 'The server could not complete that request.',
                        response.status,
                        payload && payload.errors
                    );
                }
                return payload;
            });
        });
    }

    /* ------------------------------------------------------------------ *
     * State & router
     * ------------------------------------------------------------------ */

    var state = { user: null, route: null, providers: [] };

    // The provider list is needed by the name-generation and translation UI, so
    // it is loaded on boot and again after a fresh sign-in.
    function loadProviders() {
        return api('/ai-providers')
            .then(function (response) { state.providers = response.data; })
            .catch(function () { state.providers = []; });
    }

    function currentPath() {
        var path = window.location.pathname;
        if (BASE && path.indexOf(BASE) === 0) path = path.slice(BASE.length);
        return path || '/';
    }

    function navigate(path, replace) {
        var full = url(path);
        if (replace) window.history.replaceState({}, '', full);
        else window.history.pushState({}, '', full);
        render();
    }

    window.addEventListener('popstate', render);

    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[data-nav]');
        if (!link) return;
        event.preventDefault();
        navigate(link.getAttribute('href').slice(BASE.length) || '/');
    });

    function render() {
        var path = currentPath();

        if (!state.user) { return renderLogin(); }
        if (path === '/' || path === '/products') { return renderProducts(); }

        var match = path.match(/^\/products\/(\d+)$/);
        if (match) { return renderProduct(parseInt(match[1], 10)); }

        navigate('/products', true);
    }

    /* ------------------------------------------------------------------ *
     * Shared chrome
     * ------------------------------------------------------------------ */

    function layout(content) {
        return '' +
            '<header class="topbar"><div class="wrap">' +
                '<a class="brand" href="' + url('/products') + '" data-nav>' +
                    '<span class="mark">&ldquo;</span><span>testimonials</span><span class="tag">manager</span>' +
                '</a>' +
                '<nav>' +
                    '<a href="' + url('/products') + '" data-nav>Product library</a>' +
                    '<span class="who">' + esc(state.user ? state.user.name : '') + '</span>' +
                    '<button class="btn btn-sm" id="logout">Sign out</button>' +
                '</nav>' +
            '</div></header>' +
            '<main><div class="wrap">' + content + '</div></main>';
    }

    function bindChrome() {
        var logout = qs('#logout');
        if (logout) {
            logout.addEventListener('click', function () {
                api('/logout', { method: 'POST' }).then(function () {
                    state.user = null;
                    navigate('/login');
                });
            });
        }
    }

    function stars(testimonial) {
        if (testimonial.rating_mode === 'random') {
            return '<span class="stars" title="Resolved to 4 or 5 when displayed">&#9733;&#9733;&#9733;&#9733;&#9734;</span> <span class="muted small">4&ndash;5 random</span>';
        }
        var full = testimonial.rating || 0;
        return '<span class="stars">' + '&#9733;'.repeat(full) + '&#9734;'.repeat(5 - full) + '</span>';
    }

    function initials(name) {
        return String(name || '?').trim().split(/\s+/).slice(0, 2)
            .map(function (part) { return part.charAt(0).toUpperCase(); }).join('');
    }

    /* ------------------------------------------------------------------ *
     * Login
     * ------------------------------------------------------------------ */

    function renderLogin() {
        root.className = '';
        root.innerHTML = '' +
            '<div class="login">' +
                '<div class="pitch">' +
                    '<p class="eyebrow" style="color:#9db8a5">A home for customer voices</p>' +
                    '<h1>Real stories.<br>Every product.<br>Every country.</h1>' +
                    '<p>Bring your customer testimonials together and make every landing page feel a little more human.</p>' +
                '</div>' +
                '<div class="form-side"><form id="login-form">' +
                    '<p class="eyebrow">Welcome back</p>' +
                    '<h1 style="font-size:26px">Sign in</h1>' +
                    '<p class="lead small" style="margin-bottom:22px">Manage the stories behind your products.</p>' +
                    '<div id="login-error"></div>' +
                    '<label class="field">Email address' +
                        '<input type="email" name="email" autocomplete="username" required>' +
                    '</label>' +
                    '<label class="field">Password' +
                        '<input type="password" name="password" autocomplete="current-password" required>' +
                    '</label>' +
                    '<button class="btn btn-primary" style="width:100%" type="submit">Sign in</button>' +
                '</form></div>' +
            '</div>';

        qs('#login-form').addEventListener('submit', function (event) {
            event.preventDefault();
            var form = event.target;
            var button = qs('button', form);
            button.disabled = true;

            api('/login', { method: 'POST', body: { email: form.email.value, password: form.password.value } })
                .then(function (response) {
                    state.user = response.data;
                    return loadProviders().then(function () { navigate('/products'); });
                })
                .catch(function (error) {
                    qs('#login-error').innerHTML = '<div class="alert alert-error">' + esc(error.message) + '</div>';
                })
                .finally(function () { button.disabled = false; });
        });
    }

    /* ------------------------------------------------------------------ *
     * Product library
     * ------------------------------------------------------------------ */

    function readQuery() {
        var params = new URLSearchParams(window.location.search);
        return {
            search: params.get('search') || '',
            sort: params.get('sort') || 'parent_sku',
            direction: params.get('direction') || 'asc',
            per_page: params.get('per_page') || '25',
            page: params.get('page') || '1'
        };
    }

    function writeQuery(query) {
        var params = new URLSearchParams();
        Object.keys(query).forEach(function (key) {
            if (query[key] !== '' && query[key] !== null) params.set(key, query[key]);
        });
        window.history.replaceState({}, '', url('/products') + '?' + params.toString());
    }

    function renderProducts() {
        var query = readQuery();
        root.className = '';
        root.innerHTML = layout(
            '<h1>Product library</h1>' +
            '<p class="lead">Search parent SKUs, then pick a country to manage its testimonials.</p>' +
            '<div class="toolbar" style="margin-top:22px">' +
                '<label class="field grow">Search products' +
                    '<input type="search" id="search" placeholder="SKU, title or description" value="' + esc(query.search) + '">' +
                '</label>' +
                '<button class="btn" id="do-search">Search</button>' +
                '<label class="field">Per page' +
                    '<select id="per-page">' +
                        [10, 25, 50, 100].map(function (n) {
                            return '<option value="' + n + '"' + (String(n) === query.per_page ? ' selected' : '') + '>' + n + '</option>';
                        }).join('') +
                    '</select>' +
                '</label>' +
                '<button class="btn btn-primary" id="sync">Sync landing pages</button>' +
            '</div>' +
            '<div id="sync-status"></div>' +
            '<div class="card"><div id="product-table"><div class="empty">Loading…</div></div></div>'
        );

        bindChrome();

        function load() {
            var params = new URLSearchParams(query).toString();
            api('/products?' + params).then(function (result) {
                drawTable(result);
            }).catch(function (error) {
                qs('#product-table').innerHTML = '<div class="empty">' + esc(error.message) + '</div>';
            });
        }

        function drawTable(result) {
            if (result.data.length === 0) {
                qs('#product-table').innerHTML = '<div class="empty">No products match that search.' +
                    (query.search ? '' : ' Use <strong>Sync landing pages</strong> to import them.') + '</div>';
                return;
            }

            function header(key, label) {
                var arrow = query.sort === key ? (query.direction === 'asc' ? ' ▲' : ' ▼') : '';
                return '<th><button data-sort="' + key + '">' + label + arrow + '</button></th>';
            }

            qs('#product-table').innerHTML = '' +
                '<div class="table-wrap"><table><thead><tr>' +
                    '<th style="width:60px"></th>' +
                    header('parent_sku', 'Parent SKU') +
                    header('title', 'Product') +
                    header('landings_count', 'Countries') +
                    header('testimonials_count', 'Testimonials') +
                    '<th></th>' +
                '</tr></thead><tbody>' +
                result.data.map(function (product) {
                    return '<tr>' +
                        '<td>' + (product.product_image_url
                            ? '<img class="thumb-sm" src="' + esc(product.product_image_url) + '" alt="" loading="lazy">'
                            : '<div class="thumb-sm"></div>') + '</td>' +
                        '<td class="sku">' + esc(product.parent_sku) + '</td>' +
                        '<td>' + esc(product.title) + '</td>' +
                        '<td><span class="pill">' + product.localized_landings_count + '</span></td>' +
                        '<td><span class="pill">' + product.testimonials_count + '</span></td>' +
                        '<td><a class="btn btn-sm" href="' + url('/products/' + product.id) + '" data-nav>Open</a></td>' +
                    '</tr>';
                }).join('') +
                '</tbody></table></div>' +
                '<div class="pagination">' +
                    '<span>' + (result.meta.from || 0) + '–' + (result.meta.to || 0) + ' of ' + result.meta.total + '</span>' +
                    '<button class="btn btn-sm" id="prev"' + (result.meta.current_page <= 1 ? ' disabled' : '') + '>← Previous</button>' +
                    '<span>' + result.meta.current_page + ' / ' + result.meta.last_page + '</span>' +
                    '<button class="btn btn-sm" id="next"' + (result.meta.current_page >= result.meta.last_page ? ' disabled' : '') + '>Next →</button>' +
                '</div>';

            on(document, '[data-sort]', 'click', function (event) {
                var key = event.currentTarget.getAttribute('data-sort');
                if (query.sort === key) query.direction = query.direction === 'asc' ? 'desc' : 'asc';
                else { query.sort = key; query.direction = 'asc'; }
                query.page = '1';
                writeQuery(query); load();
            });

            var prev = qs('#prev'), next = qs('#next');
            if (prev) prev.addEventListener('click', function () { query.page = String(result.meta.current_page - 1); writeQuery(query); load(); });
            if (next) next.addEventListener('click', function () { query.page = String(result.meta.current_page + 1); writeQuery(query); load(); });
        }

        function applySearch() {
            query.search = qs('#search').value.trim();
            query.page = '1';
            writeQuery(query); load();
        }

        qs('#do-search').addEventListener('click', applySearch);
        qs('#search').addEventListener('keydown', function (e) { if (e.key === 'Enter') applySearch(); });
        qs('#per-page').addEventListener('change', function (e) {
            query.per_page = e.target.value; query.page = '1'; writeQuery(query); load();
        });

        qs('#sync').addEventListener('click', function (event) {
            var button = event.currentTarget;
            button.disabled = true;
            button.textContent = 'Syncing…';
            qs('#sync-status').innerHTML = '';

            api('/landings/sync', { method: 'POST', body: {} })
                .then(function (response) {
                    var s = response.data;
                    qs('#sync-status').innerHTML = '<p class="alert alert-ok" role="status">' +
                        s.received + ' received · ' + s.created + ' created · ' + s.updated + ' updated · ' +
                        s.unchanged + ' unchanged · ' + s.failed + ' failed</p>';
                    load();
                })
                .catch(function (error) {
                    qs('#sync-status').innerHTML = '<p class="alert alert-error" role="status">' + esc(error.message) + '</p>';
                })
                .finally(function () { button.disabled = false; button.textContent = 'Sync landing pages'; });
        });

        load();
    }

    /* ------------------------------------------------------------------ *
     * Product workspace: countries + testimonials
     * ------------------------------------------------------------------ */

    function renderProduct(productId) {
        root.className = '';
        root.innerHTML = layout('<div id="product-shell"><div class="empty">Loading…</div></div>');
        bindChrome();

        var ctx = {
            productId: productId,
            product: null,
            landings: [],
            landing: null,
            page: 1,
            data: null,
            selected: {},
            provider: 'openai'
        };

        var params = new URLSearchParams(window.location.search);
        var wantedCountry = params.get('country');
        ctx.page = parseInt(params.get('page') || '1', 10);

        function syncUrl() {
            var p = new URLSearchParams();
            if (ctx.landing) p.set('country', ctx.landing.country_code);
            if (ctx.page > 1) p.set('page', String(ctx.page));
            var query = p.toString();
            window.history.replaceState({}, '', url('/products/' + productId) + (query ? '?' + query : ''));
        }

        Promise.all([api('/products/' + productId), api('/products/' + productId + '/landings')])
            .then(function (responses) {
                ctx.product = responses[0].data;
                ctx.landings = responses[1].data;

                var preferred = ctx.landings.filter(function (l) { return l.country_code === wantedCountry; })[0];
                ctx.landing = preferred || ctx.landings.filter(function (l) { return l.is_master; })[0] || ctx.landings[0];

                drawShell();
                loadTestimonials();
            })
            .catch(function (error) {
                qs('#product-shell').innerHTML = '<div class="empty">' + esc(error.message) + '</div>';
            });

        function drawShell() {
            var product = ctx.product;

            qs('#product-shell').innerHTML = '' +
                '<p><a href="' + url('/products') + '" data-nav>← Back to product library</a></p>' +
                '<p class="eyebrow" style="margin-top:18px">' + esc(product.parent_sku) + '</p>' +
                '<h1>' + esc(product.title) + '</h1>' +
                '<p class="lead">' + esc(product.description || '') + '</p>' +
                '<h3 style="margin-top:28px">Country landings <span class="muted">' + ctx.landings.length + '</span></h3>' +
                '<div class="countries" id="countries"></div>' +
                '<div id="testimonials"></div>';

            drawCountries();
        }

        function drawCountries() {
            qs('#countries').innerHTML = ctx.landings.map(function (landing) {
                var badge = landing.is_master
                    ? '<span class="badge badge-active">English master</span>'
                    : (landing.inherits
                        ? '<span class="badge badge-inherit">English fallback</span>'
                        : '<span class="badge ' + (landing.own_active_count > 0 ? 'badge-active' : 'badge-off') + '">' +
                          (landing.own_active_count > 0 ? 'Active' : 'No active') + '</span>');

                return '<button class="country' + (ctx.landing && landing.id === ctx.landing.id ? ' active' : '') + '" data-landing="' + landing.id + '">' +
                    '<div class="row"><span class="code">' + esc(landing.country_code) + '</span>' +
                    '<span class="kind">' + (landing.is_master ? 'Master' : 'Localized') + '</span></div>' +
                    '<div class="title">' + esc(landing.title) + '</div>' +
                    badge +
                    '<div class="counts">' + landing.own_count + ' local · ' + landing.effective_count + ' effective</div>' +
                '</button>';
            }).join('');

            on(qs('#countries'), '[data-landing]', 'click', function (event) {
                var id = parseInt(event.currentTarget.getAttribute('data-landing'), 10);
                ctx.landing = ctx.landings.filter(function (l) { return l.id === id; })[0];
                ctx.page = 1;
                ctx.selected = {};
                syncUrl();
                drawCountries();
                loadTestimonials();
            });
        }

        function refreshCounts() {
            return api('/products/' + productId + '/landings').then(function (response) {
                ctx.landings = response.data;
                var current = ctx.landing;
                ctx.landing = ctx.landings.filter(function (l) { return l.id === current.id; })[0] || current;
                drawCountries();
            });
        }

        function loadTestimonials() {
            qs('#testimonials').innerHTML = '<div class="empty">Loading testimonials…</div>';

            return api('/landings/' + ctx.landing.id + '/testimonials?page=' + ctx.page)
                .then(function (result) {
                    ctx.data = result;
                    drawTestimonials();
                })
                .catch(function (error) {
                    qs('#testimonials').innerHTML = '<div class="empty">' + esc(error.message) + '</div>';
                });
        }

        function drawTestimonials() {
            var inheritance = ctx.data.inheritance;
            var rows = ctx.data.data;
            var readonly = inheritance.inherited;
            var selectedIds = Object.keys(ctx.selected).filter(function (k) { return ctx.selected[k]; });

            var head = '' +
                '<div class="list-head">' +
                    '<div>' +
                        '<h2>' + esc(ctx.landing.country_code) + ' testimonials ' +
                            '<a class="small" href="' + esc(ctx.landing.landing_url) + '" target="_blank" rel="noopener noreferrer">Open landing ↗</a>' +
                        '</h2>' +
                        '<span class="small muted">' + inheritance.local_testimonial_count + ' local · ' +
                            inheritance.effective_testimonial_count + ' effective</span>' +
                    '</div>' +
                    '<div class="actions">' +
                        '<button class="btn btn-sm" id="copy-from">Copy from country</button>' +
                        (ctx.landing.is_master ? '' : '<button class="btn btn-sm" id="translate">✧ Translate from English</button>') +
                        '<button class="btn btn-sm btn-primary" id="new-testimonial">+ New testimonial</button>' +
                    '</div>' +
                '</div>';

            if (readonly) {
                head += '<div class="alert alert-ok">Inherited from the English master (' +
                    esc(inheritance.source_country) + '). These are read-only here. ' +
                    '<button class="btn btn-sm" id="copy-locally" style="margin-left:8px">Copy locally to edit →</button></div>';
            }

            var bulk = (!readonly && rows.length > 0) ? '' +
                '<div class="list-head">' +
                    '<label class="checkbox"><input type="checkbox" id="select-page">Select this page</label>' +
                    (selectedIds.length > 0 ? '<span class="small muted">' + selectedIds.length + ' selected</span>' +
                        '<button class="btn btn-sm" data-bulk="activate">Activate</button>' +
                        '<button class="btn btn-sm" data-bulk="deactivate">Deactivate</button>' +
                        '<button class="btn btn-sm btn-danger" data-bulk="delete">Delete</button>' : '') +
                    '<div class="actions">' +
                        '<label class="field" style="margin:0"><span class="small">Name generation provider</span>' +
                        '<select id="provider">' + state.providers.map(function (p) {
                            return '<option value="' + esc(p.key) + '"' + (p.key === ctx.provider ? ' selected' : '') + '>' + esc(p.label) + '</option>';
                        }).join('') + '</select></label>' +
                    '</div>' +
                '</div>' : '';

            var list = rows.length === 0
                ? '<div class="card empty">No testimonials yet for this country.</div>'
                : rows.map(function (t) { return card(t, readonly); }).join('');

            var pager = '' +
                '<div class="pagination">' +
                    '<span>' + (ctx.data.meta.from || 0) + '–' + (ctx.data.meta.to || 0) + ' of ' + ctx.data.meta.total + '</span>' +
                    '<button class="btn btn-sm" id="t-prev"' + (ctx.data.meta.current_page <= 1 ? ' disabled' : '') + '>← Previous</button>' +
                    '<span>' + ctx.data.meta.current_page + ' / ' + ctx.data.meta.last_page + '</span>' +
                    '<button class="btn btn-sm" id="t-next"' + (ctx.data.meta.current_page >= ctx.data.meta.last_page ? ' disabled' : '') + '>Next →</button>' +
                '</div>';

            qs('#testimonials').innerHTML = head + bulk + '<div id="t-list">' + list + '</div>' + pager;
            bindTestimonials(readonly);
        }

        function card(t, readonly) {
            var images = (t.images || []).map(function (image, index) {
                return '<figure>' +
                    '<img src="' + esc(image.thumbnail_url) + '" alt="' + esc(image.original_filename) + '" loading="lazy">' +
                    (readonly ? '' : '<div class="controls">' +
                        '<button class="btn-link" data-img-move="' + image.id + '" data-dir="-1" title="Move image left"' + (index === 0 ? ' disabled' : '') + '>←</button>' +
                        '<button class="btn-link" data-img-delete="' + image.id + '">Remove</button>' +
                        '<button class="btn-link" data-img-move="' + image.id + '" data-dir="1" title="Move image right"' + (index === t.images.length - 1 ? ' disabled' : '') + '>→</button>' +
                    '</div>') +
                '</figure>';
            }).join('');

            return '' +
            '<article class="card t-card' + (t.is_active ? '' : ' inactive') + '" data-id="' + t.id + '"' + (readonly ? '' : ' draggable="true"') + '>' +
                '<div class="rail">' +
                    (readonly ? '' :
                        '<input type="checkbox" data-select="' + t.id + '"' + (ctx.selected[t.id] ? ' checked' : '') + ' aria-label="Select testimonial">' +
                        '<span class="handle" title="Drag to reorder">⠿</span>' +
                        '<button class="btn-link" data-move="' + t.id + '" data-dir="-1" title="Move testimonial up">↑</button>' +
                        '<button class="btn-link" data-move="' + t.id + '" data-dir="1" title="Move testimonial down">↓</button>') +
                '</div>' +
                '<div class="body">' +
                    '<div class="t-head">' +
                        '<div class="avatar">' + esc(initials(t.author_name)) + '</div>' +
                        '<div>' +
                            '<strong>' + esc(t.author_name) + '</strong>' +
                            '<div class="t-meta">' + esc(t.gender.charAt(0).toUpperCase() + t.gender.slice(1)) +
                                ' · ' + (readonly ? 'Inherited' : 'Local') + ' · #' + (t.sort_order + 1) + '</div>' +
                        '</div>' +
                        '<div class="t-right">' + stars(t) +
                            '<span class="badge ' + (t.is_active ? 'badge-active' : 'badge-off') + '">' + (t.is_active ? 'Active' : 'Inactive') + '</span>' +
                        '</div>' +
                    '</div>' +
                    '<p class="t-comment">' + esc(t.comment) + '</p>' +
                    (t.link ? '<a class="t-link" href="' + esc(t.link) + '" target="_blank" rel="noopener noreferrer">' + esc(t.link) + '</a>' : '') +
                    (images ? '<div class="gallery">' + images + '</div>' : '') +
                    (readonly ? '' :
                    '<div class="t-actions">' +
                        '<button class="btn-link" data-edit="' + t.id + '">Edit testimonial</button>' +
                        '<button class="btn-link" data-toggle="' + t.id + '">' + (t.is_active ? 'Deactivate' : 'Activate') + '</button>' +
                        '<label class="btn-link" style="cursor:pointer">Add images' +
                            '<input type="file" data-upload="' + t.id + '" accept="image/jpeg,image/png,image/webp" multiple hidden>' +
                        '</label>' +
                        '<button class="btn-link" data-generate="' + t.id + '">Generate name</button>' +
                        '<button class="btn-link" data-history="' + t.id + '">History</button>' +
                        '<button class="btn-link btn-danger spacer" data-delete="' + t.id + '">Delete</button>' +
                    '</div>') +
                '</div>' +
            '</article>';
        }

        /* ---- event wiring for the list ---- */
        function bindTestimonials(readonly) {
            var prev = qs('#t-prev'), next = qs('#t-next');
            if (prev) prev.addEventListener('click', function () { ctx.page--; syncUrl(); loadTestimonials(); });
            if (next) next.addEventListener('click', function () { ctx.page++; syncUrl(); loadTestimonials(); });

            var copyFrom = qs('#copy-from');
            if (copyFrom) copyFrom.addEventListener('click', function () { openCopyModal(false); });

            var translate = qs('#translate');
            if (translate) translate.addEventListener('click', function () { openTranslateModal(); });

            var copyLocally = qs('#copy-locally');
            if (copyLocally) copyLocally.addEventListener('click', function () { openCopyModal(true); });

            var create = qs('#new-testimonial');
            if (create) create.addEventListener('click', function () { openTestimonialModal(null); });

            var provider = qs('#provider');
            if (provider) provider.addEventListener('change', function (e) { ctx.provider = e.target.value; });

            if (readonly) return;

            on(document, '[data-select]', 'change', function (event) {
                ctx.selected[event.target.getAttribute('data-select')] = event.target.checked;
                drawTestimonials();
            });

            var selectPage = qs('#select-page');
            if (selectPage) selectPage.addEventListener('change', function (event) {
                ctx.data.data.forEach(function (t) { ctx.selected[t.id] = event.target.checked; });
                drawTestimonials();
            });

            on(document, '[data-bulk]', 'click', function (event) {
                var action = event.currentTarget.getAttribute('data-bulk');
                var ids = Object.keys(ctx.selected).filter(function (k) { return ctx.selected[k]; }).map(Number);
                if (ids.length === 0) return;

                var run = function () {
                    api('/landings/' + ctx.landing.id + '/testimonials/bulk-action', {
                        method: 'POST',
                        body: { ids: ids, action: action, version: ctx.landing.version }
                    }).then(function () {
                        ctx.selected = {};
                        toast(ids.length + ' testimonial(s) updated.');
                        return refreshCounts().then(loadTestimonials);
                    }).catch(function (error) { toast(error.message, true); });
                };

                if (action === 'delete') {
                    confirmModal('Delete ' + ids.length + ' testimonial(s)?', 'This also removes their images and cannot be undone.', run);
                } else { run(); }
            });

            on(document, '[data-edit]', 'click', function (event) {
                var id = parseInt(event.currentTarget.getAttribute('data-edit'), 10);
                openTestimonialModal(ctx.data.data.filter(function (t) { return t.id === id; })[0]);
            });

            on(document, '[data-toggle]', 'click', function (event) {
                var id = parseInt(event.currentTarget.getAttribute('data-toggle'), 10);
                var t = ctx.data.data.filter(function (x) { return x.id === id; })[0];

                // Send the complete editable form; the toggle must not drop fields.
                api('/testimonials/' + id, {
                    method: 'PATCH',
                    body: {
                        author_name: t.author_name, comment: t.comment, link: t.link,
                        rating_mode: t.rating_mode, rating: t.rating, gender: t.gender,
                        is_active: !t.is_active, version: t.version
                    }
                }).then(function () { return refreshCounts().then(loadTestimonials); })
                  .catch(function (error) { toast(error.message, true); });
            });

            on(document, '[data-delete]', 'click', function (event) {
                var id = parseInt(event.currentTarget.getAttribute('data-delete'), 10);
                confirmModal('Delete this testimonial?', 'Its images are removed too. This cannot be undone.', function () {
                    api('/testimonials/' + id, { method: 'DELETE' })
                        .then(function () { toast('Testimonial deleted.'); return refreshCounts().then(loadTestimonials); })
                        .catch(function (error) { toast(error.message, true); });
                });
            });

            on(document, '[data-history]', 'click', function (event) {
                openHistory(parseInt(event.currentTarget.getAttribute('data-history'), 10));
            });

            on(document, '[data-generate]', 'click', function (event) {
                var id = parseInt(event.currentTarget.getAttribute('data-generate'), 10);
                var t = ctx.data.data.filter(function (x) { return x.id === id; })[0];

                api('/testimonials/' + id + '/generate-author-name', {
                    method: 'POST', body: { provider: ctx.provider, version: t.version }
                }).then(function () { toast('Name generated.'); return loadTestimonials(); })
                  .catch(function (error) { toast(error.message, true); });
            });

            on(document, '[data-upload]', 'change', function (event) {
                var id = event.target.getAttribute('data-upload');
                if (!event.target.files.length) return;

                var form = new FormData();
                Array.prototype.forEach.call(event.target.files, function (file) { form.append('images[]', file); });

                api('/testimonials/' + id + '/images', { method: 'POST', body: form })
                    .then(function () { toast('Images uploaded.'); return loadTestimonials(); })
                    .catch(function (error) { toast(error.message, true); });
            });

            on(document, '[data-img-delete]', 'click', function (event) {
                var id = event.currentTarget.getAttribute('data-img-delete');
                api('/testimonial-images/' + id, { method: 'DELETE' })
                    .then(loadTestimonials).catch(function (error) { toast(error.message, true); });
            });

            on(document, '[data-img-move]', 'click', function (event) {
                var imageId = parseInt(event.currentTarget.getAttribute('data-img-move'), 10);
                var direction = parseInt(event.currentTarget.getAttribute('data-dir'), 10);
                var testimonial = ctx.data.data.filter(function (t) {
                    return t.images.some(function (i) { return i.id === imageId; });
                })[0];

                var ids = testimonial.images.map(function (i) { return i.id; });
                var from = ids.indexOf(imageId);
                var to = from + direction;
                if (to < 0 || to >= ids.length) return;
                ids.splice(to, 0, ids.splice(from, 1)[0]);

                api('/testimonials/' + testimonial.id + '/images/reorder', {
                    method: 'POST', body: { ids: ids, version: testimonial.version }
                }).then(loadTestimonials).catch(function (error) { toast(error.message, true); });
            });

            on(document, '[data-move]', 'click', function (event) {
                var id = parseInt(event.currentTarget.getAttribute('data-move'), 10);
                var direction = parseInt(event.currentTarget.getAttribute('data-dir'), 10);
                moveTestimonial(id, direction);
            });

            enableDragAndDrop();
        }

        /* ---- ordering ---- */

        /**
         * Reordering always sends the landing's complete id list, even when the
         * visible page is only part of it.
         */
        function fullOrder() {
            return api('/landings/' + ctx.landing.id + '/testimonials?page=1&per_page=100')
                .then(function (first) {
                    var ids = first.data.map(function (t) { return t.id; });
                    var pages = [];
                    for (var p = 2; p <= first.meta.last_page; p++) pages.push(p);

                    return pages.reduce(function (chain, page) {
                        return chain.then(function (acc) {
                            return api('/landings/' + ctx.landing.id + '/testimonials?page=' + page + '&per_page=100')
                                .then(function (r) { return acc.concat(r.data.map(function (t) { return t.id; })); });
                        });
                    }, Promise.resolve(ids));
                });
        }

        function persistOrder(ids) {
            return api('/landings/' + ctx.landing.id + '/testimonials/reorder', {
                method: 'POST', body: { ids: ids, version: ctx.landing.version }
            }).then(function (response) {
                ctx.landing.version = response.data.version;
                return loadTestimonials();
            }).catch(function (error) {
                toast(error.message, true);
                return loadTestimonials();
            });
        }

        function moveTestimonial(id, direction) {
            fullOrder().then(function (ids) {
                var from = ids.indexOf(id);
                var to = from + direction;
                if (to < 0 || to >= ids.length) return;
                ids.splice(to, 0, ids.splice(from, 1)[0]);
                return persistOrder(ids);
            });
        }

        function enableDragAndDrop() {
            var dragged = null;

            on(document, '.t-card[draggable]', 'dragstart', function (event) {
                dragged = event.currentTarget;
                dragged.classList.add('dragging');
                event.dataTransfer.effectAllowed = 'move';
            });

            on(document, '.t-card[draggable]', 'dragend', function (event) {
                event.currentTarget.classList.remove('dragging');
                qsa('.drop-target').forEach(function (el) { el.classList.remove('drop-target'); });
            });

            on(document, '.t-card[draggable]', 'dragover', function (event) {
                event.preventDefault();
                if (event.currentTarget !== dragged) event.currentTarget.classList.add('drop-target');
            });

            on(document, '.t-card[draggable]', 'dragleave', function (event) {
                event.currentTarget.classList.remove('drop-target');
            });

            on(document, '.t-card[draggable]', 'drop', function (event) {
                event.preventDefault();
                event.currentTarget.classList.remove('drop-target');
                if (!dragged || event.currentTarget === dragged) return;

                var movedId = parseInt(dragged.getAttribute('data-id'), 10);
                var targetId = parseInt(event.currentTarget.getAttribute('data-id'), 10);

                fullOrder().then(function (ids) {
                    var from = ids.indexOf(movedId);
                    ids.splice(from, 1);
                    ids.splice(ids.indexOf(targetId), 0, movedId);
                    return persistOrder(ids);
                });
            });
        }

        /* ---- modals ---- */

        function openTestimonialModal(testimonial) {
            var isNew = !testimonial;
            var form = testimonial || { author_name: '', comment: '', link: '', rating_mode: 'fixed', rating: 5, gender: 'unisex', is_active: true };

            var body = '' +
                '<div id="form-error"></div>' +
                '<label class="field">Author name' +
                    '<input type="text" name="author_name" maxlength="120" required value="' + esc(form.author_name) + '">' +
                    '<span class="field-error" data-for="author_name"></span>' +
                '</label>' +
                '<label class="field">Testimonial <span class="counter" id="counter"></span>' +
                    '<textarea name="comment" maxlength="2000" required>' + esc(form.comment) + '</textarea>' +
                    '<span class="field-error" data-for="comment"></span>' +
                '</label>' +
                '<label class="field">Link <span class="muted">(optional)</span>' +
                    '<input type="url" name="link" maxlength="2048" placeholder="https://example.com/product" value="' + esc(form.link || '') + '">' +
                    '<span class="field-error" data-for="link"></span>' +
                '</label>' +
                '<div style="display:flex;gap:14px;flex-wrap:wrap">' +
                    '<label class="field" style="flex:1 1 180px">Rating mode' +
                        '<select name="rating_mode">' +
                            '<option value="fixed"' + (form.rating_mode === 'fixed' ? ' selected' : '') + '>Fixed rating</option>' +
                            '<option value="random"' + (form.rating_mode === 'random' ? ' selected' : '') + '>Random (4–5 at display)</option>' +
                        '</select>' +
                    '</label>' +
                    '<label class="field" style="flex:1 1 180px" id="stars-field">Stars' +
                        '<select name="rating">' +
                            [1, 2, 3, 4, 5].map(function (n) {
                                return '<option value="' + n + '"' + (Number(form.rating) === n ? ' selected' : '') + '>' + n + ' star' + (n > 1 ? 's' : '') + '</option>';
                            }).join('') +
                        '</select>' +
                        '<span class="field-error" data-for="rating"></span>' +
                    '</label>' +
                '</div>' +
                '<label class="field">Gender' +
                    '<select name="gender">' +
                        ['male', 'female', 'unisex'].map(function (g) {
                            return '<option value="' + g + '"' + (form.gender === g ? ' selected' : '') + '>' + g.charAt(0).toUpperCase() + g.slice(1) + '</option>';
                        }).join('') +
                    '</select>' +
                '</label>' +
                '<label class="checkbox"><input type="checkbox" name="is_active"' + (form.is_active ? ' checked' : '') + '>Active on the landing page</label>';

            var modal = openModal(isNew ? 'New testimonial' : 'Edit testimonial', '<form id="t-form">' + body + '</form>',
                '<span class="save-state" id="save-state"></span>' +
                '<span class="spacer"></span>' +
                '<button class="btn" data-close>Cancel</button>' +
                '<button class="btn btn-primary" id="save">Save testimonial</button>');

            var formEl = qs('#t-form', modal);

            function updateCounter() {
                qs('#counter', modal).textContent = formEl.comment.value.length + ' / 2,000';
            }

            function updateStarsVisibility() {
                qs('#stars-field', modal).style.display = formEl.rating_mode.value === 'random' ? 'none' : '';
            }

            formEl.comment.addEventListener('input', function () { updateCounter(); markDirty(); });
            formEl.rating_mode.addEventListener('change', function () { updateStarsVisibility(); markDirty(); });
            qsa('input, select, textarea', formEl).forEach(function (el) { el.addEventListener('input', markDirty); });
            updateCounter();
            updateStarsVisibility();

            function markDirty() {
                var el = qs('#save-state', modal);
                el.className = 'save-state dirty';
                el.textContent = 'Unsaved changes';
            }

            qs('#save', modal).addEventListener('click', function () {
                qsa('.field-error', modal).forEach(function (el) { el.textContent = ''; });
                qs('#form-error', modal).innerHTML = '';

                var payload = {
                    author_name: formEl.author_name.value,
                    comment: formEl.comment.value,
                    link: formEl.link.value,
                    rating_mode: formEl.rating_mode.value,
                    rating: formEl.rating_mode.value === 'random' ? null : Number(formEl.rating.value),
                    gender: formEl.gender.value,
                    is_active: formEl.is_active.checked
                };

                var state = qs('#save-state', modal);
                state.className = 'save-state';
                state.textContent = 'Saving…';

                var request = isNew
                    ? api('/landings/' + ctx.landing.id + '/testimonials', { method: 'POST', body: payload })
                    : api('/testimonials/' + testimonial.id, { method: 'PATCH', body: Object.assign({ version: testimonial.version }, payload) });

                request.then(function () {
                    closeModal(modal);
                    toast(isNew ? 'Testimonial created.' : 'Testimonial saved.');
                    return refreshCounts().then(loadTestimonials);
                }).catch(function (error) {
                    state.className = 'save-state error';
                    state.textContent = 'Not saved';

                    // A failed save must stay visible, never be swallowed.
                    qs('#form-error', modal).innerHTML = '<div class="alert alert-error">' + esc(error.message) + '</div>';

                    Object.keys(error.errors || {}).forEach(function (field) {
                        var target = qs('.field-error[data-for="' + field + '"]', modal);
                        if (target) target.textContent = error.errors[field][0];
                    });
                });
            });
        }

        function openCopyModal(defaultFromMaster) {
            var others = ctx.landings.filter(function (l) { return l.id !== ctx.landing.id; });

            if (others.length === 0) {
                toast('This product has no other country to copy from.', true);
                return;
            }

            var master = others.filter(function (l) { return l.is_master; })[0];
            var preselected = defaultFromMaster && master ? master.id : others[0].id;

            var modal = openModal('Copy testimonials', '' +
                '<div id="copy-error"></div>' +
                '<label class="field">Copy from' +
                    '<select id="source">' + others.map(function (l) {
                        return '<option value="' + l.id + '"' + (l.id === preselected ? ' selected' : '') + '>' +
                            esc(l.country_code) + (l.is_master ? ' (English master)' : '') + ' — ' + l.own_count + ' stored' +
                        '</option>';
                    }).join('') + '</select>' +
                '</label>' +
                '<label class="field">What happens to the testimonials already here' +
                    '<select id="strategy">' +
                        '<option value="append">Append — keep what is here and add the copies</option>' +
                        '<option value="replace">Replace — delete what is here first</option>' +
                        '<option value="empty">Only when empty — do nothing if something exists</option>' +
                        '<option value="skip">Skip duplicates — ignore identical author and text</option>' +
                    '</select>' +
                '</label>' +
                '<p class="small muted">Copies are independent records with their own image files. Both active and inactive source rows are copied.</p>',
                '<span class="spacer"></span><button class="btn" data-close>Cancel</button>' +
                '<button class="btn btn-primary" id="do-copy">Copy testimonials</button>');

            qs('#do-copy', modal).addEventListener('click', function (event) {
                event.currentTarget.disabled = true;

                api('/landings/' + ctx.landing.id + '/copy-testimonials', {
                    method: 'POST',
                    body: {
                        source_landing_id: Number(qs('#source', modal).value),
                        strategy: qs('#strategy', modal).value,
                        version: ctx.landing.version
                    }
                }).then(function (response) {
                    closeModal(modal);
                    toast(response.data.copied + ' copied, ' + response.data.skipped + ' skipped.');
                    return refreshCounts().then(loadTestimonials);
                }).catch(function (error) {
                    event.currentTarget.disabled = false;
                    qs('#copy-error', modal).innerHTML = '<div class="alert alert-error">' + esc(error.message) + '</div>';
                });
            });
        }

        function openTranslateModal() {
            var modal = openModal('Translate from English', '' +
                '<div id="tr-error"></div>' +
                '<div style="display:flex;gap:14px;flex-wrap:wrap">' +
                    '<label class="field" style="flex:1 1 200px">Provider' +
                        '<select id="tr-provider">' + state.providers.map(function (p) {
                            return '<option value="' + esc(p.key) + '">' + esc(p.label) + '</option>';
                        }).join('') + '</select>' +
                    '</label>' +
                    '<label class="field" style="flex:1 1 200px">Destination strategy' +
                        '<select id="tr-strategy">' +
                            '<option value="append">Append</option>' +
                            '<option value="replace">Replace</option>' +
                            '<option value="empty">Only when empty</option>' +
                            '<option value="skip">Skip duplicates</option>' +
                        '</select>' +
                    '</label>' +
                '</div>' +
                '<label class="checkbox"><input type="checkbox" id="tr-names">Also generate a country-plausible author name</label>' +
                '<p class="small muted">No AI provider is contacted and nothing is charged. The mock adapters prefix the text with the country code.</p>' +
                '<div id="tr-preview"></div>',
                '<span class="spacer"></span><button class="btn" data-close>Cancel</button>' +
                '<button class="btn" id="tr-do-preview">Preview translation</button>' +
                '<button class="btn btn-primary" id="tr-save" disabled>Save translations</button>', true);

            function options(preview) {
                return {
                    provider: qs('#tr-provider', modal).value,
                    strategy: qs('#tr-strategy', modal).value,
                    generate_names: qs('#tr-names', modal).checked,
                    preview: preview,
                    version: ctx.landing.version
                };
            }

            qs('#tr-do-preview', modal).addEventListener('click', function () {
                qs('#tr-error', modal).innerHTML = '';

                api('/landings/' + ctx.landing.id + '/translate-testimonials', { method: 'POST', body: options(true) })
                    .then(function (response) {
                        var rows = response.data.preview;

                        if (rows.length === 0) {
                            qs('#tr-preview', modal).innerHTML = '<p class="muted small">The English master has no testimonials to translate.</p>';
                            return;
                        }

                        qs('#tr-preview', modal).innerHTML = '<h3 style="margin-top:18px">Preview</h3>' + rows.map(function (row) {
                            return '<div class="preview-row">' +
                                '<div class="before">' + esc(row.author_name) + ' — ' + esc(row.comment) + '</div>' +
                                '<div><strong>' + esc(row.translated_author_name) + '</strong> — ' + esc(row.translated_comment) + '</div>' +
                            '</div>';
                        }).join('');

                        qs('#tr-save', modal).disabled = false;
                    })
                    .catch(function (error) {
                        qs('#tr-error', modal).innerHTML = '<div class="alert alert-error">' + esc(error.message) + '</div>';
                    });
            });

            qs('#tr-save', modal).addEventListener('click', function (event) {
                event.currentTarget.disabled = true;

                api('/landings/' + ctx.landing.id + '/translate-testimonials', { method: 'POST', body: options(false) })
                    .then(function (response) {
                        closeModal(modal);
                        toast(response.data.copied + ' translated testimonial(s) saved.');
                        return refreshCounts().then(loadTestimonials);
                    })
                    .catch(function (error) {
                        event.currentTarget.disabled = false;
                        qs('#tr-error', modal).innerHTML = '<div class="alert alert-error">' + esc(error.message) + '</div>';
                    });
            });
        }

        function openHistory(id) {
            var modal = openModal('History', '<div id="history-body"><p class="muted">Loading…</p></div>',
                '<span class="spacer"></span><button class="btn" data-close>Close</button>', true);

            api('/testimonials/' + id + '/activity').then(function (response) {
                if (response.data.length === 0) {
                    qs('#history-body', modal).innerHTML = '<p class="muted">No history recorded yet.</p>';
                    return;
                }

                qs('#history-body', modal).innerHTML = response.data.map(function (entry) {
                    function values(label, data) {
                        if (!data) return '';
                        return '<div class="small"><span class="muted">' + label + ':</span> ' +
                            Object.keys(data).map(function (key) {
                                return '<code>' + esc(key) + '</code> ' + esc(String(data[key]));
                            }).join(' · ') + '</div>';
                    }

                    return '<div class="history-entry">' +
                        '<strong>' + esc(entry.action) + '</strong> · ' +
                        '<span class="muted small">' + esc(entry.actor || 'system') + ' · ' + esc(entry.created_at) + '</span>' +
                        values('before', entry.old_values) + values('after', entry.new_values) +
                    '</div>';
                }).join('');
            });
        }
    }

    /* ------------------------------------------------------------------ *
     * Generic modal helpers
     * ------------------------------------------------------------------ */

    function openModal(title, body, footer, wide) {
        var overlay = document.createElement('div');
        overlay.className = 'overlay';
        overlay.innerHTML = '<div class="modal' + (wide ? ' wide' : '') + '" role="dialog" aria-modal="true">' +
            '<header><h2>' + esc(title) + '</h2><button class="close" data-close aria-label="Close">×</button></header>' +
            '<div class="content">' + body + '</div>' +
            (footer ? '<footer>' + footer + '</footer>' : '') +
        '</div>';

        document.body.appendChild(overlay);

        on(overlay, '[data-close]', 'click', function () { closeModal(overlay); });
        overlay.addEventListener('click', function (event) { if (event.target === overlay) closeModal(overlay); });
        document.addEventListener('keydown', function escape(event) {
            if (event.key === 'Escape') { closeModal(overlay); document.removeEventListener('keydown', escape); }
        });

        var firstInput = qs('input, textarea, select', overlay);
        if (firstInput) firstInput.focus();

        return overlay;
    }

    function closeModal(overlay) { if (overlay && overlay.parentNode) overlay.remove(); }

    function confirmModal(title, message, onConfirm) {
        var modal = openModal(title, '<p>' + esc(message) + '</p>',
            '<span class="spacer"></span><button class="btn" data-close>Cancel</button>' +
            '<button class="btn btn-primary" id="confirm">Yes, continue</button>');

        qs('#confirm', modal).addEventListener('click', function () { closeModal(modal); onConfirm(); });
    }

    /* ------------------------------------------------------------------ *
     * Boot
     * ------------------------------------------------------------------ */

    api('/user')
        .then(function (response) {
            state.user = response.data;
            return loadProviders();
        })
        .catch(function () { state.user = null; })
        .finally(function () {
            if (state.user && currentPath() === '/login') navigate('/products', true);
            else render();
        });
})();
