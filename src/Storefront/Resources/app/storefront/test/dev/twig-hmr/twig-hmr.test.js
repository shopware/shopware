import { init, refresh, reset } from 'src/dev/twig-hmr/twig-hmr';
import { NOTICE_ID } from 'src/dev/twig-hmr/notice';

/**
 * @sw-package framework
 */
describe('dev/twig-hmr', () => {
    let pages;

    function page(body, title = 'Shop', head = '') {
        return `<!DOCTYPE html><html><head><title>${title}</title>${head}</head><body>${body}</body></html>`;
    }

    function respond(url, { status = 200, html = '', redirectedTo = null, contentType = 'text/html; charset=UTF-8' } = {}) {
        return Promise.resolve({
            ok: status < 400,
            status,
            redirected: redirectedTo !== null,
            url: redirectedTo ?? url,
            headers: { get: (name) => (name.toLowerCase() === 'content-type' ? contentType : null) },
            text: () => Promise.resolve(html),
        });
    }

    function setup(body) {
        document.body.innerHTML = body;
        init();
    }

    function userInput(control, type = 'input') {
        control.dispatchEvent(new Event(type, { bubbles: true }));
    }

    function notice() {
        return document.getElementById(NOTICE_ID);
    }

    beforeAll(() => {
        // jsdom has no CSS.escape, which idiomorph uses to move elements with an id.
        global.CSS ??= { escape: (value) => value };
    });

    beforeEach(() => {
        pages = {};
        global.fetch = jest.fn((url) => respond(url, pages[url] ?? { status: 404 }));
        jest.spyOn(console, 'info').mockImplementation(() => {});
        jest.spyOn(console, 'warn').mockImplementation(() => {});
        jest.spyOn(console, 'error').mockImplementation(() => {});
        window.PluginManager.initializePlugins = jest.fn(() => Promise.resolve());
        document.title = 'Shop';
        document.head.innerHTML = '';
        document.body.innerHTML = '';
        document.body.className = '';
    });

    afterEach(() => {
        reset();
        delete window.PluginManager.getPluginList;
        jest.restoreAllMocks();
    });

    it('updates an open modal and keeps its runtime state', async () => {
        setup('<div class="modal fade" id="demo" aria-hidden="true"><p class="text">Version 1</p></div>');
        const modal = document.getElementById('demo');
        // Bootstrap opens the modal.
        modal.classList.add('show');
        modal.style.display = 'block';
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('role', 'dialog');
        modal.removeAttribute('aria-hidden');
        document.body.insertAdjacentHTML('beforeend', '<div class="modal-backdrop fade show"></div>');
        document.body.classList.add('modal-open');
        pages['http://localhost/'] = {
            html: page('<div class="modal fade" id="demo" aria-hidden="true"><p class="text">Version 2</p></div>', 'New title'),
        };

        await refresh(['modal-body.html.twig']);

        expect(modal.querySelector('.text').textContent).toBe('Version 2');
        expect(modal.classList.contains('show')).toBe(true);
        expect(modal.style.display).toBe('block');
        expect(modal.getAttribute('aria-modal')).toBe('true');
        expect(modal.getAttribute('role')).toBe('dialog');
        expect(modal.hasAttribute('aria-hidden')).toBe(false);
        expect(document.querySelector('.modal-backdrop')).not.toBeNull();
        expect(document.body.classList.contains('modal-open')).toBe(true);
        expect(document.querySelector('body body')).toBeNull();
        expect(document.title).toBe('New title');
        expect(window.PluginManager.initializePlugins).toHaveBeenCalledTimes(1);
        expect(notice()).toBeNull();
    });

    it('keeps the inert background of an open offcanvas', async () => {
        setup('<main><p class="text">Version 1</p></main><footer>Footer</footer>');
        // The offcanvas plugin appends the offcanvas and disables the background.
        document.body.insertAdjacentHTML('beforeend', '<div class="offcanvas offcanvas-end js-offcanvas-singleton show">Cart</div>');
        ['main', 'footer'].forEach((selector) => {
            const element = document.querySelector(selector);
            element.setAttribute('inert', '');
            element.setAttribute('aria-hidden', 'true');
        });
        pages['http://localhost/'] = { html: page('<main><p class="text">Version 2</p></main><footer>Footer</footer>') };

        await refresh(['page.html.twig']);

        const main = document.querySelector('main');
        expect(main.querySelector('.text').textContent).toBe('Version 2');
        expect(main.hasAttribute('inert')).toBe(true);
        expect(main.getAttribute('aria-hidden')).toBe('true');
        expect(document.querySelector('footer').getAttribute('aria-hidden')).toBe('true');
        expect(document.querySelector('footer').hasAttribute('inert')).toBe(true);
        expect(document.querySelector('.js-offcanvas-singleton').classList.contains('show')).toBe(true);
    });

    it('keeps the tab that the user activated', async () => {
        const tabs = (one, two) => `
            <ul class="nav nav-tabs" role="tablist">
                <li><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#one" aria-selected="true">One</button></li>
                <li><button class="nav-link" data-bs-toggle="tab" data-bs-target="#two" aria-selected="false" tabindex="-1">Two</button></li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="one">${one}</div>
                <div class="tab-pane fade" id="two">${two}</div>
            </div>
        `;
        setup(tabs('One 1', 'Two 1'));
        const [first, second] = document.querySelectorAll('.nav-link');
        // Bootstrap shows the second tab.
        first.classList.remove('active');
        first.setAttribute('aria-selected', 'false');
        first.setAttribute('tabindex', '-1');
        second.classList.add('active');
        second.setAttribute('aria-selected', 'true');
        second.removeAttribute('tabindex');
        document.getElementById('one').classList.remove('show', 'active');
        document.getElementById('two').classList.add('show', 'active');
        second.dispatchEvent(new Event('shown.bs.tab', { bubbles: true }));
        pages['http://localhost/'] = { html: page(tabs('One 2', 'Two 2')) };

        await refresh(['tabs.html.twig']);

        expect(second.classList.contains('active')).toBe(true);
        expect(second.getAttribute('aria-selected')).toBe('true');
        expect(second.hasAttribute('tabindex')).toBe(false);
        expect(first.classList.contains('active')).toBe(false);
        expect(first.getAttribute('aria-selected')).toBe('false');
        expect([...document.getElementById('two').classList].sort()).toEqual(['active', 'fade', 'show', 'tab-pane']);
        expect(document.getElementById('two').textContent).toBe('Two 2');
        expect([...document.getElementById('one').classList].sort()).toEqual(['fade', 'tab-pane']);
    });

    it('keeps the collapse elements and checkboxes that the user changed', async () => {
        setup(`
            <main>
                <button class="btn collapsed" data-bs-toggle="collapse" data-bs-target="#filter" aria-expanded="false">Filter</button>
                <div class="collapse" id="filter"><input type="checkbox" class="option"> Old</div>
            </main>
        `);
        const filter = document.getElementById('filter');
        const toggle = document.querySelector('[data-bs-toggle]');
        const checkbox = document.querySelector('.option');
        // Bootstrap opens the collapse element, then the user checks the option.
        filter.classList.add('show');
        toggle.classList.remove('collapsed');
        toggle.setAttribute('aria-expanded', 'true');
        filter.dispatchEvent(new Event('shown.bs.collapse', { bubbles: true }));
        checkbox.checked = true;
        userInput(checkbox, 'change');
        pages['http://localhost/'] = {
            html: page(`
                <main>
                    <button class="btn btn-primary collapsed" data-bs-toggle="collapse" data-bs-target="#filter" aria-expanded="false">Filter</button>
                    <div class="collapse" id="filter"><input type="checkbox" class="option"> New</div>
                </main>
            `),
        };

        await refresh(['filter.html.twig']);

        expect(toggle.classList.contains('btn-primary')).toBe(true);
        expect(toggle.classList.contains('collapsed')).toBe(false);
        expect(toggle.getAttribute('aria-expanded')).toBe('true');
        expect(filter.classList.contains('show')).toBe(true);
        expect(filter.textContent).toContain('New');
        expect(document.querySelector('.option')).toBe(checkbox);
        expect(checkbox.checked).toBe(true);
    });

    it('applies the template to hidden inputs and controls that the user did not change', async () => {
        setup(`
            <form id="buy">
                <input type="hidden" name="lineItemId" value="old-id">
                <input type="text" name="comment" value="old default">
                <input type="checkbox" name="gift">
                <select name="size"><option value="s" selected>S</option><option value="m">M</option></select>
                <div class="collapse show" id="details">Details</div>
            </form>
        `);
        pages['http://localhost/'] = {
            html: page(`
                <form id="buy">
                    <input type="hidden" name="lineItemId" value="new-id">
                    <input type="text" name="comment" value="new default">
                    <input type="checkbox" name="gift" checked>
                    <select name="size"><option value="s">S</option><option value="m" selected>M</option></select>
                    <div class="collapse" id="details">Details</div>
                </form>
            `),
        };

        await refresh(['buy-form.html.twig']);

        const form = document.getElementById('buy');
        expect(form.elements.lineItemId.value).toBe('new-id');
        expect(form.elements.comment.value).toBe('new default');
        expect(form.elements.gift.checked).toBe(true);
        expect(form.elements.size.value).toBe('m');
        expect(document.getElementById('details').classList.contains('show')).toBe(false);
    });

    it('keeps the values that the user typed or selected', async () => {
        setup(`
            <form id="buy">
                <input type="hidden" name="token" value="1">
                <input type="text" name="comment" value="">
                <select name="size"><option value="s" selected>S</option><option value="m">M</option></select>
            </form>
        `);
        const form = document.getElementById('buy');
        form.elements.comment.value = 'typed';
        userInput(form.elements.comment);
        form.elements.size.value = 'm';
        userInput(form.elements.size, 'change');
        form.elements.token.value = '2';
        userInput(form.elements.token, 'change');
        pages['http://localhost/'] = {
            html: page(`
                <form id="buy">
                    <input type="hidden" name="token" value="3">
                    <input type="text" name="comment" value="">
                    <select name="size"><option value="s" selected>Small</option><option value="m">Medium</option></select>
                </form>
            `),
        };

        await refresh(['buy-form.html.twig']);

        expect(form.elements.comment.value).toBe('typed');
        expect(form.elements.size.value).toBe('m');
        expect(form.elements.size.options[1].textContent).toBe('Medium');
        expect(form.elements.token.value).toBe('3');
    });

    it('restores the value of an edited control that the template replaced', async () => {
        setup('<form id="register"><div class="field"><input type="email" name="email"></div></form>');
        const input = document.querySelector('[name="email"]');
        input.value = 'mail@example.com';
        userInput(input);
        pages['http://localhost/'] = {
            html: page('<form id="register"><section class="field"><input type="email" name="email" class="form-control"></section></form>'),
        };

        await refresh(['register.html.twig']);

        const replaced = document.querySelector('[name="email"]');
        expect(replaced).not.toBe(input);
        expect(replaced.classList.contains('form-control')).toBe(true);
        expect(replaced.value).toBe('mail@example.com');
    });

    it.each([
        ['a multi select', '<select name="sizes" multiple><option>S</option><option>M</option><option>L</option></select>', (select) => {
            select.options[1].selected = true;
            select.options[2].selected = true;
        }, (select) => [...select.selectedOptions].map((option) => option.textContent), ['M', 'L']],
        ['a checkbox', '<input type="checkbox" name="newsletter" value="1">', (checkbox) => {
            checkbox.checked = true;
        }, (checkbox) => checkbox.checked, true],
    ])('restores %s that the template replaced', async (name, control, edit, read, expected) => {
        setup(`<form id="register"><div class="field">${control}</div></form>`);
        const element = document.querySelector('[name]');
        edit(element);
        userInput(element, 'change');
        pages['http://localhost/'] = { html: page(`<form id="register"><section class="field">${control}</section></form>`) };

        await refresh(['register.html.twig']);

        const replaced = document.querySelector('[name]');
        expect(replaced).not.toBe(element);
        expect(read(replaced)).toEqual(expected);
    });

    it.each([
        ['replaced', 'div', 'section'],
        ['kept', 'div', 'div'],
    ])('restores the selected options of a %s select by value after the template reordered them', async (name, before, after) => {
        const select = (wrapper, options) => `<form id="buy"><${wrapper} class="field"><select name="sizes" multiple>${options}</select></${wrapper}></form>`;
        setup(select(before, '<option value="s">S</option><option value="m">M</option><option value="l">L</option>'));
        const element = document.querySelector('select');
        element.options[1].selected = true;
        element.options[2].selected = true;
        userInput(element, 'change');
        pages['http://localhost/'] = {
            html: page(select(after, '<option value="l">L</option><option value="m">M</option><option value="s">S</option>')),
        };

        await refresh(['buy-form.html.twig']);

        const current = document.querySelector('select');
        expect([...current.options].map((option) => option.value)).toEqual(['l', 'm', 's']);
        expect([...current.selectedOptions].map((option) => option.value).sort()).toEqual(['l', 'm']);
    });

    it.each([
        ['inside a form', (radios) => `<form id="checkout">${radios}</form>`],
        ['without a form', (radios) => `<div>${radios}</div>`],
    ])('keeps the radio button that the user picked %s', async (name, wrap) => {
        const radios = (checked) => ['standard', 'express']
            .map((value) => `<input type="radio" name="shipping" value="${value}"${value === checked ? ' checked' : ''}>`)
            .join('');
        setup(wrap(radios('standard')));
        const [standard, express] = document.querySelectorAll('[name="shipping"]');
        express.checked = true;
        userInput(express, 'change');
        pages['http://localhost/'] = { html: page(wrap(radios('standard'))) };

        await refresh(['shipping.html.twig']);

        expect(standard.checked).toBe(false);
        expect(express.checked).toBe(true);
    });

    it('keeps an open dropdown and drops one that the template removed', async () => {
        const dropdown = (id, item) => `
            <div class="dropdown" id="${id}">
                <button type="button" data-bs-toggle="dropdown" aria-expanded="false">Menu</button>
                <ul class="dropdown-menu"><li>${item}</li></ul>
            </div>
        `;
        setup(dropdown('account', 'Item 1') + dropdown('language', 'Item 1'));
        // Bootstrap opens both dropdowns.
        document.querySelectorAll('.dropdown').forEach((element) => {
            element.querySelector('[data-bs-toggle]').classList.add('show');
            element.querySelector('[data-bs-toggle]').setAttribute('aria-expanded', 'true');
            const menu = element.querySelector('.dropdown-menu');
            menu.classList.add('show');
            menu.setAttribute('style', 'position: absolute; inset: 0px auto auto 0px;');
            menu.setAttribute('data-popper-placement', 'bottom-start');
        });
        pages['http://localhost/'] = { html: page(dropdown('account', 'Item 2')) };

        await refresh(['header.html.twig']);

        const toggle = document.querySelector('#account [data-bs-toggle]');
        const menu = document.querySelector('#account .dropdown-menu');
        expect(menu.textContent).toBe('Item 2');
        expect(menu.classList.contains('show')).toBe(true);
        expect(menu.getAttribute('style')).toBe('position: absolute; inset: 0px auto auto 0px;');
        expect(menu.getAttribute('data-popper-placement')).toBe('bottom-start');
        expect(toggle.classList.contains('show')).toBe(true);
        expect(toggle.getAttribute('aria-expanded')).toBe('true');
        expect(document.getElementById('language')).toBeNull();
    });

    it('activates the replaced trigger of a tab that the user activated', async () => {
        const panes = '<div class="tab-content"><div class="tab-pane active" id="one">One</div><div class="tab-pane" id="two">Two</div></div>';
        setup(`
            <div class="nav">
                <a class="nav-link active" data-bs-toggle="tab" href="#one">One</a>
                <a class="nav-link" data-bs-toggle="tab" href="#two">Two</a>
            </div>
            ${panes}
        `);
        const second = document.querySelector('[href="#two"]');
        second.classList.add('active');
        document.querySelector('[href="#one"]').classList.remove('active');
        second.dispatchEvent(new Event('shown.bs.tab', { bubbles: true }));
        pages['http://localhost/'] = {
            html: page(`
                <div class="nav">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#one">One</button>
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#two">Two</button>
                </div>
                ${panes}
            `),
        };

        await refresh(['tabs.html.twig']);

        expect(second.isConnected).toBe(false);
        expect(document.querySelector('[data-bs-target="#two"]').classList.contains('active')).toBe(true);
        expect(document.querySelector('[data-bs-target="#one"]').classList.contains('active')).toBe(false);
        expect(document.getElementById('two').classList.contains('active')).toBe(true);
        expect(document.getElementById('two').classList.contains('show')).toBe(false);
    });

    it('keeps a replaced tab trigger and a replaced collapse element on the next refresh', async () => {
        const markup = (tag, version) => `
            <div class="nav">
                <${tag} class="nav-link active" data-bs-toggle="tab" data-bs-target="#one">One</${tag}>
                <${tag} class="nav-link" data-bs-toggle="tab" data-bs-target="#two">Two</${tag}>
            </div>
            <div class="tab-content"><div class="tab-pane active" id="one">One</div><div class="tab-pane" id="two">Two ${version}</div></div>
            <${tag} class="collapse" id="filter">Filter</${tag}>
        `;
        setup(markup('a', 1));
        const [first, second] = document.querySelectorAll('.nav-link');
        first.classList.remove('active');
        second.classList.add('active');
        second.dispatchEvent(new Event('shown.bs.tab', { bubbles: true }));
        const collapse = document.getElementById('filter');
        collapse.classList.add('show');
        collapse.dispatchEvent(new Event('shown.bs.collapse', { bubbles: true }));

        pages['http://localhost/'] = { html: page(markup('button', 2)) };
        await refresh(['tabs.html.twig']);
        pages['http://localhost/'] = { html: page(markup('button', 3)) };
        await refresh(['tabs.html.twig']);

        expect(document.getElementById('two').textContent).toBe('Two 3');
        expect(document.querySelector('[data-bs-target="#two"]').classList.contains('active')).toBe(true);
        expect(document.querySelector('[data-bs-target="#one"]').classList.contains('active')).toBe(false);
        expect(document.getElementById('two').classList.contains('active')).toBe(true);
        expect(document.getElementById('filter').localName).toBe('button');
        expect(document.getElementById('filter').classList.contains('show')).toBe(true);
    });

    it.each([
        ['the template removed its pane', '#two', '<div class="tab-content"><div class="tab-pane active" id="one">One</div></div>'],
        ['its target is no valid selector', '#2', '<div class="tab-content"><div class="tab-pane active" id="one">One</div><div class="tab-pane" id="2">Two</div></div>'],
        ['its target is a URL', '/reviews', '<div class="tab-content"><div class="tab-pane active" id="one">One</div></div>'],
    ])('leaves the tabs to the template when %s', async (name, target, nextPanes) => {
        const tabs = (panes) => `
            <div class="nav">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#one">One</button>
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="${target}">Two</button>
            </div>
            ${panes}
        `;
        setup(tabs('<div class="tab-content"><div class="tab-pane active" id="one">One</div></div>'));
        const [first, second] = document.querySelectorAll('.nav-link');
        first.classList.remove('active');
        second.classList.add('active');
        second.dispatchEvent(new Event('shown.bs.tab', { bubbles: true }));
        pages['http://localhost/'] = { html: page(tabs(nextPanes)) };

        await refresh(['tabs.html.twig']);

        expect(first.classList.contains('active')).toBe(true);
        expect(second.classList.contains('active')).toBe(false);
        expect(document.getElementById('one').classList.contains('active')).toBe(true);
    });

    it('restores collapse elements by id and skips the ones without a match', async () => {
        setup(`
            <main>
                <div class="collapse" id="filter"><span>Filter</span></div>
                <div class="collapse no-id"><span>Details</span></div>
                <div class="collapse removed"><span>Removed</span></div>
            </main>
        `);
        document.querySelectorAll('.collapse').forEach((collapse) => {
            collapse.classList.add('show');
            collapse.dispatchEvent(new Event('shown.bs.collapse', { bubbles: true }));
        });
        pages['http://localhost/'] = {
            html: page(`
                <main>
                    <section class="collapse" id="filter"><span>Filter</span></section>
                    <div class="collapse no-id"><span>Details</span></div>
                </main>
            `),
        };

        await refresh(['filter.html.twig']);

        expect(document.getElementById('filter').localName).toBe('section');
        expect(document.getElementById('filter').classList.contains('show')).toBe(true);
        expect(document.querySelector('.no-id').classList.contains('show')).toBe(true);
        expect(document.querySelector('.removed')).toBeNull();
    });

    it('keeps the page when the template replaces an open modal or its inert background', async () => {
        setup('<main><div class="modal" id="demo">Version 1</div></main><footer>Footer</footer>');
        const modal = document.getElementById('demo');
        const footer = document.querySelector('footer');
        modal.classList.add('show');
        footer.setAttribute('inert', '');
        pages['http://localhost/'] = { html: page('<main><div class="modal" id="renamed">Version 2</div></main><aside>Footer</aside>') };

        await refresh(['modal.html.twig']);

        expect(modal.isConnected).toBe(true);
        expect(modal.textContent).toBe('Version 1');
        expect(footer.isConnected).toBe(true);
        expect(notice().textContent).toContain('The template removes <div#demo.modal.show>, which is open.');
        expect(notice().textContent).toContain('The template removes <footer>, which belongs to the inert background of an open overlay.');
        expect(notice().textContent).toContain('The page of http://localhost/ keeps its current content.');
    });

    it('keeps the page when the template adds a background element while an offcanvas is open', async () => {
        setup('<main><p class="text">Version 1</p></main>');
        document.body.insertAdjacentHTML('beforeend', '<div class="offcanvas js-offcanvas-singleton show">Cart</div>');
        document.querySelector('main').setAttribute('inert', '');
        pages['http://localhost/'] = { html: page('<main><p class="text">Version 2</p></main><footer>New footer</footer>') };

        await refresh(['footer.html.twig']);

        expect(document.querySelector('.text').textContent).toBe('Version 1');
        expect(document.querySelector('footer')).toBeNull();
        expect(notice().textContent).toContain('The template adds <footer> while an open overlay makes the rest of the page inert.');
    });

    it.each([
        ['a server ancestor', '<main><div class="wrapper"><p class="text">Version 1</p></div></main>', '<main><p class="text">Version 2</p></main>'],
        ['an ancestor that idiomorph moves to its pantry', '<main><div class="wrapper"><p id="text">Version 1</p></div></main>', '<main><p id="text">Version 2</p></main>'],
    ])('keeps the page when the template removes %s of an element that a script added', async (name, body, next) => {
        setup(body);
        document.querySelector('.wrapper').insertAdjacentHTML('beforeend', '<span class="badge">Added by a plugin</span>');
        pages['http://localhost/'] = { html: page(next) };

        await refresh(['page.html.twig']);

        expect(document.querySelector('main p').textContent).toBe('Version 1');
        expect(document.querySelector('.wrapper .badge')).not.toBeNull();
        expect(notice().textContent).toContain('The template removes <span.badge>, which a script added.');
    });

    it('applies a later update after a rejected one against the shown markup', async () => {
        setup('<main><div class="wrapper"><p class="text">Version 1</p></div></main>');
        document.querySelector('.wrapper').insertAdjacentHTML('beforeend', '<span class="badge">Added by a plugin</span>');
        pages['http://localhost/'] = { html: page('<main><p class="text">Version 2</p></main>') };
        await refresh(['page.html.twig']);

        pages['http://localhost/'] = { html: page('<main><div class="wrapper"><p class="text">Version 3</p></div></main>') };
        await refresh(['page.html.twig']);

        expect(document.querySelector('.text').textContent).toBe('Version 3');
        expect(document.querySelector('.wrapper .badge')).not.toBeNull();
    });

    it('asks for a reload when new markup matched an element that a script added', async () => {
        setup('<main><p class="text">Version 1</p></main>');
        document.querySelector('main').insertAdjacentHTML('beforeend', '<div id="live-region">Added by a plugin</div>');
        pages['http://localhost/'] = { html: page('<main><p class="text">Version 2</p><div id="live-region">Server</div></main>') };

        await refresh(['page.html.twig']);

        expect(document.getElementById('live-region').textContent).toBe('Added by a plugin');
        expect(notice().textContent).toContain('which a script added');
    });

    it('ignores plugin registrations without a usable selector', async () => {
        window.PluginManager.getPluginList = jest.fn(() => ({
            WithoutRegistrations: new Map(),
            InvalidSelector: new Map([['registrations', new Map([['id', { selector: '[data-broken' }]])]]),
        }));
        setup('<main><p class="text">Version 1</p></main>');
        pages['http://localhost/'] = { html: page('<main><p class="text">Version 2</p></main>') };

        await refresh(['page.html.twig']);

        expect(document.querySelector('.text').textContent).toBe('Version 2');
        expect(notice()).toBeNull();
    });

    it('keeps the focus and the selection', async () => {
        setup('<main><label>Old</label><input type="text" class="search"></main>');
        const input = document.querySelector('.search');
        input.value = 'typed';
        userInput(input);
        input.focus();
        input.setSelectionRange(2, 3);
        pages['http://localhost/'] = {
            html: page('<main><label>New</label><input type="text" class="search" value=""></main>'),
        };

        await refresh(['search.html.twig']);

        expect(document.querySelector('label').textContent).toBe('New');
        expect(document.querySelector('.search')).toBe(input);
        expect(input.value).toBe('typed');
        expect(document.activeElement).toBe(input);
        expect([input.selectionStart, input.selectionEnd]).toEqual([2, 3]);
    });

    describe('when the update moved the focus away', () => {
        function blurDuringRefresh(element) {
            // Browsers without `moveBefore()` drop the focus when idiomorph moves an element without an id.
            window.PluginManager.initializePlugins = jest.fn(() => {
                element.blur();

                return Promise.resolve();
            });
        }

        it('focuses the input again and restores its selection', async () => {
            setup('<main><input type="text" class="search"></main>');
            const input = document.querySelector('.search');
            input.value = 'typed';
            userInput(input);
            input.focus();
            input.setSelectionRange(1, 3);
            pages['http://localhost/'] = { html: page('<main><input type="text" class="search"></main>') };
            blurDuringRefresh(input);

            await refresh(['search.html.twig']);

            expect(document.activeElement).toBe(input);
            expect([input.selectionStart, input.selectionEnd]).toEqual([1, 3]);
        });

        it('focuses an element without a selection again', async () => {
            setup('<main><button class="btn">Buy</button></main>');
            const button = document.querySelector('.btn');
            button.focus();
            pages['http://localhost/'] = { html: page('<main><button class="btn">Buy now</button></main>') };
            blurDuringRefresh(button);

            await refresh(['buy-widget.html.twig']);

            expect(button.textContent).toBe('Buy now');
            expect(document.activeElement).toBe(button);
        });
    });

    function openAjaxModal(url, content) {
        document.body.insertAdjacentHTML('beforeend', `
            <div class="js-pseudo-modal">
                <div class="modal show" data-sw-source-url="${url}">${content}</div>
            </div>
        `);
    }

    it('re-renders the content of an open AJAX modal', async () => {
        setup('<main>Page</main>');
        pages['http://localhost/'] = { html: page('<main>Page</main>') };
        pages['/widgets/cms/abc'] = { html: '<p class="part">Version 1</p>' };
        openAjaxModal('/widgets/cms/abc', '<div class="js-pseudo-modal-template-content-element"><p class="part">Version 1</p></div>');
        pages['/widgets/cms/abc'] = { html: '<p class="part">Version 2</p>' };

        await refresh(['ajax-modal-part.html.twig']);

        expect(global.fetch).toHaveBeenCalledWith('/widgets/cms/abc', expect.objectContaining({
            headers: expect.objectContaining({ 'X-Requested-With': 'XMLHttpRequest', 'sw-twig-hmr': '1' }),
        }));
        expect(document.querySelector('.js-pseudo-modal .part').textContent).toBe('Version 2');
        expect(document.querySelector('.js-pseudo-modal .modal').classList.contains('show')).toBe(true);
    });

    it('re-renders the content of an open AJAX offcanvas', async () => {
        setup('<main>Page</main>');
        pages['http://localhost/'] = { html: page('<main>Page</main>') };
        document.body.insertAdjacentHTML('beforeend', `
            <div class="offcanvas offcanvas-end js-offcanvas-singleton show" data-sw-source-url="/checkout/offcanvas" style="visibility: visible;">
                <div class="offcanvas-body"><p class="part">Version 1</p></div>
            </div>
        `);
        pages['/checkout/offcanvas'] = { html: '<div class="offcanvas-body"><p class="part">Version 2</p></div>' };

        await refresh(['offcanvas-cart.html.twig']);

        const offCanvas = document.querySelector('.js-offcanvas-singleton');
        expect(global.fetch).toHaveBeenCalledWith('/checkout/offcanvas', expect.objectContaining({
            headers: expect.objectContaining({ 'X-Requested-With': 'XMLHttpRequest' }),
        }));
        expect(offCanvas.querySelector('.part').textContent).toBe('Version 2');
        expect(offCanvas.classList.contains('show')).toBe(true);
        expect(offCanvas.style.visibility).toBe('visible');
    });

    it('morphs the root element of an open AJAX modal', async () => {
        setup('');
        pages['http://localhost/'] = { html: page('') };
        openAjaxModal('/widgets/quickview', '<div class="js-pseudo-modal-template-root-element"><p class="part">Version 1</p></div>');
        pages['/widgets/quickview'] = {
            html: '<div class="js-pseudo-modal-template-root-element"><p class="part">Version 2</p></div>',
        };

        await refresh(['quickview.html.twig']);

        expect(document.querySelector('.js-pseudo-modal .part').textContent).toBe('Version 2');
        expect(document.querySelectorAll('.js-pseudo-modal-template-root-element')).toHaveLength(1);
    });

    it('moves the title of an AJAX modal response into the modal title', async () => {
        setup('');
        pages['http://localhost/'] = { html: page('') };
        openAjaxModal('/widgets/cms/abc', `
            <h5 class="js-pseudo-modal-template-title-element">Title 1</h5>
            <div class="js-pseudo-modal-template-content-element"><p class="part">Version 1</p></div>
        `);
        pages['/widgets/cms/abc'] = {
            html: '<span class="js-pseudo-modal-template-title-element">Title 2</span><p class="part">Version 2</p>',
        };

        await refresh(['cms.html.twig']);

        const modal = document.querySelector('.js-pseudo-modal .modal');
        expect(modal.querySelector('.js-pseudo-modal-template-title-element').textContent).toBe('Title 2');
        expect(modal.querySelectorAll('.js-pseudo-modal-template-title-element')).toHaveLength(1);
        expect(modal.querySelector('.part').textContent).toBe('Version 2');
    });

    it('asks for a reload when an AJAX modal misses its template elements', async () => {
        setup('');
        pages['http://localhost/'] = { html: page('') };
        openAjaxModal('/widgets/without-content', '<p class="static">Static</p>');
        pages['/widgets/without-content'] = { html: '<p class="static">Changed</p>' };

        await refresh(['cms.html.twig']);

        expect(document.querySelector('.static').textContent).toBe('Static');
        expect(notice().textContent).toContain('/widgets/without-content');
        expect(console.error).not.toHaveBeenCalled();
    });

    it('leaves the page unchanged when an open fragment fails to render', async () => {
        setup('<main><input type="text" class="search"><p class="text">Version 1</p></main>');
        const input = document.querySelector('.search');
        input.focus();
        pages['http://localhost/'] = { html: page('<main><input type="text" class="search"><p class="text">Version 2</p></main>') };
        openAjaxModal('/widgets/cms/abc', '<div class="js-pseudo-modal-template-content-element"><p class="part">Version 1</p></div>');
        pages['/widgets/cms/abc'] = { status: 500, html: page('', 'Unexpected &quot;}&quot; in cms.html.twig') };

        await refresh(['cms.html.twig']);

        expect(document.querySelector('.text').textContent).toBe('Version 1');
        expect(document.querySelector('.part').textContent).toBe('Version 1');
        expect(document.activeElement).toBe(input);
        expect(window.PluginManager.initializePlugins).not.toHaveBeenCalled();
        expect(notice().textContent).toContain('Unexpected "}" in cms.html.twig');
    });

    it('rejects a redirect without changing the page', async () => {
        setup('<main><p class="text">Version 1</p></main>');
        pages['http://localhost/'] = {
            html: page('<main><p class="text">Login</p></main>'),
            redirectedTo: 'http://localhost/account/login',
        };

        await refresh(['page.html.twig']);

        expect(document.querySelector('.text').textContent).toBe('Version 1');
        expect(console.error).toHaveBeenCalledWith('[sw-twig-hmr] http://localhost/ redirected to http://localhost/account/login');
    });

    it('rejects a response that is no HTML', async () => {
        setup('<main><p class="text">Version 1</p></main>');
        pages['http://localhost/'] = { html: '{}', contentType: 'application/json' };

        await refresh(['page.html.twig']);

        expect(document.querySelector('.text').textContent).toBe('Version 1');
        expect(console.error).toHaveBeenCalledWith('[sw-twig-hmr] http://localhost/ answered with application/json instead of HTML');
    });

    it.each([
        ['closed', (modal) => modal.classList.remove('show')],
        ['loaded other content', (modal) => { modal.dataset.swSourceUrl = '/widgets/other'; }],
    ])('discards the response of a fragment that %s during the fetch', async (name, change) => {
        setup('<main><p class="text">Version 1</p></main>');
        pages['http://localhost/'] = { html: page('<main><p class="text">Version 2</p></main>') };
        openAjaxModal('/widgets/cms/abc', '<div class="js-pseudo-modal-template-content-element"><p class="part">Version 1</p></div>');
        const modal = document.querySelector('.js-pseudo-modal .modal');
        let fragmentRequests = 0;
        global.fetch = jest.fn((url) => {
            if (url === '/widgets/cms/abc') {
                fragmentRequests += 1;
                // The first request is the baseline, the second one belongs to the refresh.
                if (fragmentRequests === 2) {
                    change(modal);
                }

                return respond(url, { html: '<p class="part">Version 2</p>' });
            }

            return respond(url, pages[url] ?? { status: 404 });
        });

        await refresh(['cms.html.twig']);

        expect(document.querySelector('.text').textContent).toBe('Version 2');
        expect(modal.querySelector('.part').textContent).toBe('Version 1');
    });

    it('keeps elements that scripts added and removes the ones the template removed', async () => {
        setup(`
            <div class="server-banner">Banner</div>
            <main><p class="text">Version 1</p></main>
            <div class="toast" data-sw-twig-hmr-preserve>Rendered toast</div>
        `);
        document.body.insertAdjacentHTML('beforeend', '<div class="script-root">Appended by a plugin</div>');
        document.querySelector('main').insertAdjacentHTML('beforeend', '<span class="badge">Added by a plugin</span>');
        pages['http://localhost/'] = { html: page('<main><p class="text">Version 2</p></main>') };

        await refresh(['page.html.twig']);

        expect(document.querySelector('.text').textContent).toBe('Version 2');
        expect(document.querySelector('.server-banner')).toBeNull();
        expect(document.querySelector('[data-sw-twig-hmr-preserve]')).not.toBeNull();
        expect(document.querySelector('.script-root')).not.toBeNull();
        expect(document.querySelector('main .badge')).not.toBeNull();
    });

    it('asks for a reload when the head changed', async () => {
        document.head.innerHTML = '<meta name="description" content="Old">';
        setup('<main>Page</main>');
        pages['http://localhost/'] = { html: page('<main>Page</main>', 'Shop', '<meta name="description" content="New">') };
        const updated = jest.fn();
        document.addEventListener('sw-twig-hmr:updated', updated);

        await refresh(['meta.html.twig']);

        expect(notice().textContent).toContain('<head> changed');
        expect(updated.mock.calls[0][0].detail.reloadRequired).toEqual([
            'The <head> changed. Meta and link elements are only applied by a reload.',
        ]);
        document.removeEventListener('sw-twig-hmr:updated', updated);
    });

    it('shows a render error until the next update succeeds', async () => {
        setup('<main><p class="text">Version 1</p></main>');
        pages['http://localhost/'] = {
            status: 500,
            html: page('<h1>Error</h1>', 'Unexpected &quot;}&quot; in modal-body.html.twig (500 Internal Server Error)'),
        };

        await refresh(['modal-body.html.twig']);

        expect(document.querySelector('.text').textContent).toBe('Version 1');
        expect(window.PluginManager.initializePlugins).not.toHaveBeenCalled();
        expect(console.error).toHaveBeenCalledWith(
            '[sw-twig-hmr] http://localhost/ answered with HTTP 500: Unexpected "}" in modal-body.html.twig (500 Internal Server Error)',
        );
        expect(notice().textContent).toContain('could not be rendered');

        pages['http://localhost/'] = { html: page('<main><p class="text">Version 2</p></main>') };
        await refresh(['modal-body.html.twig']);

        expect(document.querySelector('.text').textContent).toBe('Version 2');
        expect(notice()).toBeNull();
    });

    it('logs a generic message for an error page without a title', async () => {
        setup('<main><p class="text">Version 1</p></main>');
        pages['http://localhost/'] = { status: 502, html: 'Bad Gateway' };

        await refresh(['page.html.twig']);

        expect(document.querySelector('.text').textContent).toBe('Version 1');
        expect(console.error).toHaveBeenCalledWith('[sw-twig-hmr] http://localhost/ answered with HTTP 502: server error');
    });

    it('logs errors that are no Error objects', async () => {
        setup('<main>Version 1</main>');
        pages['http://localhost/'] = { html: page('<main>Version 2</main>') };
        window.PluginManager.initializePlugins = jest.fn(() => Promise.reject('Plugin failed'));

        await refresh(['page.html.twig']);

        expect(console.error).toHaveBeenCalledWith('Plugin failed');
    });

    it('runs changes during a refresh once afterwards', async () => {
        const updated = jest.fn();
        document.addEventListener('sw-twig-hmr:updated', updated);
        setup('<main>Version 1</main>');
        pages['http://localhost/'] = { html: page('<main>Version 2</main>') };

        await Promise.all([
            refresh(['a.html.twig']),
            refresh(['b.html.twig']),
            refresh(['c.html.twig']),
        ]);
        await new Promise(process.nextTick);

        expect(global.fetch).toHaveBeenCalledTimes(2);
        expect(updated.mock.calls.map(([event]) => event.detail.files)).toEqual([
            ['a.html.twig'],
            ['b.html.twig', 'c.html.twig'],
        ]);

        document.removeEventListener('sw-twig-hmr:updated', updated);
    });

    it('works without an explicit init', async () => {
        document.body.innerHTML = '<main>Version 1</main>';
        pages['http://localhost/'] = { html: page('<main>Version 2</main>') };

        await refresh(['page.html.twig']);

        expect(document.querySelector('main').textContent).toBe('Version 2');
    });
});
