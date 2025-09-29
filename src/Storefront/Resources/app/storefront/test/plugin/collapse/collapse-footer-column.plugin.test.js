import CollapseFooterColumnsPlugin from 'src/plugin/collapse/collapse-footer-columns.plugin';
import { Collapse } from 'bootstrap';

describe('collapse-footer-columns.plugin test', () => {
    let plugin;
    let viewportSpy;

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

        viewportSpy = jest
            .spyOn(CollapseFooterColumnsPlugin.prototype, '_isInAllowedViewports')
            .mockReturnValue(false); // default to "desktop"

        plugin = initPlugin();

    });

    afterEach(() => {
        document.body.innerHTML = '';
        plugin = undefined;
        viewportSpy.mockRestore();
    });

    test('plugin exists', () => {
        expect(typeof plugin).toBe('object');
    });

    test('mobile: wiring adds data-API to toggle and creates a Collapse instance', () => {

        viewportSpy.mockReturnValue(true);
        plugin._onViewportHasChanged();

        const toggle = document.querySelector('.js-collapse-footer-column-trigger');
        const content = document.querySelector('.js-footer-column-content');

        expect(content.id).toMatch(/^footer-collapse-/);

        // Toggle carries Bootstrap data-API on mobile
        expect(toggle.getAttribute('data-bs-toggle')).toBe('collapse');
        expect(toggle.getAttribute('data-bs-target')).toBe(`#${content.id}`);
        expect(toggle.getAttribute('aria-controls')).toBe(content.id);
        expect(toggle.classList.contains('collapsed')).toBe(true);
        expect(toggle.getAttribute('aria-expanded')).toBe('false');

        // Collapse instance exists and panel is not auto-open
        expect(Collapse.getInstance(content)).toBeTruthy();
        expect(content.classList.contains('show')).toBe(false);
    });

    test('desktop: disposes Collapse and strips data-API so headline link can navigate', () => {
        viewportSpy.mockReturnValue(true);
        plugin._onViewportHasChanged();

        const toggle = document.querySelector('.js-collapse-footer-column-trigger');
        const content = document.querySelector('.js-footer-column-content');

        expect(Collapse.getInstance(content)).toBeTruthy();
        expect(toggle.getAttribute('data-bs-toggle')).toBe('collapse');

        viewportSpy.mockReturnValue(false);
        plugin._onViewportHasChanged();

        // Data-API is removed on desktop, aria reflects non-collapsible state
        expect(toggle.getAttribute('data-bs-toggle')).toBeNull();
        expect(toggle.getAttribute('data-bs-target')).toBeNull();
        expect(toggle.getAttribute('aria-controls')).toBeNull();
        expect(toggle.classList.contains('collapsed')).toBe(false);
        expect(toggle.getAttribute('aria-expanded')).toBe('true');

        expect(Collapse.getInstance(content)).toBeNull();
    });

    test('switching mobile → desktop → mobile rebinds cleanly', () => {

        const toggle = document.querySelector('.js-collapse-footer-column-trigger');
        const content = document.querySelector('.js-footer-column-content');

        viewportSpy.mockReturnValue(true);
        plugin._onViewportHasChanged();

        expect(Collapse.getInstance(content)).toBeTruthy();
        expect(toggle.getAttribute('data-bs-toggle')).toBe('collapse');

        // Switch to desktop -> cleanup
        viewportSpy.mockReturnValue(false);
        plugin._onViewportHasChanged();

        expect(Collapse.getInstance(content)).toBeNull();
        expect(toggle.getAttribute('data-bs-toggle')).toBeNull();

        // Back to mobile -> rebind
        viewportSpy.mockReturnValue(true);
        plugin._onViewportHasChanged();

        expect(content.id).toBeTruthy();
        expect(toggle.getAttribute('data-bs-toggle')).toBe('collapse');
        expect(Collapse.getInstance(content)).toBeTruthy();
    });
});
