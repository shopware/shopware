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

    it('keeps the current DOM when the server answers with an error', async () => {
        document.body.innerHTML = '<main><p class="text">Version 1</p></main>';
        pages['http://localhost/'] = {
            status: 500,
            html: page('<h1>Error</h1>', 'Unexpected &quot;}&quot; in modal-body.html.twig (500 Internal Server Error)'),
        };

        await refresh(['modal-body.html.twig']);

        expect(document.querySelector('.text').textContent).toBe('Version 1');
        expect(window.PluginManager.initializePlugins).not.toHaveBeenCalled();
        expect(console.error).toHaveBeenCalledWith('[sw-twig-hmr] Unexpected "}" in modal-body.html.twig (500 Internal Server Error)');
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
