/**
 * @package inventory
 */
import './index';

const { Module } = Shopware;

describe('src/module/sw-settings-media/index.js', () => {
    it('should register component', () => {
        expect(Shopware.Component.getComponentRegistry().has('sw-settings-media')).toBeTruthy();
    });

    it('should register module base information', () => {
        const module = Module.getModuleRegistry().get('sw-settings-media');
        expect(module).toBeDefined();

        expect(module.manifest).toEqual({
            type: 'core',
            name: 'settings-media',
            title: 'sw-settings-media.general.title',
            description: 'sw-settings-media.general.description',
            color: '#9AA8B5',
            icon: 'regular-cog',
            favicon: 'icon-module-settings.png',
            routes: expect.any(Object),
            settingsItem: [
                {
                    id: 'sw-settings-media',
                    group: 'shop',
                    to: 'sw.settings.media.index',
                    icon: 'regular-image',
                    privilege: 'system.system_config',
                    label: 'sw-settings-media.general.title',
                    name: 'settings-media',
                },
            ],
            display: true,
        });
    });

    it('should register module routes', () => {
        const routes = {
            'sw.settings.media.index': {
                path: '/sw/settings/media/index',
                components: { default: 'sw-settings-media' },
            },
        };

        const register = Module.getModuleRegistry().get('sw-settings-media').routes;
        expect(register).toBeDefined();

        expect(register.size).toBe(Object.keys(routes).length);
        Object.keys(routes).forEach((name) => {
            const route = register.get(name);

            expect(route.path).toBe(routes[name].path);
            expect(route.component).toBe(routes[name].component);
        });
    });
});
