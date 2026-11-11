import OffCanvasMenuPlugin from 'src/plugin/main-menu/offcanvas-menu.plugin';

describe('OffCanvasMenuPlugin tests', () => {
    let plugin;

    const offCanvasMenuSubCategory = `
        <div class="navigation-offcanvas-container">
            <div class="navigation-offcanvas-content">
                <div class="navigation-offcanvas-headline">Categories</div>
                <ul class="list-unstyled navigation-offcanvas-list">
                    <li class="navigation-offcanvas-list-item">
                        <a href="#"
                           class="navigation-offcanvas-link nav-item nav-link js-navigation-offcanvas-link"
                           data-href="/widgets/menu/offcanvas?navigationId=0188fd3e4ffb7079959622b2785167eb">
                            Cars
                        </a>
                    </li>
                    <li class="navigation-offcanvas-list-item">
                        <a href="#"
                           class="navigation-offcanvas-link nav-item nav-link js-navigation-offcanvas-link"
                           data-href="/widgets/menu/offcanvas?navigationId=0188fd3e4ffb7079959622b2785167eb">
                            Smartphones
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    `;

    const offCanvasMenuInitialContent = `
        <div class="navigation-offcanvas-container navigation-offcanvas-root">
            <div class="navigation-offcanvas-content">
                <div class="navigation-offcanvas-headline">Categories</div>
                <ul class="list-unstyled navigation-offcanvas-list">
                    <li class="navigation-offcanvas-list-item">
                        <a href="#"
                           class="navigation-offcanvas-link nav-item nav-link js-navigation-offcanvas-link"
                           data-href="/widgets/menu/offcanvas?navigationId=0188fd3e4ffb7079959622b2785167eb">
                            Outdoors
                        </a>
                    </li>
                    <li class="navigation-offcanvas-list-item">
                        <a href="#"
                           class="navigation-offcanvas-link nav-item nav-link js-navigation-offcanvas-link"
                           data-href="/widgets/menu/offcanvas?navigationId=0188fd3e4ffb7079959622b2785167eb">
                            Automotive
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    `;

    beforeEach(() => {
        global.fetch = jest.fn((url) => {
            if (url.endsWith('navigationId=0188fd3e4ffb7079959622b2785167eb')) {
                return Promise.resolve({
                    text: () => Promise.resolve(offCanvasMenuSubCategory),
                });
            } else {
                return Promise.resolve({
                    text: () => Promise.resolve(offCanvasMenuInitialContent),
                });
            }
        });

        document.body.innerHTML = `
            <button class="btn nav-main-toggle-btn header-actions-btn" type="button" data-offcanvas-menu="true">
                <span class="icon icon-stack"></span>
            </button>

            <div class="js-navigation-offcanvas-initial-content d-none">
                <div class="offcanvas-body">
                    <p>Initial content</p>

                    <div class="navigation-offcanvas-container"></div>
                </div>
            </div>
        `;

        const el = document.querySelector('[data-offcanvas-menu]');

        window.focusHandler = {
            saveFocusState: jest.fn(),
            resumeFocusState: jest.fn(),
            setFocus: jest.fn(),
            // @todo: Remove when upstream issue https://github.com/twbs/bootstrap/issues/42503 is resolved.
            _addFocusTrapGuard: jest.fn(),
            _removeFocusTrapGuard: jest.fn(),
        };

        window.PluginManager.register = jest.fn();
        window.PluginManager.initializePluginsInParentElement = jest.fn(() => Promise.resolve());
        window.history.replaceState = jest.fn(() => Promise.resolve());

        plugin = new OffCanvasMenuPlugin(el);
    });

    test('Creates plugin instance', () => {
        expect(typeof plugin).toBe('object');
    });

    test('Open OffCanvas menu on click with initial content from DOM', async () => {
        // Open OffCanvas menu
        plugin.el.dispatchEvent(new Event('click'));
        await new Promise(process.nextTick);

        const categoryLinks = document.querySelectorAll('.navigation-offcanvas .navigation-offcanvas-link');

        // Ensure OffCanvas is opened with initial content
        expect(categoryLinks[0].textContent).toContain('Outdoors');
        expect(categoryLinks[1].textContent).toContain('Automotive');
    });

    test('Fetch and render next category after click on category link', async () => {
        // Open OffCanvas menu
        plugin.el.dispatchEvent(new Event('click', { bubbles: true }));
        await new Promise(process.nextTick);

        const link = document.querySelector('.js-navigation-offcanvas-link');
        plugin._getLinkEventHandler(new MouseEvent('click', { bubbles: true }), link);
        await new Promise(process.nextTick);

        // jest.runAllTimers();

        const subCategoryLinks = document.querySelectorAll('.navigation-offcanvas .navigation-offcanvas-link');

        // Ensure sub-categories are rendered
        expect(subCategoryLinks[0].textContent).toContain('Cars');
        expect(subCategoryLinks[1].textContent).toContain('Smartphones');
    });

    test('Marks the open menu as a part of the page for the Twig hot reload', async () => {
        window.activeNavigationId = 'root';
        plugin.options.navigationUrl = '/widgets/menu/offcanvas';

        plugin.el.dispatchEvent(new Event('click'));
        await new Promise(process.nextTick);

        const offcanvas = document.querySelector('.navigation-offcanvas');
        expect(offcanvas.dataset.swSourceShell).toBe('.js-navigation-offcanvas-initial-content');
        expect(offcanvas.dataset.swSourceSlot).toBe('.navigation-offcanvas-container');
        expect(offcanvas.dataset.swSourceUrl).toBe('/widgets/menu/offcanvas?navigationId=root');

        plugin._getLinkEventHandler(new MouseEvent('click'), offcanvas.querySelector('.js-navigation-offcanvas-link'));
        await new Promise(process.nextTick);

        expect(offcanvas.dataset.swSourceUrl).toBe('/widgets/menu/offcanvas?navigationId=0188fd3e4ffb7079959622b2785167eb');
    });

    test('Drops cached responses and binds new links after a Twig hot reload', async () => {
        plugin.el.dispatchEvent(new Event('click'));
        await new Promise(process.nextTick);

        const offcanvas = document.querySelector('.navigation-offcanvas');
        const keptLink = offcanvas.querySelector('.js-navigation-offcanvas-link');
        const newLink = keptLink.cloneNode(true);
        keptLink.after(newLink);
        const handler = jest.spyOn(plugin, '_getLinkEventHandler').mockImplementation(() => {});

        document.dispatchEvent(new CustomEvent('sw-twig-hmr:updated', { detail: { files: [], reloadRequired: [] } }));

        expect(plugin._cache).toEqual({});

        keptLink.dispatchEvent(new MouseEvent('click'));
        newLink.dispatchEvent(new MouseEvent('click'));

        expect(handler.mock.calls.map(([, link]) => link)).toEqual([keptLink, newLink]);
    });

    test('Open the OffCanvas menu via URL parameter', async () => {
        // Simulate URL parameter
        window.history.pushState({}, '', '?offcanvas=menu');

        // Open OffCanvas menu
        plugin._openMenuViaUrlParameter();
        await new Promise(process.nextTick);

        const offCanvasMenuButton = document.querySelector('[data-offcanvas-menu="true"]');
        offCanvasMenuButton.click();
        await new Promise(process.nextTick);

        // Ensure JS events are registered within the offcanvas
        expect(window.PluginManager.initializePluginsInParentElement).toHaveBeenCalled();
        // Ensure the parameter is removed from the URL
        expect(window.history.replaceState).toHaveBeenCalled();
    });
});
