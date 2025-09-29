import CollapseFooterColumnsPlugin from 'src/plugin/collapse/collapse-footer-columns.plugin';

describe('collapse-footer-columns.plugin test', () => {
    let plugin;
    let onViewportHasChangedSpy;

    const template = `
        <div data-collapse-footer>
            <div class="js-footer-column">
                <a class="footer-headline" href="/root">Root</a>
                <button type="button" class="js-collapse-footer-column-trigger footer-column-toggle">
                    +
                </button>
                <div class="js-footer-column-content collapse">
                    <a href="/child">Child</a>
                </div>
            </div>
        </div>
    `;

    class MockCollapse {
        static _map = new WeakMap();
        constructor(el, opts = {}) {
            this._el = el;
            this._opts = opts;
            MockCollapse._map.set(el, this);
        }
        show() { this._el.classList.add('show'); }
        hide() { this._el.classList.remove('show'); }
        dispose() { MockCollapse._map.delete(this._el); }
        static getInstance(el) { return MockCollapse._map.get(el) || null; }
    }

    function initPlugin() {
        const root = document.querySelector('[data-collapse-footer]');
        return new CollapseFooterColumnsPlugin(root);
    }

    beforeEach(() => {
        window.PluginManager = {
            getPluginInstancesFromElement: () => new Map(),
            getPlugin: () => ({ get: () => [] }),
        };

        document.body.innerHTML = template;

        // Provide Bootstrap Collapse on the global like Storefront expects
        // eslint-disable-next-line no-undef
        global.bootstrap = { Collapse: MockCollapse };

        plugin = initPlugin();

    });

    afterEach(() => {
        document.body.innerHTML = '';
        plugin = undefined;
        // eslint-disable-next-line no-undef
        delete global.bootstrap;
    });

    test('plugin exists', () => {
        expect(typeof plugin).toBe('object');
    });

    test('mobile: wiring adds data-API to toggle and creates a Collapse instance', () => {
        // Force "mobile" without module-mocking by stubbing the method the plugin itself uses
        const mobileStub = jest.spyOn(plugin, '_isInAllowedViewports').mockReturnValue(true);

        // Trigger the viewport binding
        plugin._onViewportHasChanged();

        const toggle = document.querySelector('.js-collapse-footer-column-trigger');
        const content = document.querySelector('.js-footer-column-content');

        // Content should have an auto id for data-bs-target
        expect(content.id).toMatch(/^footer-collapse-/);

        // Toggle carries Bootstrap data-API on mobile
        expect(toggle.getAttribute('data-bs-toggle')).toBe('collapse');
        expect(toggle.getAttribute('data-bs-target')).toBe(`#${content.id}`);
        expect(toggle.getAttribute('aria-controls')).toBe(content.id);
        expect(toggle.classList.contains('collapsed')).toBe(true);
        expect(toggle.getAttribute('aria-expanded')).toBe('false');

        // Collapse instance exists and panel is not auto-open
        expect(MockCollapse.getInstance(content)).toBeTruthy();
        expect(content.classList.contains('show')).toBe(false);

        mobileStub.mockRestore();
    });

    test('desktop: disposes Collapse and strips data-API so headline link can navigate', () => {
        const isMobile = jest.spyOn(plugin, '_isInAllowedViewports').mockReturnValue(true);
        plugin._onViewportHasChanged(); // bind as mobile first

        const toggle = document.querySelector('.js-collapse-footer-column-trigger');
        const content = document.querySelector('.js-footer-column-content');

        // Sanity check preconditions
        expect(MockCollapse.getInstance(content)).toBeTruthy();
        expect(toggle.getAttribute('data-bs-toggle')).toBe('collapse');

        // Now simulate desktop and re-bind
        isMobile.mockReturnValue(false);
        plugin._onViewportHasChanged();

        // Data-API is removed on desktop, aria reflects non-collapsible state
        expect(toggle.getAttribute('data-bs-toggle')).toBeNull();
        expect(toggle.getAttribute('data-bs-target')).toBeNull();
        expect(toggle.getAttribute('aria-controls')).toBeNull();
        expect(toggle.classList.contains('collapsed')).toBe(false);
        expect(toggle.getAttribute('aria-expanded')).toBe('true');

        // Instance disposed
        expect(MockCollapse.getInstance(content)).toBeNull();

        isMobile.mockRestore();
    });

    test('switching mobile → desktop → mobile rebinds cleanly', () => {
        const isMobile = jest.spyOn(plugin, '_isInAllowedViewports').mockReturnValue(true);
        const toggle = document.querySelector('.js-collapse-footer-column-trigger');
        const content = document.querySelector('.js-footer-column-content');

        // Bind as mobile
        plugin._onViewportHasChanged();
        expect(MockCollapse.getInstance(content)).toBeTruthy();
        expect(toggle.getAttribute('data-bs-toggle')).toBe('collapse');

        // Switch to desktop -> cleanup
        isMobile.mockReturnValue(false);
        plugin._onViewportHasChanged();
        expect(MockCollapse.getInstance(content)).toBeNull();
        expect(toggle.getAttribute('data-bs-toggle')).toBeNull();

        // Back to mobile -> rebind
        isMobile.mockReturnValue(true);
        plugin._onViewportHasChanged();
        expect(content.id).toBeTruthy();
        expect(toggle.getAttribute('data-bs-toggle')).toBe('collapse');
        expect(MockCollapse.getInstance(content)).toBeTruthy();

        isMobile.mockRestore();
    });
});
