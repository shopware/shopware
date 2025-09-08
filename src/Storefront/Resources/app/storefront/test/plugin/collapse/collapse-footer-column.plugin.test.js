/**
 * Unit tests for CollapseFooterColumnsPlugin
 * Covers: attribute wiring on mobile, cleanup on desktop, and viewport switching.
 *
 * Test strategy:
 * - Mock Plugin base + ViewportDetection so we control environment
 * - Mock Bootstrap.Collapse to track instances and toggle 'show' class
 * - Build a minimal DOM: headline <a> + dedicated '+' button + collapsible content
 * - Call _onViewportHasChanged() to simulate (re)binding per viewport
 */

let CollapseFooterColumnsPlugin; // loaded fresh per test (after mocks)
let viewportMock;

jest.mock('src/plugin-system/plugin.class', () => ({
    __esModule: true,
    default: class Plugin {
        constructor(el, options = {}) {
            this.el = el;
            this.options = { ...(this.constructor.options || {}), ...options };
            this.$emitter = { publish: jest.fn() };
        }
        init() {} // plugin tests call init() explicitly
    },
}));

/**
 * Bootstrap Collapse mock
 * - stores instance per element
 * - show()/hide() toggle 'show' class
 * - dispose() removes instance
 * - getInstance()/getOrCreateInstance() behave like Bootstrap 5
 */
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
    static getOrCreateInstance(el, opts = {}) {
        return MockCollapse.getInstance(el) || new MockCollapse(el, opts);
    }
}

function loadPluginWithViewport({ isXS = false, isSM = false } = {}) {
    jest.resetModules();

    viewportMock = {
        isXS: jest.fn(() => isXS),
        isSM: jest.fn(() => isSM),
        // present but unused here
        isMD: jest.fn(() => false),
        isLG: jest.fn(() => false),
    };

    jest.doMock('src/helper/viewport-detection.helper', () => ({
        __esModule: true,
        default: viewportMock,
    }));

    // Expose bootstrap global
    // eslint-disable-next-line no-undef
    global.bootstrap = { Collapse: MockCollapse };

    return import('./../../../src/plugin/collapse/collapse-footer-columns.plugin.js')
        .then(mod => { CollapseFooterColumnsPlugin = mod.default; });
}

function buildDom() {
    // Root element the plugin attaches to
    const root = document.createElement('div');
    root.setAttribute('data-collapse-footer', ''); // typical root marker

    // One footer column
    const column = document.createElement('div');
    column.className = 'js-footer-column';

    // Headline link (pure link — should always navigate)
    const headline = document.createElement('a');
    headline.className = 'footer-headline';
    headline.href = '/root';
    headline.textContent = 'Root';

    // "+" toggle button (the only element that should carry collapse trigger class)
    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'js-collapse-footer-column-trigger footer-column-toggle';

    // Collapsible content
    const content = document.createElement('div');
    content.className = 'js-footer-column-content collapse';

    column.appendChild(headline);
    column.appendChild(toggle);
    column.appendChild(content);
    root.appendChild(column);

    document.body.innerHTML = '';
    document.body.appendChild(root);

    return { root, column, headline, toggle, content };
}

describe('CollapseFooterColumnsPlugin', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        // eslint-disable-next-line no-undef
        delete global.bootstrap;
    });

    test('mobile: wires data-API on the toggle and creates a Collapse instance', async () => {
        await loadPluginWithViewport({ isXS: true });

        const { root, toggle, content } = buildDom();

        const plugin = new CollapseFooterColumnsPlugin(root);
        plugin.init();

        // Force (re)binding for the current viewport
        expect(typeof plugin._onViewportHasChanged).toBe('function');
        plugin._onViewportHasChanged();

        // Content should have a stable id for data-bs-target wiring
        expect(content.id).toMatch(/^footer-collapse-/);

        // Toggle button must carry Bootstrap data-API on mobile
        expect(toggle.getAttribute('data-bs-toggle')).toBe('collapse');
        expect(toggle.getAttribute('data-bs-target')).toBe(`#${content.id}`);
        expect(toggle.getAttribute('aria-controls')).toBe(content.id);
        expect(toggle.classList.contains('collapsed')).toBe(true);
        expect(toggle.getAttribute('aria-expanded')).toBe('false');

        // Bootstrap Collapse instance should be created but not auto-toggled (toggle: false)
        expect(MockCollapse.getInstance(content)).toBeTruthy();
        expect(content.classList.contains('show')).toBe(false);
    });

    test('desktop: disposes Collapse and strips data-API so headline link can navigate', async () => {
        // Start mobile first to simulate real life, then switch to desktop
        await loadPluginWithViewport({ isXS: true });

        const { root, toggle, content } = buildDom();

        const plugin = new CollapseFooterColumnsPlugin(root);
        plugin.init();
        plugin._onViewportHasChanged(); // mobile bind

        // Sanity: mobile has data attributes + instance
        expect(MockCollapse.getInstance(content)).toBeTruthy();
        expect(toggle.getAttribute('data-bs-toggle')).toBe('collapse');

        // Switch to desktop
        viewportMock.isXS.mockReturnValue(false);
        viewportMock.isSM.mockReturnValue(false);
        plugin._onViewportHasChanged();

        // Data-API must be removed on desktop
        expect(toggle.getAttribute('data-bs-toggle')).toBeNull();
        expect(toggle.getAttribute('data-bs-target')).toBeNull();
        expect(toggle.getAttribute('aria-controls')).toBeNull();
        expect(toggle.classList.contains('collapsed')).toBe(false);
        expect(toggle.getAttribute('aria-expanded')).toBe('true');

        // Instance disposed
        expect(MockCollapse.getInstance(content)).toBeNull();
    });

    test('switching back to mobile re-adds data-API and recreates instance', async () => {
        await loadPluginWithViewport({ isXS: true });

        const { root, toggle, content } = buildDom();

        const plugin = new CollapseFooterColumnsPlugin(root);
        plugin.init();

        // mobile -> bind
        plugin._onViewportHasChanged();
        expect(MockCollapse.getInstance(content)).toBeTruthy();

        // switch to desktop -> dispose & strip
        viewportMock.isXS.mockReturnValue(false);
        viewportMock.isSM.mockReturnValue(false);
        plugin._onViewportHasChanged();
        expect(MockCollapse.getInstance(content)).toBeNull();
        expect(toggle.getAttribute('data-bs-toggle')).toBeNull();

        // back to mobile -> rebind
        viewportMock.isXS.mockReturnValue(true);
        plugin._onViewportHasChanged();

        expect(content.id).toBeTruthy();
        expect(toggle.getAttribute('data-bs-toggle')).toBe('collapse');
        expect(MockCollapse.getInstance(content)).toBeTruthy();
    });
});
