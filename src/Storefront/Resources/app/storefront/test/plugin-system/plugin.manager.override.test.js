import PluginManager from 'src/plugin-system/plugin.manager';
import NavbarPlugin from 'src/plugin/navbar/navbar.plugin';
import NavbarOverridePlugin from './navbar.override.fixture';

/**
 * End-to-end coverage with the real core Navbar plugin and a real override,
 * registered the way a theme registers it.
 */
describe('Plugin manager override with a real core plugin', () => {
    beforeEach(() => {
        document.body.innerHTML = `
            <nav data-navbar="true">
                <div class="main-navigation-menu">
                    <a class="nav-link main-navigation-link" href="/foo"><span>Foo</span></a>
                </div>
            </nav>
        `;

        jest.spyOn(console, 'warn').mockImplementation();
        jest.spyOn(console, 'error').mockImplementation();
    });

    afterEach(() => {
        PluginManager.deregister('Navbar');
        jest.resetAllMocks();
    });

    // Core: src/main.js registers Navbar as an async plugin.
    const coreRegister = () => PluginManager.register(
        'Navbar',
        () => import('src/plugin/navbar/navbar.plugin'),
        '[data-navbar]',
    );

    // Theme: main.js overrides it, guarded the way the documentation recommends.
    const themeOverride = () => {
        if (Object.prototype.hasOwnProperty.call(PluginManager.getPluginList(), 'Navbar')) {
            PluginManager.override(
                'Navbar',
                () => import('./navbar.override.fixture'),
                '[data-navbar]',
            );
        }
    };

    const assertOverrideIsLive = () => {
        const el = document.querySelector('[data-navbar]');
        const registered = PluginManager.getPlugin('Navbar').get('class');
        const instance = PluginManager.getPluginInstanceFromElement(el, 'Navbar');

        // The registered class must be the override.
        expect(registered).toBe(NavbarOverridePlugin);

        // And the live instance on the element must be the override, not the core class.
        expect(instance).toBeInstanceOf(NavbarOverridePlugin);
        expect(typeof instance._marker).toBe('function');
        expect(instance._marker()).toBe('override-ran');

        // And the override's own init() really ran on the element.
        expect(el.getAttribute('data-override-init')).toBe('true');

        // Sanity: it is still a Navbar, so core behaviour is inherited.
        expect(instance).toBeInstanceOf(NavbarPlugin);
    };

    it('theme overrides before the page initializes', async () => {
        coreRegister();
        themeOverride();

        await PluginManager.initializePlugins();
        await new Promise(process.nextTick);

        assertOverrideIsLive();
    });

    it('theme overrides while the core chunk is still loading', async () => {
        coreRegister();

        const initPromise = PluginManager.initializePlugins();
        await new Promise(process.nextTick);
        themeOverride();

        await initPromise;
        await new Promise(process.nextTick);

        assertOverrideIsLive();
    });

    it('theme overrides after the page was already initialized', async () => {
        coreRegister();
        await PluginManager.initializePlugins();

        const el = document.querySelector('[data-navbar]');
        expect(PluginManager.getPluginInstanceFromElement(el, 'Navbar')).toBeInstanceOf(NavbarPlugin);

        themeOverride();
        await new Promise(process.nextTick);

        assertOverrideIsLive();
        expect(PluginManager.getPluginInstances('Navbar').length).toBe(1);
    });

    it('theme overrides an async plugin with a plain class', async () => {
        coreRegister();
        PluginManager.override('Navbar', NavbarOverridePlugin, '[data-navbar]');

        await PluginManager.initializePlugins();
        await new Promise(process.nextTick);

        assertOverrideIsLive();
    });
});
