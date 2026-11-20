// ERP core script. Plain JavaScript: no jQuery, no Bootstrap JS, no plugins.
// Source file. public/js/app.js is generated from it by: php tools/build-assets.php
// Write comments on their own lines, starting with two slashes.
(function () {
    'use strict';

    var root = document.documentElement;
    var body = document.body;
    var ERP = (window.ERP = {});
    var SPRITE = root.getAttribute('data-icons') || '/icons.svg';
    var T = window.ERP_TEXT || {};
    // what a page needs done once it is on the screen; run again for every page that is put in place without a full load
    var pageInits = [];

    var $ = function (sel, scope) { return (scope || document).querySelector(sel); };
    var $$ = function (sel, scope) { return Array.prototype.slice.call((scope || document).querySelectorAll(sel)); };
    var mq = function (q) { return window.matchMedia(q).matches; };
    var appMode = function () { return mq('(max-width: 991.98px)'); };
    var phone = function () { return mq('(max-width: 767.98px)'); };

    ERP.escape = function (s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };
    ERP.icon = function (id, cls) {
        return '<i class="fi ' + (cls || '') + '"><svg aria-hidden="true"><use href="' + SPRITE + '#' + id + '"/></svg></i>';
    };
    function setIcon(el, id) {
        var use = el && $('use', el);
        if (use) use.setAttribute('href', SPRITE + '#' + id);
    }

    // ---------------------------------------------------------------- settings
    var settings = {};
    try { settings = JSON.parse(localStorage.getItem('erp.ui') || '{}'); } catch (e) { settings = {}; }
    function save() {
        try { localStorage.setItem('erp.ui', JSON.stringify(settings)); } catch (e) { return; }
    }
    function theme() { return root.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light'; }
    function syncTheme() {
        $$('[data-theme-toggle]').forEach(function (b) { setIcon(b, theme() === 'dark' ? 's-sun' : 's-moon'); });
    }
    document.addEventListener('click', function (e) {
        if (!e.target.closest('[data-theme-toggle]')) return;
        settings.theme = theme() === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-bs-theme', settings.theme);
        save();
        syncTheme();
    });

    // ----------------------------------------------------------------- sidebar
    function toggleLayout() {
        settings.layout = root.getAttribute('data-layout') === 'collapsed' ? 'vertical' : 'collapsed';
        root.setAttribute('data-layout', settings.layout);
        save();
    }
    function closeDrawer() { body.classList.remove('sidebar-open'); }
    document.addEventListener('click', function (e) {
        var t = e.target;
        if (t.closest('#sidebarToggle')) {
            if (appMode()) body.classList.toggle('sidebar-open'); else toggleLayout();
        } else if (t.closest('#sidebarPin')) {
            toggleLayout();
        } else if (t.closest('#tabMenu')) {
            body.classList.add('sidebar-open');
        } else if (t.closest('#sidebarBackdrop')) {
            closeDrawer();
        } else if (t.closest('#appBack')) {
            var same = false;
            try { same = !!document.referrer && new URL(document.referrer).origin === location.origin; } catch (err) { same = false; }
            if (history.length > 1 && same) history.back(); else location.href = root.getAttribute('data-home') || '/';
        }
    });

    // ---------------------------------------------------------------- collapse
    function setCollapse(link, open) {
        var target = $(link.getAttribute('data-bs-target') || link.getAttribute('href'));
        if (!target) return;
        target.classList.toggle('show', open);
        link.setAttribute('aria-expanded', open ? 'true' : 'false');
        var item = link.closest('.menu-item');
        if (item) item.classList.toggle('open', open);
    }
    document.addEventListener('click', function (e) {
        var link = e.target.closest('[data-bs-toggle="collapse"]');
        if (!link) return;
        e.preventDefault();
        var open = link.getAttribute('aria-expanded') !== 'true';
        var menu = link.closest('#sidebarMenu');
        // the sidebar is an accordion: one open group at a time
        if (menu && open) {
            $$('[data-bs-toggle="collapse"][aria-expanded="true"]', menu).forEach(function (other) { setCollapse(other, false); });
        }
        setCollapse(link, open);
    });

    // --------------------------------------------------------------- dropdowns
    function closeDropdowns(except) {
        $$('.dropdown-menu.show').forEach(function (menu) {
            if (menu === except) return;
            menu.classList.remove('show');
            // a bottom sheet was moved to <body>: put it back next to its button
            if (menu._home) { menu._home.appendChild(menu); menu._home = null; }
            var toggle = $('[data-bs-toggle="dropdown"]', menu.parentNode);
            if (toggle) { toggle.classList.remove('show'); toggle.setAttribute('aria-expanded', 'false'); }
        });
        if (!except) $$('.sheet-backdrop').forEach(function (b) { b.remove(); });
    }
    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-bs-toggle="dropdown"]');
        if (toggle) {
            e.preventDefault();
            var menu = $('.dropdown-menu', toggle.parentNode);
            if (!menu) return;
            var open = !menu.classList.contains('show');
            closeDropdowns();
            if (open) {
                menu.setAttribute('data-bs-popper', 'static');
                menu.classList.add('show');
                toggle.classList.add('show');
                toggle.setAttribute('aria-expanded', 'true');
                // on phones and tablets the menu is a bottom sheet. It moves to <body>
                // because the blurred top bar would otherwise trap its fixed position.
                if (appMode()) {
                    var back = document.createElement('div');
                    back.className = 'sheet-backdrop';
                    body.appendChild(back);
                    menu._home = menu.parentNode;
                    body.appendChild(menu);
                }
            }
            return;
        }
        var inside = e.target.closest('.dropdown-menu.show');
        if (inside && inside.getAttribute('data-bs-auto-close') === 'outside' && !e.target.closest('a, button[type="submit"]')) return;
        closeDropdowns();
    });

    // ------------------------------------------------------------------ modals
    ERP.showModal = function (modal) {
        if (typeof modal === 'string') modal = $(modal);
        if (!modal) return;
        closeDropdowns();
        var back = document.createElement('div');
        back.className = 'modal-backdrop fade';
        body.appendChild(back);
        modal._back = back;
        modal.style.display = 'block';
        modal.removeAttribute('aria-hidden');
        body.classList.add('modal-open');
        // force a reflow so the fade transition runs
        void modal.offsetWidth;
        modal.classList.add('show');
        back.classList.add('show');
        var focus = $('[autofocus]', modal) || $('input:not([type="hidden"]), .btn-primary, .btn', modal);
        if (focus) focus.focus();
        modal.dispatchEvent(new CustomEvent('erp:shown'));
    };
    ERP.hideModal = function (modal) {
        if (!modal || !modal.classList.contains('show')) return;
        modal.classList.remove('show');
        var back = modal._back;
        modal._back = null;
        if (back) back.classList.remove('show');
        setTimeout(function () {
            if (back) back.remove();
            if (modal.classList.contains('show')) return;          // opened again meanwhile
            modal.style.display = '';
            modal.setAttribute('aria-hidden', 'true');
            if (!$('.modal.show')) body.classList.remove('modal-open');
            modal.dispatchEvent(new CustomEvent('erp:hidden'));
        }, 160);
    };
    document.addEventListener('click', function (e) {
        var open = e.target.closest('[data-bs-toggle="modal"]');
        if (open) { e.preventDefault(); closeDrawer(); ERP.showModal(open.getAttribute('data-bs-target')); return; }
        var dismiss = e.target.closest('[data-bs-dismiss="modal"]');
        if (dismiss) { ERP.hideModal(dismiss.closest('.modal')); return; }
        // a click beside a form never closes it: what was typed would be lost
        if (e.target.classList && e.target.classList.contains('modal') && !e.target.hasAttribute('data-static')) ERP.hideModal(e.target);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var modals = $$('.modal.show');
        if (modals.length) ERP.hideModal(modals[modals.length - 1]);
        else if ($('.dropdown-menu.show')) closeDropdowns();
        else closeDrawer();
    });

    // -------------------------------------------------------------------- tabs
    document.addEventListener('click', function (e) {
        var tab = e.target.closest('[data-bs-toggle="tab"]');
        if (!tab) return;
        e.preventDefault();
        var pane = $(tab.getAttribute('data-bs-target') || tab.getAttribute('href'));
        if (!pane) return;
        $$('[data-bs-toggle="tab"]', tab.closest('.nav')).forEach(function (t) { t.classList.toggle('active', t === tab); });
        $$('.tab-pane', pane.parentNode).forEach(function (p) {
            p.classList.toggle('active', p === pane);
            p.classList.toggle('show', p === pane);
        });
    });

    // ------------------------------------------------------------------ toasts
    ERP.toast = function (message, type) {
        var box = $('#toasts');
        if (!box) { box = document.createElement('div'); box.id = 'toasts'; box.className = 'erp-toasts'; body.appendChild(box); }
        type = type || 'success';
        var ico = { success: 's-circle-check', danger: 's-circle-xmark', warning: 's-triangle-exclamation', info: 's-circle-info' }[type] || 's-circle-info';
        var el = document.createElement('div');
        el.className = 'erp-toast is-' + type;
        el.setAttribute('role', 'status');
        el.innerHTML = ERP.icon(ico) + '<span>' + ERP.escape(message) + '</span>';
        box.appendChild(el);
        setTimeout(function () { el.classList.add('is-out'); setTimeout(function () { el.remove(); }, 250); }, type === 'danger' ? 6000 : 3200);
    };
    $$('[data-toast]').forEach(function (el) { ERP.toast(el.textContent.trim(), el.getAttribute('data-toast')); el.remove(); });

    // ----------------------------------------------------------------- confirm
    // <form data-confirm="Sign out?" data-confirm-text="..." data-confirm-ok="Sign out" data-confirm-style="primary">
    ERP.confirm = function (o, onOk) {
        var style = o.style || 'danger';
        var modal = document.createElement('div');
        modal.className = 'modal fade erp-confirm';
        modal.tabIndex = -1;
        modal.innerHTML = '<div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content"><div class="modal-body text-center">'
            + '<div class="erp-confirm-icon text-' + style + '">' + ERP.icon(style === 'danger' ? 's-triangle-exclamation' : 's-circle-question') + '</div>'
            + '<h2 class="h5 mb-1">' + ERP.escape(o.title || T.confirmTitle || 'Are you sure?') + '</h2>'
            + (o.text ? '<p class="text-muted mb-0">' + ERP.escape(o.text) + '</p>' : '')
            + '<div class="d-flex gap-2 mt-4"><button type="button" class="btn btn-light-secondary flex-fill" data-bs-dismiss="modal">' + ERP.escape(T.cancel || 'Cancel') + '</button>'
            + '<button type="button" class="btn btn-' + style + ' flex-fill" data-ok>' + ERP.escape(o.ok || T.confirmOk || 'Yes') + '</button></div>'
            + '</div></div></div>';
        body.appendChild(modal);
        modal.addEventListener('erp:hidden', function () { modal.remove(); });
        $('[data-ok]', modal).addEventListener('click', function () { ERP.hideModal(modal); onOk(); });
        ERP.showModal(modal);
        $('[data-bs-dismiss]', modal).focus();
    };
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form.hasAttribute('data-confirm') && !form._confirmed) {
            e.preventDefault();
            ERP.confirm({
                title: form.getAttribute('data-confirm'), text: form.getAttribute('data-confirm-text'),
                ok: form.getAttribute('data-confirm-ok'), style: form.getAttribute('data-confirm-style'),
            }, function () { form._confirmed = true; if (form.requestSubmit) form.requestSubmit(); else form.submit(); });
            return;
        }
        // a form can be sent only once: stops double posting on slow connections
        if (form._sent) { e.preventDefault(); return; }
        form._sent = true;
        $$('button[type="submit"], button:not([type])', form).forEach(function (b) { b.classList.add('is-busy'); b.setAttribute('aria-disabled', 'true'); });
    });
    // coming back with the browser back button re-enables the form
    window.addEventListener('pageshow', function () {
        $$('form').forEach(function (f) { f._sent = false; f._confirmed = false; });
        $$('.is-busy').forEach(function (b) { b.classList.remove('is-busy'); b.removeAttribute('aria-disabled'); });
    });

    // ------------------------------------------------------- password show/hide
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-toggle-password]');
        if (!btn) return;
        var input = $('input', btn.closest('.auth-field, .input-group, .input-icon'));
        if (!input) return;
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        setIcon(btn, show ? 'r-eye-slash' : 'r-eye');
    });

    // --------------------------------------------------------- rows that repeat (the representatives of a company)
    // a form keeps a hidden model row; "add" copies it with the next number, the cross takes a row away
    document.addEventListener('click', function (e) {
        var add = e.target.closest ? e.target.closest('[data-repeat-add]') : null;
        var remove = e.target.closest ? e.target.closest('[data-repeat-remove]') : null;
        if (!add && !remove) return;
        var box = (add || remove).closest('[data-repeat]');
        if (!box) return;
        var rows = box.querySelector('[data-repeat-rows]');
        if (add) {
            var next = parseInt(box.getAttribute('data-repeat-next') || '0', 10);
            var holder = document.createElement('div');
            holder.innerHTML = box.querySelector('[data-repeat-template]').innerHTML.replace(/__i__/g, String(next));
            box.setAttribute('data-repeat-next', String(next + 1));
            var row = holder.firstElementChild;
            rows.appendChild(row);
            var first = row.querySelector('input');
            if (first) first.focus();
        } else {
            var mine = remove.closest('[data-repeat-row]');
            // the last row stays, emptied
            if (rows.querySelectorAll('[data-repeat-row]').length > 1) mine.parentNode.removeChild(mine);
            else Array.prototype.forEach.call(mine.querySelectorAll('input'), function (input) { input.value = ''; });
        }
    });

    // --------------------------------------------------------- command search
    var results = $('#searchResults');
    var input = $('#searchInput');
    if (results && input) {
        var index = $$('#sidebarMenu a[href]').filter(function (a) { return a.getAttribute('href').charAt(0) !== '#'; }).map(function (a) {
            var group = a.closest('.has-sub');
            var text = $('.menu-text', a);
            var use = $('.menu-icon use', group || a);
            return {
                label: (text ? text.textContent : a.textContent).trim(),
                parent: group ? $('.menu-text', group).textContent.trim() : '',
                href: a.getAttribute('href'),
                icon: use ? use.getAttribute('href').split('#')[1] : 's-file',
            };
        });
        // and the pages in the menu of the profile
        $$('.user-menu a.dropdown-item[href]').forEach(function (a) {
            var use = $('use', a);
            index.push({ label: a.textContent.trim(), parent: '', href: a.getAttribute('href'), icon: use ? use.getAttribute('href').split('#')[1] : 's-file' });
        });
        var focus = 0;
        var render = function (q) {
            q = (q || '').trim().toLowerCase();
            var hits = index.filter(function (i) { return !q || (i.label + ' ' + i.parent).toLowerCase().indexOf(q) > -1; }).slice(0, 30);
            focus = 0;
            results.innerHTML = hits.length ? hits.map(function (h, n) {
                return '<a class="search-item ' + (n === 0 ? 'focus' : '') + '" href="' + ERP.escape(h.href) + '"><span class="si-icon">' + ERP.icon(h.icon) + '</span>'
                    + '<span><strong>' + ERP.escape(h.label) + '</strong><small>' + ERP.escape(h.parent) + '</small></span>' + ERP.icon('s-arrow-right', 'si-go') + '</a>';
            }).join('') : '<div class="search-empty">' + ERP.escape(T.noResults || 'No results') + '</div>';
        };
        $('#searchModal').addEventListener('erp:shown', function () { input.value = ''; input.focus(); render(''); });
        input.addEventListener('input', function () { render(input.value); });
        input.addEventListener('keydown', function (e) {
            var items = $$('.search-item', results);
            if (!items.length) return;
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                focus = (focus + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
                items.forEach(function (a, n) { a.classList.toggle('focus', n === focus); });
                items[focus].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter') {
                location.href = items[focus].getAttribute('href');
            }
        });
        document.addEventListener('keydown', function (e) {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); ERP.showModal('#searchModal'); }
        });
    }

    // ------------------------------------------------------- amount boxes
    // Every amount box (inputmode="decimal") accepts digits only and shows thousands
    // with commas while typing: 12500.5 becomes 12,500.5. The server removes the commas.
    ERP.formatNumber = function (value) {
        var text = String(value == null ? '' : value).replace(/[^0-9.]/g, '');
        var dot = text.indexOf('.');
        var whole = dot < 0 ? text : text.slice(0, dot);
        // only the first dot counts
        var fraction = dot < 0 ? null : text.slice(dot + 1).replace(/\./g, '');
        whole = whole.replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        if (dot === 0) whole = '0';
        return fraction === null ? whole : whole + '.' + fraction;
    };
    ERP.numberInput = function (input) {
        var before = input.value;
        var caret = input.selectionStart == null ? before.length : input.selectionStart;
        // how many digits and dots are left of the caret: the caret stays after the same one
        var keep = before.slice(0, caret).replace(/[^0-9.]/g, '').length;
        var after = ERP.formatNumber(before);
        if (after === before) return;
        input.value = after;
        var position = 0;
        for (var seen = 0; position < after.length && seen < keep; position++) {
            if (/[0-9.]/.test(after.charAt(position))) seen++;
        }
        try { input.setSelectionRange(position, position); } catch (err) { return; }
    };
    var AMOUNT = 'input[inputmode="decimal"]';
    // Digits typed with a Dari, Pashto or Arabic keyboard become 0 to 9 in every box that holds a
    // number: amounts, counts, phones, identity numbers, dates (anything written left to right).
    ERP.latinDigits = function (value) {
        return String(value).replace(/[\u06f0-\u06f9]/g, function (d) { return String(d.charCodeAt(0) - 0x06f0); })
            .replace(/[\u0660-\u0669]/g, function (d) { return String(d.charCodeAt(0) - 0x0660); })
            .replace(/\u066b/g, '.').replace(/\u066c/g, ',');
    };
    var DIGITS = 'input[inputmode="decimal"], input[inputmode="numeric"], input[type="tel"], input[dir="ltr"]:not([type="password"]):not([type="email"]):not([type="file"]), input[data-shamsi-date], input[data-digits]';
    var digitsInput = function (input) {
        var before = input.value;
        var after = ERP.latinDigits(before);
        if (after === before) return;
        var caret = input.selectionStart;
        input.value = after;
        // one sign for one sign: the caret stays where it was
        try { if (caret != null) input.setSelectionRange(caret, caret); } catch (err) { return; }
    };
    document.addEventListener('input', function (e) {
        if (!e.target.matches) return;
        if (e.target.matches(DIGITS)) digitsInput(e.target);
        if (e.target.matches(AMOUNT)) ERP.numberInput(e.target);
    }, true);

    // ------------------------------------------------ select with search box
    // <select data-search> becomes a box you can type in. No library needed.
    ERP.searchSelect = function (select) {
        if (select._ss || select.disabled) return;
        select._ss = true;
        var wrap = document.createElement('div');
        wrap.className = 'ss' + (select.classList.contains('form-select-sm') ? ' ss-sm' : '');
        var box = document.createElement('input');
        box.type = 'text';
        box.className = 'form-control ' + (select.classList.contains('is-invalid') ? 'is-invalid' : '');
        box.autocomplete = 'off';
        box.placeholder = select.options.length && !select.options[0].value ? select.options[0].text : '';
        if (select.required) { box.required = true; select.required = false; }
        if (select.id) { box.id = select.id; select.removeAttribute('id'); }
        var list = document.createElement('div');
        list.className = 'ss-list';
        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(box);
        // a small cross empties the choice, when the list has an empty first line
        var clear = null;
        if (select.options.length && !select.options[0].value) {
            clear = document.createElement('button');
            clear.type = 'button';
            clear.className = 'ss-clear';
            clear.tabIndex = -1;
            clear.setAttribute('aria-label', T.cancel || 'Clear');
            clear.innerHTML = '&times;';
            wrap.appendChild(clear);
        }
        wrap.appendChild(list);
        wrap.appendChild(select);
        select.hidden = true;
        var focus = 0;
        var current = function () { var o = select.options[select.selectedIndex]; return o && o.value ? o.text : ''; };
        var close = function () { wrap.classList.remove('open', 'up'); list.removeAttribute('style'); };
        // in a modal the list floats above it, so the edge of the modal does not cut it off
        var place = function () {
            var holder = wrap.closest('.modal-body');
            if (!holder) return false;
            if (!wrap._scrollWatch) {
                wrap._scrollWatch = true;
                holder.addEventListener('scroll', function () { if (wrap.classList.contains('open')) close(); }, { passive: true });
                window.addEventListener('resize', function () { if (wrap.classList.contains('open')) close(); });
            }
            var r = box.getBoundingClientRect(), below = window.innerHeight - r.bottom, above = r.top;
            var up = below < 240 && above > below;
            var rtl = getComputedStyle(wrap).direction === 'rtl';
            list.style.position = 'fixed';
            list.style.minWidth = r.width + 'px';
            list.style.maxWidth = Math.max(r.width, Math.min(420, window.innerWidth - 16)) + 'px';
            list.style.left = rtl ? 'auto' : Math.max(8, r.left) + 'px';
            list.style.right = rtl ? Math.max(8, window.innerWidth - r.right) + 'px' : 'auto';
            list.style.top = up ? 'auto' : (r.bottom + 6) + 'px';
            list.style.bottom = up ? (window.innerHeight - r.top + 6) + 'px' : 'auto';
            list.style.maxHeight = Math.max(150, Math.min(320, (up ? above : below) - 18)) + 'px';
            return true;
        };
        var mark = function () { wrap.classList.toggle('has-value', !!select.value); };
        var show = function (all) {
            var q = all ? '' : box.value.trim().toLowerCase();
            var html = '';
            var n = 0;
            for (var i = 0; i < select.options.length && n < 60; i++) {
                var o = select.options[i];
                if (!o.value || o.disabled || o.hidden || (q && o.text.toLowerCase().indexOf(q) < 0)) continue;
                html += '<div class="ss-item' + (n === 0 ? ' focus' : '') + (o.value === select.value ? ' selected' : '') + '" data-value="' + ERP.escape(o.value) + '">' + ERP.escape(o.text) + '</div>';
                n++;
            }
            list.innerHTML = html || '<div class="ss-empty">' + ERP.escape(T.noResults || 'No results') + '</div>';
            focus = 0;
            wrap.classList.add('open');
            // open upwards when there is no room under the box
            if (!place()) {
                var rect = wrap.getBoundingClientRect();
                wrap.classList.toggle('up', window.innerHeight - rect.bottom < 280 && rect.top > 280);
            }
            var chosen = $('.ss-item.selected', list);
            if (chosen && all) chosen.scrollIntoView({ block: 'nearest' });
        };
        var pick = function (value) {
            select.value = value;
            box.value = current();
            mark();
            close();
            select.dispatchEvent(new Event('change', { bubbles: true }));
        };
        box.value = current();
        mark();
        if (clear) clear.addEventListener('mousedown', function (e) { e.preventDefault(); pick(''); box.focus(); });
        box.addEventListener('focus', function () { box.select(); show(true); });
        // clicking a box that already has the cursor opens the list again, ready to type over
        box.addEventListener('click', function () { if (!wrap.classList.contains('open')) { box.select(); show(true); } });
        box.addEventListener('input', function () { show(false); });
        box.addEventListener('keydown', function (e) {
            var items = $$('.ss-item', list);
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (!items.length) return;
                focus = (focus + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
                items.forEach(function (it, n) { it.classList.toggle('focus', n === focus); });
                items[focus].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter' && wrap.classList.contains('open')) {
                e.preventDefault();
                if (items[focus]) pick(items[focus].getAttribute('data-value'));
            } else if (e.key === 'Escape') {
                close();
            }
        });
        // mousedown comes before blur, so the choice is not lost
        list.addEventListener('mousedown', function (e) {
            var item = e.target.closest('.ss-item');
            if (item) { e.preventDefault(); pick(item.getAttribute('data-value')); }
        });
        // the options or the value were changed from outside: show the chosen text again
        select._ssSync = function () { box.value = current(); mark(); };
        // a value set by another script (with a change event) shows in the box too
        select.addEventListener('change', function () { if (document.activeElement !== box) select._ssSync(); });
        box.addEventListener('blur', function () {
            if (box.value.trim() === '' && select.value !== '') pick('');
            else box.value = current();
            close();
        });
    };
    // Lists marked data-search, and every list long enough to need it (8 choices or more), become searchable.
    var enhance = function (scope) {
        $$('select', scope).forEach(function (select) {
            if (select.multiple || select.hasAttribute('data-plain') || select.closest('.input-group')) return;
            if (select.hasAttribute('data-search') || select.options.length >= 9) ERP.searchSelect(select);
        });
    };

    // ------------------------------------------------------- Excel and PDF
    // Every page that shows a table (a list or a report, not a form) gets two buttons in its title bar:
    // Excel downloads the table with the filters that are on the screen, PDF opens the page as a paper
    // with the print window, where "Save as PDF" makes the file.
    pageInits.push(function () {
        var main = $('.app-content');
        var header = main ? $('.page-header', main) : null;
        if (!header || body.hasAttribute('data-no-export')) return;
        var tables = $$('table.table', main).filter(function (table) {
            return !table.hasAttribute('data-rows') && !table.classList.contains('dash-table') && !table.closest('form[method="POST"], form[method="post"]') && $('tbody tr td', table);
        });
        if (!tables.length) return;
        var actions = $('.page-header-actions', header);
        if (!actions) {
            actions = document.createElement('div');
            actions.className = 'page-header-actions';
            header.appendChild(actions);
        }
        var link = function (params, icon, text, blank) {
            var url = new URL(window.location.href);
            Object.keys(params).forEach(function (key) { url.searchParams.set(key, params[key]); });
            var a = document.createElement('a');
            a.className = 'btn btn-outline-soft';
            a.href = url.toString();
            if (blank) { a.target = '_blank'; a.rel = 'noopener'; }
            a.innerHTML = ERP.icon(icon, 'me-1') + ' ' + ERP.escape(text);
            return a;
        };
        var group = document.createElement('span');
        group.className = 'export-buttons';
        group.appendChild(link({ export: 'xlsx' }, 's-file-excel', 'Excel', false));
        group.appendChild(link({ print: '1', pdf: '1' }, 's-file-pdf', 'PDF', true));
        actions.insertBefore(group, actions.firstChild);
    });

    // ------------------------------------------------------- quick add
    // A pick list that can get a new record without leaving the page:
    //   <select data-quick-add="/suppliers/create" data-quick-title="New supplier">   a "+ New" link appears in its label
    //   <button data-quick-add="/items/raw/create" data-quick-target="select[data-item]">   for lists inside rows
    // The form opens in a pop-up, is saved in the background, and the new record is chosen in the list.
    var quickModal = null;
    var quickFor = null;
    var quickOpen = function (trigger) {
        var url = trigger.getAttribute('data-quick-add');
        if (!url || !window.fetch) return;
        quickFor = trigger;
        if (!quickModal) {
            quickModal = document.createElement('div');
            quickModal.className = 'modal fade erp-quick';
            quickModal.setAttribute('tabindex', '-1');
            quickModal.setAttribute('aria-hidden', 'true');
            quickModal.innerHTML = '<div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h2 class="h5 modal-title"></h2>'
                + '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' + ERP.escape(T.cancel || 'Cancel') + '"></button></div><div class="modal-body"></div></div></div>';
            body.appendChild(quickModal);
        }
        body.appendChild(quickModal);              // above a form that is open in a modal
        var box = $('.modal-body', quickModal);
        $('.modal-title', quickModal).textContent = trigger.getAttribute('data-quick-title') || trigger.textContent.trim() || '';
        box.innerHTML = '<div class="text-center text-muted py-5">\u2026</div>';
        ERP.showModal(quickModal);
        fetch(url + (url.indexOf('?') < 0 ? '?' : '&') + 'modal=1', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) { if (!response.ok) throw new Error(String(response.status)); return response.text(); })
            .then(function (html) {
                box.innerHTML = html;
                // the pop-up must not reuse the ids of the page under it
                $$('[id]', box).forEach(function (el) { el.id = 'q-' + el.id; });
                $$('label[for]', box).forEach(function (el) { el.setAttribute('for', 'q-' + el.getAttribute('for')); });
                enhance(box);
                var form = $('form[data-quick-form]', box);
                if (form) form.addEventListener('submit', quickSend);
                var first = $('input:not([type="hidden"]):not([type="checkbox"]), select, textarea', box);
                if (first) first.focus();
            })
            .catch(function () { ERP.hideModal(quickModal); ERP.toast(T.failed || 'Could not open the form.', 'danger'); });
    };
    var quickSend = function (e) {
        e.preventDefault();
        e.stopPropagation();
        var form = e.currentTarget;
        if (form._busy) return;
        form._busy = true;
        var button = $('button[type="submit"]', form);
        if (button) button.classList.add('is-busy');
        var errors = $('[data-quick-errors]', form);
        var fail = function (messages) {
            form._busy = false;
            if (button) button.classList.remove('is-busy');
            if (!errors) return;
            errors.innerHTML = messages.map(function (m) { return '<div>' + ERP.escape(m) + '</div>'; }).join('');
            errors.hidden = false;
            errors.scrollIntoView({ block: 'nearest' });
        };
        fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data || {} }; }); })
            .then(function (result) {
                if (!result.ok) {
                    var list = [];
                    var bag = result.data.errors || {};
                    Object.keys(bag).forEach(function (key) { list = list.concat(bag[key]); });
                    fail(list.length ? list : [result.data.message || T.failed || 'Could not save.']);
                    return;
                }
                ERP.hideModal(quickModal);
                if (result.data.message) ERP.toast(result.data.message, 'success');
                // the page shows what was changed (the receiver of a remittance, for example): it is read again
                if (quickFor.hasAttribute('data-quick-reload')) { setTimeout(function () { window.location.reload(); }, 500); return; }
                // a file completed for the transaction on the screen: back to its Save button
                if (quickFor.hasAttribute('data-quick-auto')) {
                    var save = $('.app-content form:not([data-quick-form]) button[type="submit"]');
                    if (save) { save.scrollIntoView({ block: 'center' }); save.focus(); }
                    return;
                }
                quickRefresh(quickFor, (result.data.ids || []).concat(result.data.names || []).map(String));
            })
            .catch(function () { fail([T.failed || 'Could not save.']); });
    };
    // Read the page again in the background and copy the fresh choices into the lists, then pick the new record.
    var quickRefresh = function (trigger, ids) {
        var selector = trigger.getAttribute('data-quick-target');
        var targets = selector ? $$(selector) : [trigger];
        var templates = selector ? $$('template') : [];
        var source = (trigger.closest && trigger.closest('.erp-form-modal') && ERP.modalFormUrl) || window.location.href;
        fetch(source, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) { return response.text(); })
            .then(function (html) {
                var fresh = new DOMParser().parseFromString(html, 'text/html');
                var find = function (query) {
                    var found = fresh.querySelector(query);
                    for (var i = 0, all = fresh.querySelectorAll('template'); !found && i < all.length; i++) found = all[i].content.querySelector(query);
                    return found;
                };
                var copy = function (select, source) {
                    var keep = select.value;
                    select.innerHTML = source.innerHTML;
                    select.value = keep;
                    if (select.value !== keep) select.selectedIndex = 0;
                };
                var picked = false;
                targets.forEach(function (select) {
                    var source = (select.name && fresh.querySelector('select[name="' + select.name.replace(/"/g, '\\"') + '"]')) || (selector ? find(selector) : null);
                    if (!source) return;
                    copy(select, source);
                    // the new record goes into the list that asked for it, or into the first empty row
                    if (!picked && (!selector || !select.value)) {
                        for (var i = 0; i < ids.length; i++) {
                            select.value = ids[i];
                            if (select.value === ids[i]) { picked = true; break; }
                        }
                        if (!picked) select.value = '';
                    }
                    if (select._ssSync) select._ssSync();
                    if (select.value) select.dispatchEvent(new Event('change', { bubbles: true }));
                });
                templates.forEach(function (template) {
                    var inside = template.content.querySelector(selector);
                    var source = inside ? find(selector) : null;
                    if (source) inside.innerHTML = source.innerHTML;
                });
            })
            .catch(function () { return null; });
    };
    // the file of a customer lacked something for the transaction just tried: its pop-up opens by itself
    var quickAuto = $('[data-quick-auto]');
    if (quickAuto) setTimeout(function () { quickOpen(quickAuto); }, 150);
    // a button or link that shows only an icon takes its name from its title
    ERP.nameIcons = function (root) {
        $$('button, a', root || document).forEach(function (el) {
            if (el.getAttribute('aria-label') || el.getAttribute('aria-labelledby') || el.textContent.trim() !== '') return;
            var name = el.getAttribute('title') || el.getAttribute('data-bs-original-title');
            if (name) el.setAttribute('aria-label', name);
        });
    };
    pageInits.push(function (scope) { ERP.nameIcons(scope); });

    // an invoice: the price of the chosen item is offered, and the amounts and the total follow what is typed
    pageInits.push(function (scope) { $$('form[data-invoice]', scope).forEach(function (form) {
        var baseId = form.getAttribute('data-base');
        var currency = form.querySelector('[name=currency_id]');
        var discount = form.querySelector('[name=discount]');
        var totalBox = form.querySelector('[data-total]');
        function num(v) { v = parseFloat(String(v || '').replace(/,/g, '')); return isNaN(v) ? 0 : v; }
        function fmt(v) { return v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
        function total() {
            var sum = 0;
            $$('tr[data-line]', form).forEach(function (tr) {
                var amount = Math.round(num(tr.querySelector('input[data-qty]').value) * num(tr.querySelector('input[data-price]').value) * 100) / 100;
                tr.querySelector('[data-amount]').textContent = amount ? fmt(amount) : '';
                sum += amount;
            });
            var t = Math.max(0, sum - num(discount.value));
            totalBox.textContent = fmt(t);
            return t;
        }
        form.addEventListener('change', function (e) {
            var select = e.target.closest && e.target.closest('select[data-item]');
            if (select) {
                var tr = select.closest('tr');
                var option = select.options[select.selectedIndex];
                var qty = tr.querySelector('input[data-qty]');
                var price = tr.querySelector('input[data-price]');
                if (option && option.value) {
                    if (!qty.value) qty.value = '1';
                    if (!price.value && currency.value === baseId && num(option.getAttribute('data-price'))) price.value = option.getAttribute('data-price');
                }
            }
            total();
        });
        form.addEventListener('input', total);
        var all = form.querySelector('[data-pay-all]');
        if (all) all.addEventListener('click', function () { form.querySelector('[name=paid]').value = total().toFixed(2); });
        total();
    }); });

    // the tour of the screen: a ring around one part at a time, with a card that says what it is
    (function () {
        var tour = window.ERP_TOUR;
        if (!tour || !tour.steps || !tour.steps.length) return;
        var key = 'hb.tour.' + tour.user;
        var ring = null, card = null, at = 0;
        function seen() { try { return localStorage.getItem(key) === '1'; } catch (e) { return true; } }
        function end() {
            if (ring) ring.remove();
            if (card) card.remove();
            ring = card = null;
            try { localStorage.setItem(key, '1'); } catch (e) { /* private window */ }
        }
        function button(text, cls, fn) {
            var b = document.createElement('button');
            b.type = 'button'; b.className = 'btn btn-sm ' + cls; b.textContent = text;
            b.addEventListener('click', fn);
            return b;
        }
        function show(i, dir) {
            var el = null;
            while (i >= 0 && i < tour.steps.length) {
                el = document.querySelector(tour.steps[i].el);
                if (el && el.getClientRects().length) break;
                i += dir;
            }
            if (i < 0 || i >= tour.steps.length) { end(); return; }
            at = i;
            if (!ring) {
                ring = document.createElement('div'); ring.className = 'tour-ring';
                card = document.createElement('div'); card.className = 'tour-card'; card.setAttribute('role', 'dialog');
                document.body.appendChild(ring); document.body.appendChild(card);
            }
            var r = el.getBoundingClientRect();
            ring.style.top = (r.top - 4) + 'px'; ring.style.left = (r.left - 4) + 'px';
            ring.style.width = (r.width + 8) + 'px'; ring.style.height = (r.height + 8) + 'px';
            card.textContent = '';
            var title = document.createElement('div'); title.className = 'tour-title'; title.textContent = tour.steps[i].title;
            var text = document.createElement('div'); text.className = 'tour-text'; text.textContent = tour.steps[i].text;
            var actions = document.createElement('div'); actions.className = 'tour-actions';
            var count = document.createElement('span'); count.className = 'tour-count'; count.textContent = (i + 1) + ' / ' + tour.steps.length;
            actions.appendChild(count);
            actions.appendChild(button(T.tourSkip || 'Skip', 'btn-light-secondary', end));
            if (i > 0) actions.appendChild(button(T.tourBack || 'Back', 'btn-light-secondary', function () { show(at - 1, -1); }));
            actions.appendChild(button(i === tour.steps.length - 1 ? (T.tourDone || 'Done') : (T.tourNext || 'Next'), 'btn-primary', function () { show(at + 1, 1); }));
            card.appendChild(title); card.appendChild(text); card.appendChild(actions);
            var w = card.offsetWidth, h = card.offsetHeight, top, left;
            if (r.height > window.innerHeight * 0.6) {           // a tall part (the menu): the card stands beside it
                top = Math.max(8, r.top + 24);
                left = r.right + 12 + w <= window.innerWidth ? r.right + 12 : r.left - w - 12;
            } else {
                top = r.bottom + 12 + h <= window.innerHeight ? r.bottom + 12 : r.top - h - 12;
                left = r.left;
            }
            card.style.top = Math.max(8, Math.min(top, window.innerHeight - h - 8)) + 'px';
            card.style.left = Math.max(8, Math.min(left, window.innerWidth - w - 8)) + 'px';
        }
        ERP.tour = { start: function () { show(0, 1); }, end: end };
        document.addEventListener('click', function (e) { if (e.target.closest && e.target.closest('[data-tour-start]')) show(0, 1); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && ring) end(); });
        window.addEventListener('resize', function () { if (ring) show(at, 1); });
        if (tour.auto && !seen()) setTimeout(function () { show(0, 1); }, 700);
    })();

    // a voice note: the button records from the microphone, and on the second press the recording is attached
    pageInits.push(function (scope) { $$('[data-recorder]', scope).forEach(function (button) {
        if (!navigator.mediaDevices || !window.MediaRecorder || !window.DataTransfer) { button.hidden = true; return; }
        var recorder = null;
        var chunks = [];
        var label = button.querySelector('[data-recorder-text]');
        button.addEventListener('click', function () {
            if (recorder) { recorder.stop(); return; }
            navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
                chunks = [];
                recorder = new MediaRecorder(stream);
                recorder.ondataavailable = function (e) { if (e.data && e.data.size) chunks.push(e.data); };
                recorder.onstop = function () {
                    stream.getTracks().forEach(function (track) { track.stop(); });
                    var type = (recorder.mimeType || 'audio/webm').split(';')[0];
                    var ext = type.indexOf('mp4') > -1 ? 'm4a' : (type.indexOf('ogg') > -1 ? 'ogg' : 'webm');
                    recorder = null;
                    var form = button.closest('form');
                    var input = form.querySelector('input[type=file]');
                    var files = new DataTransfer();
                    files.items.add(new File(chunks, 'voice-note-' + Date.now() + '.' + ext, { type: type }));
                    input.files = files.files;
                    form.submit();
                };
                recorder.start();
                button.classList.add('btn-danger');
                if (label) label.textContent = button.getAttribute('data-stop');
            }).catch(function () { ERP.toast(button.getAttribute('data-denied'), 'danger'); });
        });
    }); });

    // a small "+ New" link in the label of every pick list that allows it
    pageInits.push(function (scope) { $$('select[data-quick-add]', scope).forEach(function (select) {
        if (select.closest('.modal')) return;
        var label = select.id ? $('label[for="' + select.id + '"]') : null;
        if (!label) return;
        var link = document.createElement('button');
        link.type = 'button';
        link.className = 'quick-add';
        link.innerHTML = ERP.icon('s-plus') + ' ' + ERP.escape(T.addNew || 'New');
        link.title = select.getAttribute('data-quick-title') || '';
        link.addEventListener('click', function () { quickOpen(select); });
        label.classList.add('has-quick-add');
        label.appendChild(link);
    }); });
    document.addEventListener('click', function (e) {
        var button = e.target.closest('button[data-quick-add]');
        if (button) { e.preventDefault(); quickOpen(button); }
    });

    // ------------------------------------------------------- a list that follows another list
    // <select data-follows="#f-country" data-choices='{"Afghanistan":["Kabul", ...]}' data-plain>
    // When the first list changes, the second shows the choices of that value, in a box you can
    // type in to search. A value without
    // choices gets a box to type in instead (same field name).
    pageInits.push(function (scope) { $$('select[data-follows]', scope).forEach(function (select) {
        var parent = document.querySelector(select.getAttribute('data-follows'));
        var choices = {};
        try { choices = JSON.parse(select.getAttribute('data-choices') || '{}'); } catch (e) { choices = {}; }
        if (!parent) return;
        var current = select.getAttribute('data-value') || select.value || '';
        var box = document.createElement('input');
        box.type = 'text';
        box.className = 'form-control';
        box.name = select.name;
        box.maxLength = 100;
        select.parentNode.insertBefore(box, select.nextSibling);
        var fill = function () {
            var list = choices[parent.value] || null;
            select.disabled = !list;
            if (!select._ss) select.hidden = !list;
            if (select._ss) select.parentNode.style.display = list ? '' : 'none';
            box.hidden = !!list; box.disabled = !!list;
            if (!list) { box.value = current; return; }
            select.innerHTML = '';
            var blank = document.createElement('option');
            blank.value = ''; blank.textContent = '\u2026';
            select.appendChild(blank);
            list.concat(current && list.indexOf(current) < 0 ? [current] : []).forEach(function (name) {
                var option = document.createElement('option');
                option.value = name; option.textContent = name;
                if (name === current) option.selected = true;
                select.appendChild(option);
            });
            // the list is one you can type in to search, like the country above it
            if (!select._ss) ERP.searchSelect(select);
            if (select._ssSync) select._ssSync();
        };
        parent.addEventListener('change', function () { current = ''; fill(); });
        select.addEventListener('change', function () { current = select.value; });
        box.addEventListener('input', function () { current = box.value; });
        fill();
    }); });

    // ------------------------------------------------------- rows of a paper
    // <table data-rows> with <tbody data-rows-body>, <template data-row-template> (uses __i__ as
    // the row number), a [data-add-row] button and a [data-remove-row] button in every row.
    var rowCounter = 1000;
    document.addEventListener('click', function (e) {
        var add = e.target.closest('[data-add-row]');
        if (add) {
            // a form can have several groups of rows: data-add-row="costs" works on data-rows-body="costs"
            var form = add.closest('form');
            var group = add.getAttribute('data-add-row');
            var pick = group ? '="' + group + '"' : '';
            var body = $('[data-rows-body' + pick + ']', form);
            var template = $('[data-row-template' + pick + ']', form);
            if (!body || !template) return;
            var holder = document.createElement('tbody');
            holder.innerHTML = template.innerHTML.replace(/__i__/g, String(rowCounter++));
            var row = holder.firstElementChild;
            body.appendChild(row);
            enhance(row);
            var first = $('input:not([type="hidden"]), select', row);
            if (first) first.focus();
            return;
        }
        var remove = e.target.closest('[data-remove-row]');
        if (remove) {
            var tr = remove.closest('[data-row]');
            var tbody = tr.parentNode;
            if ($$('[data-row]', tbody).length > 1) tr.remove();
            else $$('input:not([type="hidden"])', tr).forEach(function (i) { i.value = ''; });
            recalc(tbody.closest('form'));
        }
    });

    // Totals of a sale or purchase. Only for showing: the server calculates again when saving.
    var num = function (v) { var n = parseFloat(String(v == null ? '' : v).replace(/,/g, '')); return isFinite(n) ? n : 0; };
    var show2 = function (n) { return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    function recalc(form) {
        if (!form || !form.hasAttribute('data-totals')) return;
        var subtotal = 0;
        $$('[data-row]', form).forEach(function (row) {
            var qty = $('input[data-qty]', row);
            var price = $('input[data-price]', row);
            var out = $('[data-line-total]', row);
            if (!qty || !price) return;
            var total = Math.round(num(qty.value) * num(price.value) * 100) / 100;
            subtotal += total;
            if (out) out.textContent = show2(total);
        });
        var discount = num(($('input[data-discount]', form) || {}).value);
        var set = function (sel, value) { var el = $(sel, form); if (el) el.textContent = show2(value); };
        set('[data-subtotal]', subtotal);
        set('[data-grand]', subtotal - discount);
        var paid = num(($('input[data-paid]', form) || {}).value);
        set('[data-remaining]', subtotal - discount - paid);
    }
    document.addEventListener('input', function (e) { recalc(e.target.closest('form')); });
    // choosing an item fills its usual price and unit
    document.addEventListener('change', function (e) {
        var select = e.target;
        if (!select.matches || !select.matches('select[data-item]')) return;
        var option = select.options[select.selectedIndex];
        var row = select.closest('[data-row]');
        if (!option || !row) return;
        var price = $('input[data-price]', row);
        var form = select.closest('form');
        var party = $('select[data-party]', form);
        var partyOption = party ? party.options[party.selectedIndex] : null;
        var inBase = !party || (partyOption && partyOption.getAttribute('data-cur') === form.getAttribute('data-base'));
        // a customer's own price (Customer Price Lists) comes first, then the usual price
        var own = null;
        try { own = ((JSON.parse(form.getAttribute('data-prices') || '{}')[party ? party.value : ''] || {})[select.value]) || null; } catch (err) { own = null; }
        if (price && !price.value && own) price.value = own;
        if (price && !price.value && inBase && option.getAttribute('data-price')) price.value = option.getAttribute('data-price');
        var unit = $('[data-unit]', row);
        if (unit) unit.textContent = option.getAttribute('data-unit') || '';
        recalc(select.closest('form'));
    });
    // the currency of the chosen customer or supplier is shown next to the totals
    var showCurrency = function (form) {
        var party = $('select[data-party]', form);
        var option = party ? party.options[party.selectedIndex] : null;
        var code = option && option.value ? option.getAttribute('data-cur') : '';
        $$('[data-party-cur]', form).forEach(function (el) { el.textContent = code; });
    };
    document.addEventListener('change', function (e) {
        if (e.target.matches && e.target.matches('select[data-party]')) showCurrency(e.target.closest('form'));
    });
    pageInits.push(function (scope) { $$('form[data-totals]', scope).forEach(function (form) { recalc(form); showCurrency(form); }); });

    // Currency exchange form: the currencies follow the chosen account, the rate comes from the
    // rates table (or is typed by hand), and the buying amount is worked out while typing.
    pageInits.push(function (scope) { $$('form[data-exchange]', scope).forEach(function (form) {
        var rates = {};
        try { rates = JSON.parse(form.getAttribute('data-rates') || '{}') || {}; } catch (err) { rates = {}; }
        var holder = $('[name="holder"]', form);
        var sellCur = $('[data-exchange-sell]', form);
        var buyCur = $('[data-exchange-buy]', form);
        var sellAmount = $('[name="sell_amount"]', form);
        var buyAmount = $('[name="buy_amount"]', form);
        var rate = $('[name="rate"]', form);
        var operation = $('[name="operation"]', form);
        var date = $('[name="date"]', form);
        var hint = $('[data-rate-hint]', form);
        var sum = $('[data-exchange-sum]', form);
        if (!holder || !sellCur || !buyCur) return;

        var number = function (input) { var v = parseFloat(String(input.value).replace(/,/g, '')); return isFinite(v) && v > 0 ? v : 0; };
        var show = function (value, decimals) {
            if (!(value > 0) || !isFinite(value)) return '';
            var text = value.toFixed(decimals);
            if (decimals > 2) text = text.replace(/0+$/, '').replace(/\.$/, '');
            return ERP.formatNumber(text);
        };
        var code = function (select) { var o = select.options[select.selectedIndex]; return o ? o.getAttribute('data-code') : ''; };
        var say = function (key) { if (hint) hint.textContent = key ? form.getAttribute('data-text-' + key) || '' : ''; };

        var summary = function () {
            $$('[data-sell-code]', form).forEach(function (el) { el.textContent = '(' + code(sellCur) + ')'; });
            $$('[data-buy-code]', form).forEach(function (el) { el.textContent = '(' + code(buyCur) + ')'; });
            if (!sum) return;
            var sold = number(sellAmount), bought = number(buyAmount), r = number(rate);
            sum.hidden = !(sold && bought && r);
            if (!sum.hidden) {
                sum.textContent = show(sold, 2) + ' ' + code(sellCur) + ' ' + (operation.value === 'divide' ? '\u00f7' : '\u00d7') + ' ' + show(r, 6) + ' = ' + show(bought, 2) + ' ' + code(buyCur);
            }
        };
        var calcBuy = function () {
            var sold = number(sellAmount), r = number(rate);
            buyAmount.value = sold && r ? show(operation.value === 'divide' ? sold / r : sold * r, 2) : '';
            summary();
        };
        var calcRate = function () {
            var sold = number(sellAmount), bought = number(buyAmount);
            if (sold && bought) { rate.value = show(operation.value === 'divide' ? sold / bought : bought / sold, 6); say('manual'); }
            summary();
        };
        var tableRate = function () {
            if (!holder.value) { say(''); summary(); return; }
            var s = parseFloat(rates[sellCur.value]), b = parseFloat(rates[buyCur.value]);
            if (!(s > 0) || !(b > 0)) { rate.value = ''; say('none'); calcBuy(); return; }
            operation.value = b >= s ? 'multiply' : 'divide';
            rate.value = show(b >= s ? b / s : s / b, 6);
            say('table');
            calcBuy();
        };
        // only the currencies the chosen account has can be picked, and never the same one twice
        var limit = function () {
            var option = holder.options[holder.selectedIndex];
            var allowed = option && option.value ? String(option.getAttribute('data-currencies') || '').split(',') : null;
            [sellCur, buyCur].forEach(function (select) {
                Array.prototype.forEach.call(select.options, function (o) {
                    var ok = !allowed || allowed.indexOf(o.value) >= 0;
                    o.hidden = !ok;
                    o.disabled = !ok;
                });
                if (select.options[select.selectedIndex] && select.options[select.selectedIndex].disabled) {
                    var first = Array.prototype.filter.call(select.options, function (o) { return !o.disabled; })[0];
                    if (first) select.value = first.value;
                }
            });
        };
        var differ = function (changed) {
            if (sellCur.value !== buyCur.value) return;
            var other = changed === buyCur ? sellCur : buyCur;
            var next = Array.prototype.filter.call(other.options, function (o) { return !o.disabled && o.value !== changed.value; })[0];
            if (next) other.value = next.value;
        };

        holder.addEventListener('change', function () { limit(); differ(sellCur); tableRate(); });
        sellCur.addEventListener('change', function () { differ(sellCur); tableRate(); });
        buyCur.addEventListener('change', function () { differ(buyCur); tableRate(); });
        sellAmount.addEventListener('input', calcBuy);
        rate.addEventListener('input', function () { say('manual'); calcBuy(); });
        operation.addEventListener('change', calcBuy);
        buyAmount.addEventListener('input', calcRate);
        var again = $('[data-table-rate]', form);
        if (again) again.addEventListener('click', tableRate);
        if (date) date.addEventListener('change', function () {
            var url = form.getAttribute('data-rates-url');
            if (!url || !date.value || !window.fetch) return;
            var query = '?date=' + encodeURIComponent(date.value) + ($('input[name="_shamsi[]"]', form) ? '&_shamsi[]=date' : '');
            fetch(url + query, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (data) { if (data) { rates = data; tableRate(); } })
                .catch(function () { return null; });
        });

        limit();
        differ(sellCur);
        if (rate.value) summary(); else tableRate();
    }); });

    // Money form: show the second amount only when the two accounts use different currencies.
    pageInits.push(function (scope) { $$('form[data-money-form]', scope).forEach(function (form) {
        var sync = function () {
            var cur = {};
            $$('select[data-currency-of]', form).forEach(function (s) {
                var o = s.options[s.selectedIndex];
                cur[s.getAttribute('data-currency-of')] = o && o.value ? o.getAttribute('data-cur') : '';
            });
            $$('[data-cur-label]', form).forEach(function (el) { var c = cur[el.getAttribute('data-cur-label')]; el.textContent = c ? '(' + c + ')' : ''; });
            var box = $('[data-other-amount]', form);
            if (box) {
                var differ = !!cur.money && !!cur.other && cur.money !== cur.other;
                box.hidden = !differ;
                if (!differ) $('input', box).value = '';
            }
        };
        // Money in / money out: choosing the person picks the cash box of the same currency.
        // A money account the user already chose in that currency is left alone.
        var pickMoney = function () {
            var other = $('select[data-currency-of="other"]', form);
            var money = $('select[data-currency-of="money"]', form);
            if (!other || !money) return;
            var chosen = other.options[other.selectedIndex];
            var cur = chosen && chosen.value ? chosen.getAttribute('data-cur') : '';
            var now = money.options[money.selectedIndex];
            if (!cur || (now && now.value && now.getAttribute('data-cur') === cur)) return;
            var match = null;
            for (var i = 0; i < money.options.length; i++) {
                var o = money.options[i];
                if (!o.value || o.getAttribute('data-cur') !== cur) continue;
                // a cash box comes before a bank or sarafi
                if (o.hasAttribute('data-cash')) { match = o; break; }
                match = match || o;
            }
            if (match) { money.value = match.value; if (money._ssSync) money._ssSync(); }
        };
        form.addEventListener('change', function (e) {
            if (form.hasAttribute('data-auto-money') && e.target.matches && e.target.matches('select[data-currency-of="other"]')) pickMoney();
            sync();
        });
        if (form.hasAttribute('data-auto-money')) pickMoney();
        sync();
    }); });

    // ------------------------------------------- tables become cards on phones
    var SKIP = '.keep-table, .table-sm';
    ERP.cardify = function (table) {
        if (table.matches(SKIP)) return;
        if (!phone()) { table.classList.remove('table-cards'); return; }
        var heads = $$('thead tr:first-child th', table).map(function (th) { return th.textContent.trim(); });
        $$('tbody > tr', table).forEach(function (tr) {
            var cells = Array.prototype.slice.call(tr.children);
            cells.forEach(function (td, i) {
                if (td.tagName !== 'TD') return;
                td.setAttribute('data-label', heads[i] || '');
                td.classList.toggle('cell-primary', i === 0);
                td.classList.toggle('cell-actions', i === cells.length - 1 && !!$('.btn, .btn-act, .dropdown', td));
            });
        });
        table.classList.add('table-cards');
    };
    var cardifyAll = function () { $$('.app-content table.table').forEach(ERP.cardify); };

    // ------------------------------------------- wide tables scroll, long texts wrap
    // A table wider than its card used to be cut off at the edge. Every table now sits in a box
    // that scrolls sideways, and a cell with a long text (details, a note) wraps onto more lines.
    ERP.fitTable = function (table) {
        if (table._fit) return;
        table._fit = true;
        if (!table.closest('.table-responsive, .table-scroll, .dt-container')) {
            var box = document.createElement('div');
            box.className = 'table-scroll';
            table.parentNode.insertBefore(box, table);
            box.appendChild(table);
        } else {
            var old = table.closest('.table-responsive');
            if (old) old.classList.add('table-scroll');
        }
        $$('tbody > tr > td', table).forEach(function (td) {
            if (td.children.length > 1 || $('.btn, input, select, textarea, .badge', td)) return;
            if (td.textContent.trim().length > 42) td.classList.add('cell-long');
        });
    };
    var fitAll = function () { $$('.app-content table.table').forEach(ERP.fitTable); };
    var resizeTimer;
    window.addEventListener('resize', function () { clearTimeout(resizeTimer); resizeTimer = setTimeout(cardifyAll, 200); });

    // ------------------------------------------------------ Solar Hijri date picker
    // A business on the Solar Hijri calendar types 1405/08/05 into its date boxes; this gives the
    // same boxes a calendar to pick from. (Gregorian date boxes use the picker of the browser.)
    (function () {
        var div = function (a, b) { return Math.floor(a / b); };
        var g2j = function (gy, gm, gd) {
            var cum = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
            var gy2 = gm > 2 ? gy + 1 : gy;
            var days = 355666 + 365 * gy + div(gy2 + 3, 4) - div(gy2 + 99, 100) + div(gy2 + 399, 400) + gd + cum[gm - 1];
            var jy = -1595 + 33 * div(days, 12053);
            days %= 12053;
            jy += 4 * div(days, 1461);
            days %= 1461;
            if (days > 365) { jy += div(days - 1, 365); days = (days - 1) % 365; }
            return days < 186 ? [jy, 1 + div(days, 31), 1 + (days % 31)] : [jy, 7 + div(days - 186, 30), 1 + ((days - 186) % 30)];
        };
        var j2g = function (jy, jm, jd) {
            jy += 1595;
            var days = -355668 + 365 * jy + div(jy, 33) * 8 + div((jy % 33) + 3, 4) + jd + (jm < 7 ? (jm - 1) * 31 : (jm - 7) * 30 + 186);
            var gy = 400 * div(days, 146097);
            days %= 146097;
            if (days > 36524) { gy += 100 * div(--days, 36524); days %= 36524; if (days >= 365) days++; }
            gy += 4 * div(days, 1461);
            days %= 1461;
            if (days > 365) { gy += div(days - 1, 365); days = (days - 1) % 365; }
            var gd = days + 1;
            var leap = (gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0;
            var len = [0, 31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
            var gm = 0;
            for (; gm < 13 && gd > len[gm]; gm++) gd -= len[gm];
            return [gy, gm, gd];
        };
        ERP.shamsi = { fromGregorian: g2j, toGregorian: j2g };
        var monthLength = function (y, m) {
            if (m < 7) return 31;
            if (m < 12) return 30;
            var g = j2g(y, 12, 30), back = g2j(g[0], g[1], g[2]);
            return back[1] === 12 && back[2] === 30 ? 30 : 29;
        };
        var lang = (document.documentElement.lang || 'en').slice(0, 2);
        var MONTHS = {
            en: ['Hamal', 'Sawr', 'Jawza', 'Saratan', 'Asad', 'Sunbula', 'Mizan', 'Aqrab', 'Qaws', 'Jadi', 'Dalw', 'Hoot'],
            fa: ['\u062d\u0645\u0644', '\u062b\u0648\u0631', '\u062c\u0648\u0632\u0627', '\u0633\u0631\u0637\u0627\u0646', '\u0627\u0633\u062f', '\u0633\u0646\u0628\u0644\u0647', '\u0645\u06cc\u0632\u0627\u0646', '\u0639\u0642\u0631\u0628', '\u0642\u0648\u0633', '\u062c\u062f\u06cc', '\u062f\u0644\u0648', '\u062d\u0648\u062a'],
            ps: ['\u0648\u0631\u06cc', '\u063a\u0648\u06cc\u06cc', '\u063a\u0628\u0631\u06ab\u0648\u0644\u06cc', '\u0686\u0646\u06ab\u0627\u069a', '\u0632\u0645\u0631\u06cc', '\u0648\u0696\u06cc', '\u062a\u0644\u0647', '\u0644\u0693\u0645', '\u0644\u06cc\u0646\u062f\u06cd', '\u0645\u0631\u063a\u0648\u0645\u06cc', '\u0633\u0644\u0648\u0627\u063a\u0647', '\u06a9\u0628']
        }[lang] || null;
        if (!MONTHS) MONTHS = ['Hamal', 'Sawr', 'Jawza', 'Saratan', 'Asad', 'Sunbula', 'Mizan', 'Aqrab', 'Qaws', 'Jadi', 'Dalw', 'Hoot'];
        // the week starts on Saturday
        var DAYS = lang === 'en' ? ['Sa', 'Su', 'Mo', 'Tu', 'We', 'Th', 'Fr'] : ['\u0634', '\u06cc', '\u062f', '\u0633', '\u0686', '\u067e', '\u062c'];
        var WORDS = { en: ['Today', 'Clear'], fa: ['\u0627\u0645\u0631\u0648\u0632', '\u067e\u0627\u06a9 \u06a9\u0631\u062f\u0646'], ps: ['\u0646\u0646', '\u067e\u0627\u06a9\u0648\u0644'] }[lang] || ['Today', 'Clear'];
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };
        var latin = function (s) { return String(s).replace(/[\u06f0-\u06f9]/g, function (d) { return '\u06f0\u06f1\u06f2\u06f3\u06f4\u06f5\u06f6\u06f7\u06f8\u06f9'.indexOf(d); }).replace(/[\u0660-\u0669]/g, function (d) { return '\u0660\u0661\u0662\u0663\u0664\u0665\u0666\u0667\u0668\u0669'.indexOf(d); }); };
        var read = function (value) {
            var m = latin(value).trim().match(/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/);
            return m && +m[2] >= 1 && +m[2] <= 12 && +m[3] >= 1 && +m[3] <= 31 ? [+m[1], +m[2], +m[3]] : null;
        };
        var now = function () { var d = new Date(); return g2j(d.getFullYear(), d.getMonth() + 1, d.getDate()); };

        var pop = null, input = null, view = null;
        var close = function () { if (pop) { pop.remove(); pop = null; input = null; } };
        var place = function () {
            if (!pop || !input) return;
            var r = input.getBoundingClientRect(), w = pop.offsetWidth, h = pop.offsetHeight;
            var left = Math.max(8, Math.min(r.left, window.innerWidth - w - 8));
            var below = window.innerHeight - r.bottom > h + 12 || r.top < h + 12;
            pop.style.left = (left + window.scrollX) + 'px';
            pop.style.top = ((below ? r.bottom + 6 : r.top - h - 6) + window.scrollY) + 'px';
        };
        var draw = function () {
            var y = view[0], m = view[1], chosen = read(input.value), today = now();
            var g = j2g(y, m, 1);
            var first = (new Date(g[0], g[1] - 1, g[2]).getDay() + 1) % 7;
            var html = '<div class="dp-head"><button type="button" class="dp-nav" data-dp="prev" aria-label="\u2039">\u2039</button>'
                + '<select class="dp-month" data-dp="month">' + MONTHS.map(function (name, i) { return '<option value="' + (i + 1) + '"' + (i + 1 === m ? ' selected' : '') + '>' + name + '</option>'; }).join('') + '</select>'
                + '<input class="dp-year" data-dp="year" type="number" value="' + y + '" min="1300" max="1500" aria-label="Year">'
                + '<button type="button" class="dp-nav" data-dp="next" aria-label="\u203a">\u203a</button></div><div class="dp-grid">';
            DAYS.forEach(function (d, i) { html += '<span class="dp-dow' + (i === 6 ? ' dp-fri' : '') + '">' + d + '</span>'; });
            for (var b = 0; b < first; b++) html += '<span></span>';
            for (var d = 1, n = monthLength(y, m); d <= n; d++) {
                var cls = 'dp-day' + ((first + d - 1) % 7 === 6 ? ' dp-fri' : '')
                    + (today[0] === y && today[1] === m && today[2] === d ? ' dp-today' : '')
                    + (chosen && chosen[0] === y && chosen[1] === m && chosen[2] === d ? ' dp-chosen' : '');
                html += '<button type="button" class="' + cls + '" data-day="' + d + '">' + d + '</button>';
            }
            html += '</div><div class="dp-foot"><button type="button" class="dp-link" data-dp="today">' + WORDS[0] + '</button>'
                + (input.required ? '' : '<button type="button" class="dp-link" data-dp="clear">' + WORDS[1] + '</button>') + '</div>';
            pop.innerHTML = html;
            place();
        };
        var set = function (value) {
            var box = input;
            box.value = value;
            close();
            box.dispatchEvent(new Event('input', { bubbles: true }));
            box.dispatchEvent(new Event('change', { bubbles: true }));
        };
        var open = function (box) {
            if (box.readOnly || box.disabled) return;
            if (input === box) return;
            close();
            input = box;
            var have = read(box.value) || now();
            view = [have[0], have[1]];
            pop = document.createElement('div');
            pop.className = 'dp';
            pop.setAttribute('dir', document.documentElement.dir || 'ltr');
            // mousedown inside keeps the cursor in the date box (except in the month and year boxes)
            pop.addEventListener('mousedown', function (e) { if (!e.target.closest('select, input')) e.preventDefault(); });
            pop.addEventListener('click', function (e) {
                var day = e.target.closest('[data-day]'), act = e.target.closest('button[data-dp]');
                if (day) { set(view[0] + '/' + pad(view[1]) + '/' + pad(+day.getAttribute('data-day'))); return; }
                if (!act) return;
                var what = act.getAttribute('data-dp');
                if (what === 'today') { var t = now(); set(t[0] + '/' + pad(t[1]) + '/' + pad(t[2])); return; }
                if (what === 'clear') { set(''); return; }
                var month = view[1] + (what === 'next' ? 1 : -1);
                view = month > 12 ? [view[0] + 1, 1] : (month < 1 ? [view[0] - 1, 12] : [view[0], month]);
                draw();
            });
            pop.addEventListener('change', function (e) {
                var what = e.target.getAttribute('data-dp');
                if (what === 'month') view[1] = +e.target.value;
                if (what === 'year' && +e.target.value >= 1300 && +e.target.value <= 1500) view[0] = +e.target.value;
                draw();
            });
            document.body.appendChild(pop);
            draw();
        };
        document.addEventListener('focusin', function (e) { if (e.target.matches && e.target.matches('input[data-shamsi-date]')) open(e.target); });
        document.addEventListener('click', function (e) { if (e.target.matches && e.target.matches('input[data-shamsi-date]')) open(e.target); });
        // what is typed moves the calendar to that month
        document.addEventListener('input', function (e) {
            if (!pop || e.target !== input) return;
            var typed = read(input.value);
            if (typed) { view = [typed[0], typed[1]]; draw(); }
        });
        document.addEventListener('mousedown', function (e) { if (pop && e.target !== input && !pop.contains(e.target)) close(); });
        document.addEventListener('keydown', function (e) { if (pop && (e.key === 'Escape' || (e.key === 'Tab' && e.target === input))) close(); });
        window.addEventListener('resize', place);
        window.addEventListener('scroll', place, true);
    })();

    // ------------------------------------------------------------ help "?" marks
    // A tap on the "?" shows its explanation; inside a label it must not jump to the input.
    document.addEventListener('click', function (e) {
        var tip = e.target.closest && e.target.closest('.help-tip');
        if (tip) { e.preventDefault(); tip.focus(); }
    });


    // ------------------------------------------------------------ amounts in tables
    // Every figure of a list is set in one column width and in the colour of what it means: what came in
    // green, what went out red, a balance by its sign. The heads of the columns say which is which.
    var MONEY = /^[-−]?\(?\d[\d,٬]*(\.\d+)?\)?(\s?[A-Z]{3})?$/;
    function toneOf(head) {
        var tones = (window.ERP_TEXT || {}).tones || {}, text = (head || '').replace(/\s+/g, ' ').trim().toLowerCase(), found = '';
        Object.keys(tones).forEach(function (tone) {
            (tones[tone] || []).forEach(function (word) {
                word = String(word).toLowerCase();
                var tail = text.length - word.length - 1;
                if (!found && word && (text === word || text.indexOf(word + ' ') === 0 || (tail >= 0 && text.lastIndexOf(' ' + word) === tail))) found = tone;
            });
        });
        return found;
    }
    ERP.paintAmounts = function (scope) {
        $$('table.table', scope).forEach(function (table) {
            if (table.hasAttribute('data-rows') || table.closest('form[data-invoice]')) return;
            var heads = $$('thead tr:last-child th', table).map(function (th) { return toneOf(th.textContent); });
            $$('tbody tr, tfoot tr', table).forEach(function (row) {
                var at = 0;
                Array.prototype.forEach.call(row.children, function (cell) {
                    var tone = heads[at] || '';
                    at += cell.colSpan || 1;
                    if (cell.tagName !== 'TD' || cell.children.length > 1 || cell.querySelector('input, select, a.btn, button')) return;
                    var text = cell.textContent.replace(/\s+/g, ' ').trim();
                    if (!MONEY.test(text) || !/[.,٬]/.test(text) && !/[A-Z]{3}$/.test(text) && !cell.classList.contains('text-end')) return;
                    cell.classList.add('num');
                    if (/amt-|text-danger|text-success/.test(cell.className)) return;
                    var negative = /^[-−(]/.test(text), zero = !/[1-9]/.test(text);
                    if (zero) cell.classList.add('amt-zero');
                    else if (negative) cell.classList.add('amt-neg');
                    else if (tone === 'in') cell.classList.add('amt-in');
                    else if (tone === 'out') cell.classList.add('amt-out');
                    else if (tone === 'sign') cell.classList.add('amt-pos');
                    else if (tone === 'debit') cell.classList.add('amt-debit');
                    else if (tone === 'credit') cell.classList.add('amt-credit');
                });
            });
        });
    };
    pageInits.push(function (scope) { ERP.paintAmounts(scope); });

    // ------------------------------------------------------------ the filter bar
    // The first choices stay in sight and the rest fold behind "More filters"; what is switched on is
    // listed under the bar as chips, each taken off with one tap.
    ERP.filterBars = function (scope) {
        var T = window.ERP_TEXT || {};
        $$('form.filter-bar', scope).forEach(function (form) {
            if (form.hasAttribute('data-filter-ready')) return;
            form.setAttribute('data-filter-ready', '1');
            var cells = Array.prototype.filter.call(form.children, function (el) { return el.querySelector('input:not([type="hidden"]), select'); });
            var submit = Array.prototype.filter.call(form.children, function (el) { return !el.querySelector('input:not([type="hidden"]), select') && el.querySelector('button, .btn'); })[0];
            var chips = [];
            cells.forEach(function (cell, index) {
                var fields = $$('select, input:not([type="hidden"])', cell).filter(function (f) { return f.name; });
                var label = (cell.querySelector('label') || {}).textContent || '';
                var active = false;
                fields.forEach(function (field) {
                    var value = field.value, shown = value, first = '';
                    if (field.tagName === 'SELECT') {
                        first = field.options.length ? field.options[0].value : '';
                        shown = field.selectedIndex >= 0 ? field.options[field.selectedIndex].textContent.trim() : '';
                        // a choice is applied as soon as it is made
                        if (!field.hasAttribute('onchange')) field.addEventListener('change', function () { form.requestSubmit ? form.requestSubmit() : form.submit(); });
                        if (value === first) return;
                    } else if (field.type === 'checkbox' || field.type === 'radio') {
                        if (!field.checked) return;
                        shown = '';
                    } else if (value === '' || field.name === 'from' || field.name === 'to') {
                        return;
                    }
                    active = true;
                    chips.push({ field: field, first: first, label: (label || field.getAttribute('aria-label') || field.getAttribute('placeholder') || '').replace(/[*:…]/g, '').trim(), value: shown });
                });
                if (index >= 4 && !active) cell.classList.add('filter-extra');
            });
            var hidden = $$('.filter-extra', form).length;
            if (hidden) {
                var more = document.createElement('div');
                more.className = 'col-auto';
                var button = document.createElement('button');
                button.type = 'button'; button.className = 'btn btn-outline-soft filter-more';
                button.innerHTML = '<span></span><span class="filter-count"></span>';
                button.firstChild.textContent = T.moreFilters || 'More filters';
                button.lastChild.textContent = hidden;
                button.addEventListener('click', function () {
                    var open = form.classList.toggle('is-open');
                    button.firstChild.textContent = open ? (T.fewerFilters || 'Fewer filters') : (T.moreFilters || 'More filters');
                    button.lastChild.style.display = open ? 'none' : '';
                });
                more.appendChild(button);
                form.insertBefore(more, submit || null);
            }
            if (chips.length) {
                var strip = document.createElement('div');
                strip.className = 'filter-chips no-print';
                chips.forEach(function (chip) {
                    var el = document.createElement('span'); el.className = 'filter-chip';
                    var name = document.createElement('small'); name.textContent = chip.label ? chip.label + ':' : '';
                    var text = document.createElement('span'); text.textContent = chip.value || chip.label;
                    var x = document.createElement('button'); x.type = 'button'; x.textContent = '×'; x.setAttribute('aria-label', (T.removeFilter || 'Remove') + ' ' + chip.label);
                    x.addEventListener('click', function () {
                        if (chip.field.type === 'checkbox' || chip.field.type === 'radio') chip.field.checked = false; else chip.field.value = chip.first;
                        form.requestSubmit ? form.requestSubmit() : form.submit();
                    });
                    if (chip.label && chip.value) el.appendChild(name);
                    el.appendChild(text); el.appendChild(x); strip.appendChild(el);
                });
                if (chips.length > 1) {
                    var clear = document.createElement('button'); clear.type = 'button'; clear.className = 'filter-clear'; clear.textContent = T.clearFilters || 'Clear all';
                    clear.addEventListener('click', function () {
                        chips.forEach(function (chip) { if (chip.field.type === 'checkbox' || chip.field.type === 'radio') chip.field.checked = false; else chip.field.value = chip.first; });
                        form.requestSubmit ? form.requestSubmit() : form.submit();
                    });
                    strip.appendChild(clear);
                }
                form.parentNode.insertBefore(strip, form.nextSibling);
            }
        });
    };
    pageInits.push(function (scope) { ERP.filterBars(scope); });

    // -------------------------------------------------------------------- boot
    ERP.initPage = function (scope) {
        body.classList.toggle('no-large-title', !$('.app-content h1'));
        pageInits.forEach(function (init) { init(scope); });
        $$(AMOUNT, scope).forEach(function (input) { if (input.value) input.value = ERP.formatNumber(input.value); });
        enhance(scope);
        fitAll();
        cardifyAll();
    };
    var onScroll = function () { body.classList.toggle('is-scrolled', window.scrollY > 28); };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
    syncTheme();
    ERP.initPage(document);
    var active = $('#sidebarMenu .menu-sublink.active, #sidebarMenu .menu-item.active > .menu-link');
    var scroller = $('#sidebarScroll');
    if (active && scroller) scroller.scrollTop = Math.max(0, active.getBoundingClientRect().top - scroller.clientHeight / 2);
    // ------------------------------------------- pages open without a full load
    // A click on a link (or a filter form) fetches the page in the background and puts only its
    // content, its row of buttons and the menu in place: the sidebar, the navbar, the styles and
    // this script stay as they are. A link is fetched as soon as the pointer rests on it, so the
    // page is usually there before the click. Anything unusual (a download, a page of another
    // layout, an error, a script inside the page) is opened by the browser the ordinary way.
    (function () {
        if (!body.hasAttribute('data-soft') || !$('.app-content') || !window.fetch || !window.DOMParser || !window.history || !history.pushState) return;
        var SWAP = ['.app-content', '.quickbar', '#sidebarMenu'];
        var cache = {};
        var turn = 0;
        var here = location.pathname + location.search;
        var bar = document.createElement('div');
        bar.className = 'soft-bar';
        body.appendChild(bar);

        var plain = function (url) {
            if (url.origin !== location.origin) return false;
            if (url.searchParams.has('export') || url.searchParams.has('print') || url.searchParams.has('pdf')) return false;
            if (/^\/(logout|company-logo|support-enter|messages\/\d+)/.test(url.pathname)) return false;
            return !/\/(receipt|download)(\/|$)/.test(url.pathname);
        };
        var linkOf = function (e) {
            var a = e.target.closest && e.target.closest('a[href]');
            if (!a || a.target || a.hasAttribute('download') || a.hasAttribute('data-no-soft') || a.hasAttribute('data-bs-toggle')) return null;
            if (a.getAttribute('href').charAt(0) === '#') return null;
            var url;
            try { url = new URL(a.href, location.href); } catch (err) { return null; }
            if (!plain(url)) return null;
            // a jump inside the page that is open
            if (url.hash && url.pathname + url.search === here) return null;
            return url;
        };
        var load = function (href) {
            var hit = cache[href];
            if (hit && Date.now() - hit.at < 15000) return hit.promise;
            var promise = fetch(href, { credentials: 'same-origin', headers: { 'X-Soft-Navigation': '1', Accept: 'text/html' } }).then(function (response) {
                if (!response.ok || (response.headers.get('Content-Type') || '').indexOf('text/html') === -1) throw new Error('not a page');
                return response.text().then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    if (!doc.body.hasAttribute('data-soft') || !doc.querySelector('.app-content') || doc.querySelector('.app-content script')) throw new Error('another layout');
                    return { doc: doc, url: response.redirected && response.url ? response.url : href };
                });
            });
            cache[href] = { at: Date.now(), promise: promise };
            promise.catch(function () { delete cache[href]; });
            return promise;
        };
        var show = function (page, push, top) {
            if (push) {
                history.replaceState({ soft: true, y: window.scrollY }, '');
                history.pushState({ soft: true, y: 0 }, '', page.url);
            }
            SWAP.forEach(function (selector) {
                var fresh = page.doc.querySelector(selector);
                var old = $(selector);
                if (fresh && old) old.innerHTML = fresh.innerHTML;
            });
            document.title = page.doc.title;
            if (page.doc.body.hasAttribute('data-no-export')) body.setAttribute('data-no-export', ''); else body.removeAttribute('data-no-export');
            here = location.pathname + location.search;
            // what was fetched before may be out of date after this page
            cache = {};
            closeDrawer();
            closeDropdowns();
            Array.prototype.slice.call(page.doc.querySelectorAll('[data-toast]')).forEach(function (el) { ERP.toast(el.textContent.trim(), el.getAttribute('data-toast')); });
            ERP.initPage($('.app-content'));
            window.scrollTo(0, top || 0);
        };
        var go = function (href, push, top, quiet) {
            var mine = ++turn;
            if (!quiet) body.classList.add('is-loading');
            return load(href).then(function (page) {
                // a newer click won
                if (mine === turn) show(page, push, top);
            }).catch(function () {
                if (mine !== turn) return;
                if (push) location.href = href; else location.reload();
            }).then(function () {
                if (mine === turn) body.classList.remove('is-loading');
            });
        };
        ERP.visit = function (href) { return go(new URL(href, location.href).href, true); };

        // ------------------------------------------------------------ forms in a modal
        // A link to a form ("Add", "Edit", a button of the quick bar) opens the form over the page instead
        // of on a page of its own. The form page is fetched and its content shown in the modal; on Save it
        // is sent in the background: with a mistake the form comes back with its errors, done, the page
        // the form leads to (usually the list, now with the new record) is shown under the closed modal.
        // A form page opened directly by its address is still an ordinary page.
        var formModal = null;
        var formAt = null;                     // the address of the form that is open
        var formTurn = 0;
        var isFormLink = function (a, url) {
            if (!a || a.hasAttribute('data-no-modal') || (a.closest && a.closest('.erp-form-modal'))) return false;
            return a.hasAttribute('data-modal') || /\/(create|\d+\/edit)$/.test(url.pathname);
        };
        var fetchPage = function (href, init) {
            init = init || {};
            init.credentials = 'same-origin';
            init.headers = { 'X-Soft-Navigation': '1', Accept: 'text/html' };
            return fetch(href, init).then(function (response) {
                if (response.status === 419) { location.reload(); throw new Error('expired'); }
                if ((response.headers.get('Content-Type') || '').indexOf('text/html') === -1) throw new Error('not a page');
                return response.text().then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    if (!doc.body.hasAttribute('data-soft') || !doc.querySelector('.app-content')) throw new Error('another layout');
                    return { doc: doc, url: response.url || href, ok: response.ok };
                });
            });
        };
        var toastsOf = function (doc, only) {
            Array.prototype.slice.call(doc.querySelectorAll('[data-toast]')).forEach(function (el) {
                if (!only || el.getAttribute('data-toast') === only) ERP.toast(el.textContent.trim(), el.getAttribute('data-toast'));
            });
        };
        var buildModal = function () {
            formModal = document.createElement('div');
            formModal.className = 'modal fade erp-form-modal';
            formModal.setAttribute('tabindex', '-1');
            formModal.setAttribute('aria-hidden', 'true');
            formModal.setAttribute('role', 'dialog');
            formModal.setAttribute('aria-modal', 'true');
            formModal.setAttribute('data-static', '');
            formModal.innerHTML = '<div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down"><div class="modal-content">'
                + '<div class="modal-header"><span class="erp-form-icon"></span><div class="erp-form-head"><h2 class="h5 modal-title" id="erpFormTitle"></h2><p class="erp-form-sub"></p></div>'
                + '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' + ERP.escape(T.cancel || 'Cancel') + '"></button></div>'
                + '<div class="erp-form-progress"></div><div class="modal-body"></div></div></div>';
            formModal.setAttribute('aria-labelledby', 'erpFormTitle');
            // what was typed is not thrown away by a slip of the hand
            formModal.addEventListener('input', function (e) { if (e.isTrusted) formModal._changed = true; });
            formModal.addEventListener('change', function (e) { if (e.isTrusted) formModal._changed = true; });
            // Ctrl + Enter saves from anywhere in the form
            formModal.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' || !(e.ctrlKey || e.metaKey)) return;
                var form = $('.modal-body form[method="POST"], .modal-body form[method="post"]', formModal);
                if (!form) return;
                e.preventDefault();
                if (form.requestSubmit) form.requestSubmit(); else form.submit();
            });
            formModal.addEventListener('erp:hidden', function () {
                formAt = null;
                ERP.modalFormUrl = null;
                $('.modal-body', formModal).innerHTML = '';
                formModal._changed = false;
                formModal.classList.remove('is-waiting', 'is-saving');
                // something was added while it was open (a note, a file): the page under it is read again
                if (formModal._dirty) { formModal._dirty = false; go(location.href, false, window.scrollY, true); }
            });
            // a link inside the form: one back to the page under it (Cancel) only closes the modal,
            // any other one closes it and is followed
            formModal.addEventListener('click', function (e) {
                var a = e.target.closest('a[href]');
                if (!a || a.target || a.hasAttribute('download') || a.hasAttribute('data-bs-toggle') || a.getAttribute('href').charAt(0) === '#') return;
                var url;
                try { url = new URL(a.href, location.href); } catch (err) { return; }
                if (url.origin !== location.origin) return;
                e.preventDefault();
                e.stopPropagation();
                closeForm(function () {
                    // another form: it takes the place of this one in the same modal
                    if (a.hasAttribute('data-modal') || /\/(create|\d+\/edit)$/.test(url.pathname)) { formOpen(url.href, a); return; }
                    ERP.hideModal(formModal);
                    // Cancel leads back to the list under the modal: closing it is enough
                    if (url.pathname !== location.pathname) go(url.href, true);
                });
            });
            body.appendChild(formModal);
        };
        var closeForm = function (then) {
            then = then || function () { ERP.hideModal(formModal); };
            if (!formModal._changed) { then(); return; }
            ERP.confirm({ title: T.discardTitle || 'Close without saving?', text: T.discardText || 'What you typed will be lost.', ok: T.discardOk || 'Close', style: 'danger' }, function () { formModal._changed = false; then(); });
        };
        var isTop = function () { var open = $$('.modal.show'); return formModal && open.length && open[open.length - 1] === formModal; };
        document.addEventListener('click', function (e) {
            if (!formModal || !formModal._changed || !isTop()) return;
            var dismiss = e.target.closest && e.target.closest('[data-bs-dismiss="modal"]');
            if (!dismiss || !formModal.contains(dismiss)) return;
            e.preventDefault();
            e.stopPropagation();
            closeForm();
        }, true);
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape' || !formModal || !formModal._changed || !isTop()) return;
            e.preventDefault();
            e.stopPropagation();
            closeForm();
        }, true);
        var setHead = function (title, sub, icon) {
            $('.modal-title', formModal).textContent = title || '';
            var line = $('.erp-form-sub', formModal);
            line.textContent = sub || '';
            line.hidden = !sub;
            $('.erp-form-icon', formModal).innerHTML = icon || '';
        };
        // put the content of a form page into the modal; false when the page holds no form to send
        var fill = function (page) {
            var content = page.doc.querySelector('.app-content');
            if (!content || content.querySelector('script') || !content.querySelector('form[method="POST"], form[method="post"]')) return false;
            if (!formModal) buildModal();
            var header = content.querySelector('.page-header');
            var heading = header ? header.querySelector('h1') : content.querySelector('h1');
            var icon = header ? header.querySelector('.page-header-icon') : null;
            var sub = header ? header.querySelector('.page-header-sub') : null;
            setHead(heading ? heading.textContent.trim() : document.title, sub ? sub.textContent.trim() : '', icon ? icon.innerHTML : '');
            if (header) header.remove();
            // what belongs to the whole page (the notice of the demo, the announcements) stays on the page
            Array.prototype.slice.call(content.querySelectorAll('[data-page-only]')).forEach(function (el) { el.remove(); });
            // the box to start typing in: the first one with a mistake, else the first ordinary box, not a
            // date (its calendar would cover the form); on a phone none, so the keyboard does not jump up
            Array.prototype.slice.call(content.querySelectorAll('[autofocus]')).forEach(function (el) { el.removeAttribute('autofocus'); });
            var phone = window.matchMedia && window.matchMedia('(max-width: 575.98px)').matches;
            var start = content.querySelector('input.is-invalid, textarea.is-invalid') || (phone ? null : content.querySelector('input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not([type="date"]):not([data-shamsi-date]):not([readonly]):not([disabled]), textarea:not([readonly])'));
            if (start) start.setAttribute('autofocus', '');
            // with nothing to start in, the close button takes the focus (never a date box: its calendar would open)
            if (!formModal) buildModal();
            $('.btn-close', formModal).toggleAttribute('autofocus', !start);
            // the modal must not reuse the ids of the page under it
            var ids = {};
            Array.prototype.slice.call(content.querySelectorAll('[id]')).forEach(function (el) { ids[el.id] = 'mf-' + el.id; el.id = ids[el.id]; });
            ['for', 'aria-describedby', 'aria-labelledby', 'aria-controls', 'list', 'form'].forEach(function (attr) {
                Array.prototype.slice.call(content.querySelectorAll('[' + attr + ']')).forEach(function (el) {
                    el.setAttribute(attr, el.getAttribute(attr).split(/\s+/).map(function (id) { return ids[id] || id; }).join(' '));
                });
            });
            Array.prototype.slice.call(content.querySelectorAll('[data-bs-target], a[href^="#"]')).forEach(function (el) {
                var attr = el.hasAttribute('data-bs-target') ? 'data-bs-target' : 'href', value = el.getAttribute(attr);
                if (value && value.charAt(0) === '#' && ids[value.slice(1)]) el.setAttribute(attr, '#' + ids[value.slice(1)]);
            });
            // a wide form (an invoice with its lines, a table of rows) gets a wide modal
            var wide = content.querySelector('form[data-invoice], table[data-rows], .table-responsive table, .row .col-xl-8, .col-lg-8');
            var dialog = $('.modal-dialog', formModal);
            // a form of a few boxes gets a narrow modal
            var small = !wide && content.querySelectorAll('input:not([type="hidden"]), select, textarea').length <= 4 && !content.querySelector('[data-modal-stay]');
            dialog.classList.toggle('modal-xl', !!wide);
            dialog.classList.toggle('modal-lg', !wide && !small);
            dialog.classList.toggle('modal-md', small);
            var box = $('.modal-body', formModal);
            // the page keeps its content in a container of its own: the form is taken out of it
            var holder = content.children.length === 1 && content.firstElementChild.classList.contains('content-container') ? content.firstElementChild : content;
            box.innerHTML = '<div class="erp-form-in">' + holder.innerHTML + '</div>';
            formModal.classList.remove('is-waiting', 'is-saving');
            formModal._changed = false;
            formAt = page.url;
            ERP.modalFormUrl = page.url;
            ERP.initPage(box);
            return true;
        };
        var focusFirst = function () {
            var box = $('.modal-body', formModal);
            var first = $('[autofocus]', box);
            if (!first) { $('.btn-close', formModal).focus(); return; }
            try { first.focus({ preventScroll: true }); } catch (err) { first.focus(); }
            if (first.classList.contains('is-invalid')) first.scrollIntoView({ block: 'center' });
        };
        // a form is fetched while the pointer rests on its button, and kept for a few seconds only: it must be fresh
        var formCache = {};
        var formFetch = function (href) {
            var hit = formCache[href];
            if (hit && Date.now() - hit.at < 20000) return hit.promise;
            var promise = fetchPage(href);
            formCache[href] = { at: Date.now(), promise: promise };
            promise.catch(function () { delete formCache[href]; });
            return promise;
        };
        var SKELETON = '<div class="erp-form-skeleton" aria-hidden="true">' + new Array(4).join('<div class="sk-row"><div><i class="sk-label"></i><i class="sk-box"></i></div><div><i class="sk-label"></i><i class="sk-box"></i></div></div>')
            + '<div class="sk-row"><div><i class="sk-label"></i><i class="sk-box is-tall"></i></div></div></div>';
        // The modal opens the moment it is asked for, with the outline of a form; the form itself takes its
        // place as soon as it has arrived. Nothing of the page under it moves.
        var formOpen = function (href, trigger) {
            var mine = ++formTurn;
            closeDrawer();
            closeDropdowns();
            if (!formModal) buildModal();
            var label = trigger ? (trigger.getAttribute('title') || trigger.getAttribute('aria-label') || trigger.textContent.replace(/\s+/g, ' ').trim()) : '';
            setHead(label, '', '');
            $('.modal-body', formModal).innerHTML = SKELETON;
            formModal.classList.add('is-waiting');
            formModal.classList.remove('is-saving');
            formModal._changed = false;
            formAt = href;
            if (!formModal.classList.contains('show')) ERP.showModal(formModal);
            var away = function () { formModal._changed = false; ERP.hideModal(formModal); go(href, true); };
            return formFetch(href).then(function (page) {
                delete formCache[href];                                 // the page that was read is used up
                if (mine !== formTurn || !formModal.classList.contains('show')) return;
                if (!page.ok || !fill(page)) { away(); return; }
                toastsOf(page.doc);
                focusFirst();
            }).catch(function () {
                if (mine === formTurn && formModal.classList.contains('show')) away();
            });
        };
        ERP.openForm = function (href, trigger) { return formOpen(new URL(href, location.href).href, trigger); };
        var formSend = function (form, submitter) {
            var data;
            try { data = submitter && submitter.name ? new FormData(form, submitter) : new FormData(form); } catch (err) { data = new FormData(form); if (submitter && submitter.name) data.append(submitter.name, submitter.value); }
            var sentFrom = formAt;
            formModal.classList.add('is-saving');
            var done = function () {
                formModal.classList.remove('is-saving');
                form._sent = false;
                $$('.is-busy', form).forEach(function (b) { b.classList.remove('is-busy'); b.removeAttribute('aria-disabled'); });
            };
            // the form page is named as the page the form was sent from, so a mistake leads back to it
            fetchPage(form.action, { method: 'POST', body: data, referrer: sentFrom, referrerPolicy: 'same-origin' }).then(function (page) {
                if (!formModal.classList.contains('show') || formAt !== sentFrom) return;
                var back = new URL(page.url, location.href), from = new URL(sentFrom, location.href);
                var success = !!page.doc.querySelector('[data-toast="success"]');
                if (!page.ok) { done(); toastsOf(page.doc, 'danger'); if (!page.doc.querySelector('[data-toast="danger"]')) ERP.toast(T.failed || 'Could not save.', 'danger'); return; }
                if (back.pathname + back.search === from.pathname + from.search && !success) {
                    // a mistake: the same form, now with what is wrong marked
                    if (!fill(page)) { done(); return; }
                    formModal._changed = true;                           // what was typed is still not saved
                    toastsOf(page.doc, 'danger');
                    toastsOf(page.doc, 'warning');
                    focusFirst();
                    var dialog = $('.modal-dialog', formModal);
                    dialog.classList.remove('is-wrong');
                    void dialog.offsetWidth;
                    dialog.classList.add('is-wrong');
                    return;
                }
                if (back.pathname + back.search === from.pathname + from.search && page.doc.querySelector('[data-modal-stay]')) {
                    // a list of its own (notes, files): it stays open, with what was just added
                    if (fill(page)) { toastsOf(page.doc, 'success'); formModal._dirty = true; focusFirst(); return; }
                }
                formCache = {};
                formModal._changed = false;
                formModal._dirty = false;
                ERP.hideModal(formModal);
                if (back.pathname + back.search === here) {
                    // the list under the modal, now with what was saved: it is put in place where it stands
                    show({ doc: page.doc, url: page.url }, false, window.scrollY);
                } else if (/\/\d+$/.test(back.pathname)) {
                    // the page of the record itself (an invoice to print, a deal): it is opened
                    show({ doc: page.doc, url: page.url }, true, 0);
                } else {
                    // saved from another page (the dashboard, a filtered list): one stays there, and it is read again quietly
                    toastsOf(page.doc);
                    go(location.href, false, window.scrollY, true);
                }
            }).catch(function () {
                done();
                ERP.toast(T.failed || 'Could not save.', 'danger');
            });
        };
        // after the other handlers of a submit (the confirmation, the guard against sending twice)
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (e.defaultPrevented || !formModal || !formModal.contains(form) || form.hasAttribute('data-quick-form')) return;
            if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post' || form.target) return;
            e.preventDefault();
            formSend(form, e.submitter);
        });

        document.addEventListener('click', function (e) {
            if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            var url = linkOf(e);
            if (!url) return;
            e.preventDefault();
            if (isFormLink(e.target.closest('a[href]'), url)) { formOpen(url.href, e.target.closest('a[href]')); return; }
            go(url.href, true);
        });
        // the page is fetched while the pointer (or the finger) is still on its link
        var warm = function (e) {
            var url = linkOf(e);
            if (!url || url.pathname + url.search === here) return;
            if (isFormLink(e.target.closest('a[href]'), url)) formFetch(url.href).catch(function () { return null; });
            else load(url.href).catch(function () { return null; });
        };
        document.addEventListener('mouseover', warm, { passive: true });
        document.addEventListener('touchstart', warm, { passive: true });
        // a form that only asks (search boxes, the filters of a list or a report)
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (e.defaultPrevented || (form.getAttribute('method') || 'get').toLowerCase() !== 'get' || form.target || form.hasAttribute('data-no-soft')) return;
            var url;
            try { url = new URL(form.getAttribute('action') || location.href, location.href); } catch (err) { return; }
            url.search = new URLSearchParams(new FormData(form)).toString();
            url.hash = '';
            if (!plain(url)) return;
            e.preventDefault();
            go(url.href, true).then(function () {
                form._sent = false;
                $$('.is-busy', form).forEach(function (b) { b.classList.remove('is-busy'); b.removeAttribute('aria-disabled'); });
            });
        });
        window.addEventListener('popstate', function (e) {
            if (location.pathname + location.search === here) return;
            go(location.href, false, e.state && e.state.y ? e.state.y : 0);
        });
    })();

    body.classList.add('is-ready', 'app-ready');
})();
