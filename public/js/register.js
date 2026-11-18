/* The registration form of the website: searchable province and city, the address checked while it is typed,
   the password measured, the choice of plan shown at the side. The form works without this file too. */
(function () {
    'use strict';
    var form = document.getElementById('reg-form');
    if (!form) { return; }
    var t = function (key) { return form.getAttribute('data-t-' + key) || ''; };
    var byId = function (id) { return document.getElementById(id); };

    /* ---- the plan at the side ---- */
    function showPlan() {
        var radio = form.querySelector('input[name="plan"]:checked');
        if (!radio) { return; }
        byId('sidePlan').textContent = radio.getAttribute('data-name');
        byId('sideFirst').textContent = radio.getAttribute('data-first');
        byId('sideRenewal').textContent = radio.getAttribute('data-renewal');
    }
    Array.prototype.forEach.call(form.querySelectorAll('input[name="plan"]'), function (radio) { radio.addEventListener('change', showPlan); });
    showPlan();

    /* ---- the address: suggested from the name, cleaned, and checked ---- */
    var slug = byId('slug'), state = byId('slugState'), name = byId('name'), touched = slug.value !== '', timer = null, asked = 0;
    function clean(value) {
        return value.toLowerCase().replace(/[^a-z0-9-]+/g, '-').replace(/-{2,}/g, '-').replace(/^[^a-z]+/, '').slice(0, 40);
    }
    function setState(el, text, kind) {
        el.textContent = text;
        el.classList.toggle('is-good', kind === 'good');
        el.classList.toggle('is-bad', kind === 'bad');
    }
    function showAddress() {
        byId('sideAddress').textContent = slug.value ? slug.value + '.' + form.getAttribute('data-domain') : '—';
    }
    function check() {
        showAddress();
        clearTimeout(timer);
        slug.classList.remove('is-invalid');
        if (slug.value === '') { setState(state, '', ''); return; }
        setState(state, t('checking'), '');
        var mine = ++asked;
        timer = setTimeout(function () {
            fetch(form.getAttribute('data-check') + '?slug=' + encodeURIComponent(slug.value), { headers: { Accept: 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (mine !== asked) { return; }
                    if (!data) { setState(state, '', ''); return; }
                    setState(state, data.message, data.free ? 'good' : 'bad');
                    slug.classList.toggle('is-invalid', !data.free);
                })
                .catch(function () { if (mine === asked) { setState(state, '', ''); } });
        }, 350);
    }
    slug.addEventListener('input', function () {
        touched = true;
        var fixed = clean(slug.value);
        if (fixed !== slug.value) { slug.value = fixed; }
        check();
    });
    name.addEventListener('input', function () {
        if (touched) { return; }
        var guess = clean(name.value.trim()).replace(/-+$/, '');
        if (guess.length >= 3 || slug.value !== '') { slug.value = guess; check(); }
    });
    showAddress();
    if (slug.value !== '' && !state.textContent.trim()) { check(); }

    /* ---- the password: how strong, and whether the two are the same ---- */
    var pass = byId('password'), again = byId('password_confirmation'), bar = byId('passBar'), passState = byId('passState'), matchState = byId('matchState');
    var hint = passState.textContent;
    function measure() {
        var v = pass.value, score = 0;
        if (v.length >= 8) { score++; }
        if (v.length >= 12) { score++; }
        if (/[0-9]/.test(v) && /[^0-9]/.test(v)) { score++; }
        if (/[^A-Za-z0-9]/.test(v) || (/[a-z]/.test(v) && /[A-Z]/.test(v))) { score++; }
        if (v === '') { bar.style.width = '0'; setState(passState, hint, ''); return; }
        if (v.length < 8) { bar.style.width = '20%'; bar.style.background = '#dc3545'; setState(passState, t('weak'), 'bad'); return; }
        bar.style.width = score >= 3 ? '100%' : '60%';
        bar.style.background = score >= 3 ? '#047857' : '#d97706';
        setState(passState, score >= 3 ? t('strong') : t('fair'), 'good');
    }
    function match() {
        if (again.value === '') { setState(matchState, '', ''); again.classList.remove('is-invalid'); return true; }
        var same = again.value === pass.value;
        setState(matchState, same ? t('match') : t('nomatch'), same ? 'good' : 'bad');
        again.classList.toggle('is-invalid', !same);
        return same;
    }
    pass.addEventListener('input', function () { pass.classList.remove('is-invalid'); measure(); match(); });
    again.addEventListener('input', match);
    Array.prototype.forEach.call(form.querySelectorAll('[data-eye]'), function (button) {
        button.addEventListener('click', function () {
            var box = byId(button.getAttribute('data-eye')), hidden = box.type === 'password';
            box.type = hidden ? 'text' : 'password';
            button.textContent = hidden ? t('hide') : t('show');
        });
    });

    /* ---- what is missing is said beside its box, in the language of the page ---- */
    function complain(box, text) {
        box.classList.add('is-invalid');
        var holder = box.closest('.col-12') || box.parentNode, note = holder.querySelector('.invalid-feedback');
        if (note) { note.textContent = text; }
    }
    Array.prototype.forEach.call(form.querySelectorAll('input[required]'), function (box) {
        box.addEventListener('input', function () { if (box !== slug && box !== again) { box.classList.remove('is-invalid'); } });
    });
    form.addEventListener('submit', function (event) {
        var first = null;
        Array.prototype.forEach.call(form.querySelectorAll('input[required]'), function (box) {
            var bad = box.value.trim() === '' || (box.type === 'email' && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(box.value.trim()));
            if (bad) {
                if (box === slug) { setState(state, t('required'), 'bad'); slug.classList.add('is-invalid'); }
                else if (box === pass) { setState(passState, t('required'), 'bad'); pass.classList.add('is-invalid'); }
                else if (box === again) { setState(matchState, t('required'), 'bad'); again.classList.add('is-invalid'); }
                else { complain(box, box.type === 'email' && box.value.trim() !== '' ? t('email') : t('required')); }
                first = first || box;
            }
        });
        if (!first && pass.value.length < 8) { measure(); pass.classList.add('is-invalid'); first = pass; }
        if (!first && !match()) { first = again; }
        if (!first && slug.classList.contains('is-invalid')) { first = slug; }
        if (first) {
            event.preventDefault();
            first.focus();
            first.scrollIntoView({ block: 'center', behavior: 'smooth' });
            return;
        }
        var go = byId('reg-go');
        go.disabled = true;
        go.textContent = t('wait');
    });

    /* ---- province and city: select2, the cities of the chosen province only ---- */
    function dress() {
        var $ = window.jQuery;
        if (!$ || !$.fn || !$.fn.select2) { return; }
        var places = {};
        try { places = JSON.parse(byId('reg-places').textContent || '{}'); } catch (e) { places = {}; }
        var dir = form.getAttribute('data-dir'), lang = form.getAttribute('data-lang');
        var $province = $('#province'), $city = $('#city'), old = $city.attr('data-old') || $city.val() || '';

        // a place is found by its name in any of the languages
        function matcher(params, data) {
            var term = $.trim(params.term || '').toLowerCase();
            if (term === '') { return data; }
            var hay = ((data.text || '') + ' ' + (data.alt || (data.element && data.element.getAttribute('data-alt')) || '')).toLowerCase();
            return hay.indexOf(term) > -1 ? data : null;
        }
        var common = { width: '100%', dir: dir, language: lang, allowClear: true, dropdownCssClass: 'reg-drop', matcher: matcher };

        $province.select2($.extend({}, common, { placeholder: t('province') }));

        function cities(keep) {
            var list = places[$province.val()] || [];
            if ($city.hasClass('select2-hidden-accessible')) { $city.select2('destroy'); }
            $city.empty().append(new Option('', '', false, false));
            var found = false;
            list.forEach(function (city) { if (city.id === keep) { found = true; } });
            if (keep && !found && $province.val()) { list = list.concat([{ id: keep, text: keep }]); }
            $city.select2($.extend({}, common, {
                data: list, tags: true, placeholder: $province.val() ? t('city') : t('city-first'),
                createTag: function (params) {
                    var term = $.trim(params.term);
                    return term === '' || term.length > 100 ? null : { id: term, text: term };
                }
            }));
            $city.prop('disabled', !$province.val());
            $city.val(keep && $province.val() ? keep : '').trigger('change.select2');
        }
        cities(old);
        $province.on('change', function () { cities(''); });
        // the search box is ready to type in as soon as a list opens
        $(document).on('select2:open', function () {
            var field = document.querySelector('.select2-container--open .select2-search__field');
            if (field) { field.focus(); }
        });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', dress); } else { dress(); }
})();
