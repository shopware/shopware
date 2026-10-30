import { refresh } from 'src/dev/twig-hmr/twig-hmr';

/**
 * @sw-package framework
 */
describe('dev/twig-hmr', () => {
    let pages;

    function page(body, title = 'Shop') {
        return `<!DOCTYPE html><html><head><title>${title}</title></head><body>${body}</body></html>`;
    }

    function mockFetch() {
        global.fetch = jest.fn((url) => {
            const { status = 200, html } = pages[url];

            return Promise.resolve({
                ok: status < 400,
                status,
                text: () => Promise.resolve(html),
            });
        });
    }

    beforeEach(() => {
        pages = {};
        mockFetch();
        jest.spyOn(console, 'info').mockImplementation(() => {});
        jest.spyOn(console, 'error').mockImplementation(() => {});
        window.PluginManager.initializePlugins = jest.fn(() => Promise.resolve());
        document.title = 'Shop';
        document.body.innerHTML = '';
    });

    it('updates an open modal and keeps its runtime state', async () => {
        document.body.innerHTML = `
            <div class="modal fade show" id="demo" style="display: block;" aria-modal="true" role="dialog">
                <p class="text">Version 1</p>
            </div>
            <div class="modal-backdrop fade show"></div>
        `;
        document.body.classList.add('modal-open');
        pages['http://localhost/'] = {
            html: page('<div class="modal fade" id="demo" aria-hidden="true"><p class="text">Version 2</p></div>', 'New title'),
        };

        await refresh(['modal-body.html.twig']);

        const modal = document.getElementById('demo');
        expect(modal.querySelector('.text').textContent).toBe('Version 2');
        expect(modal.classList.contains('show')).toBe(true);
        expect(modal.style.display).toBe('block');
        expect(modal.getAttribute('aria-modal')).toBe('true');
        expect(modal.hasAttribute('aria-hidden')).toBe(false);
        expect(document.querySelector('.modal-backdrop')).not.toBeNull();
        expect(document.body.classList.contains('modal-open')).toBe(true);
        expect(document.querySelector('body body')).toBeNull();
        expect(document.title).toBe('New title');
        expect(window.PluginManager.initializePlugins).toHaveBeenCalledTimes(1);
    });

    it('keeps typed form values and the focus', async () => {
        document.body.innerHTML = '<main><label>Old</label><input type="text" class="search"></main>';
        const input = document.querySelector('.search');
        input.value = 'typed';
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

    it('re-renders the content of an open AJAX modal', async () => {
        document.body.innerHTML = `
            <main>Page</main>
            <div class="js-pseudo-modal">
                <div class="modal show" data-sw-source-url="/widgets/cms/abc">
                    <div class="js-pseudo-modal-template-content-element"><p class="part">Version 1</p></div>
                </div>
            </div>
        `;
        pages['http://localhost/'] = { html: page('<main>Page</main>') };
        pages['/widgets/cms/abc'] = { html: '<p class="part">Version 2</p>' };

        await refresh(['ajax-modal-part.html.twig']);

        expect(global.fetch).toHaveBeenCalledWith('/widgets/cms/abc', expect.objectContaining({
            headers: expect.objectContaining({ 'X-Requested-With': 'XMLHttpRequest' }),
        }));
        expect(document.querySelector('.js-pseudo-modal .part').textContent).toBe('Version 2');
        expect(document.querySelector('.js-pseudo-modal .modal').classList.contains('show')).toBe(true);
    });

    it('re-renders the content of an open AJAX offcanvas', async () => {
        document.body.innerHTML = `
            <main>Page</main>
            <div class="offcanvas offcanvas-end js-offcanvas-singleton show" data-sw-source-url="/checkout/offcanvas" style="visibility: visible;">
                <div class="offcanvas-body"><p class="part">Version 1</p></div>
            </div>
        `;
        pages['http://localhost/'] = { html: page('<main>Page</main>') };
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
        document.body.innerHTML = `
            <div class="js-pseudo-modal">
                <div class="modal show" data-sw-source-url="/widgets/quickview">
                    <div class="js-pseudo-modal-template-root-element"><p class="part">Version 1</p></div>
                </div>
            </div>
        `;
        pages['http://localhost/'] = { html: page('') };
        pages['/widgets/quickview'] = {
            html: '<div class="js-pseudo-modal-template-root-element"><p class="part">Version 2</p></div>',
        };

        await refresh(['quickview.html.twig']);

        expect(document.querySelector('.js-pseudo-modal .part').textContent).toBe('Version 2');
        expect(document.querySelectorAll('.js-pseudo-modal-template-root-element')).toHaveLength(1);
    });

    it('moves the title of an AJAX modal response into the modal title', async () => {
        document.body.innerHTML = `
            <div class="js-pseudo-modal">
                <div class="modal show" data-sw-source-url="/widgets/cms/abc">
                    <h5 class="js-pseudo-modal-template-title-element">Title 1</h5>
                    <div class="js-pseudo-modal-template-content-element"><p class="part">Version 1</p></div>
                </div>
            </div>
        `;
        pages['http://localhost/'] = { html: page('') };
        pages['/widgets/cms/abc'] = {
            html: '<span class="js-pseudo-modal-template-title-element">Title 2</span><p class="part">Version 2</p>',
        };

        await refresh(['cms.html.twig']);

        const modal = document.querySelector('.js-pseudo-modal .modal');
        expect(modal.querySelector('.js-pseudo-modal-template-title-element').textContent).toBe('Title 2');
        expect(modal.querySelectorAll('.js-pseudo-modal-template-title-element')).toHaveLength(1);
        expect(modal.querySelector('.part').textContent).toBe('Version 2');
    });

    it('keeps elements that scripts added at runtime', async () => {
        document.body.innerHTML = `
            <main>
                <p class="text">Version 1</p>
                <div class="toast" data-sw-twig-hmr-preserve>Added to cart</div>
            </main>
            <div class="script-root">Appended by a plugin</div>
        `;
        pages['http://localhost/'] = { html: page('<main><p class="text">Version 2</p></main>') };

        await refresh(['page.html.twig']);

        expect(document.querySelector('.text').textContent).toBe('Version 2');
        expect(document.querySelector('[data-sw-twig-hmr-preserve]')).not.toBeNull();
        expect(document.querySelector('.script-root')).not.toBeNull();
    });

    it('keeps the state of collapse elements, toggles and checkboxes', async () => {
        document.body.innerHTML = `
            <main>
                <button class="btn" data-bs-toggle="collapse" aria-expanded="true">Filter</button>
                <div class="collapse show" id="filter"><input type="checkbox" class="option"> Old</div>
            </main>
        `;
        const checkbox = document.querySelector('.option');
        checkbox.checked = true;
        pages['http://localhost/'] = {
            html: page(`
                <main>
                    <button class="btn btn-primary" data-bs-toggle="collapse" aria-expanded="false">Filter</button>
                    <div class="collapse" id="filter"><input type="checkbox" class="option"> New</div>
                </main>
            `),
        };

        await refresh(['filter.html.twig']);

        const toggle = document.querySelector('[data-bs-toggle]');
        expect(toggle.classList.contains('btn-primary')).toBe(true);
        expect(toggle.getAttribute('aria-expanded')).toBe('true');
        expect(document.getElementById('filter').classList.contains('show')).toBe(true);
        expect(document.getElementById('filter').textContent).toContain('New');
        expect(document.querySelector('.option')).toBe(checkbox);
        expect(checkbox.checked).toBe(true);
    });

    it('keeps the current DOM when the request fails', async () => {
        document.body.innerHTML = '<main><p class="text">Version 1</p></main>';
        global.fetch = jest.fn(() => Promise.reject(new Error('Failed to fetch')));

        await refresh(['page.html.twig']);

        expect(document.querySelector('.text').textContent).toBe('Version 1');
        expect(console.error).toHaveBeenCalledWith('Failed to fetch');
    });

    it('keeps the current DOM when the server answers with an error', async () => {
        document.body.innerHTML = '<main><p class="text">Version 1</p></main>';
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
    });

    it('runs changes during a refresh once afterwards', async () => {
        const updated = jest.fn();
        document.addEventListener('sw-twig-hmr:updated', updated);
        document.body.innerHTML = '<main>Version 1</main>';
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
});
