/**
 * @sw-package framework
 */
import './index';

const { Module } = Shopware;

describe('src/module/sw-settings-message-stats/index.js', () => {
    it('should register component', () => {
        expect(Shopware.Component.getComponentRegistry().has('sw-settings-message-stats')).toBeTruthy();
    });

    it('should register module base information', () => {
        const module = Module.getModuleRegistry().get('sw-settings-message-stats');
        expect(module).toBeDefined();

        expect(module.manifest).toEqual({
            type: 'core',
            name: 'settings-message-stats',
            title: 'sw-settings-message-stats.general.mainMenuItemGeneral',
            description: 'sw-settings-message-stats.general.descriptionTextModule',
            version: '1.0.0',
            targetVersion: '1.0.0',
            color: '#9AA8B5',
            icon: 'regular-cog',
            favicon: 'icon-module-settings.png',
            routes: expect.any(Object),
            settingsItem: [
                {
                    id: 'sw-settings-message-stats',
                    group: 'system',
                    to: 'sw.settings.message.stats.index',
                    icon: 'regular-bars-square',
                    privilege: 'system.system_config',
                    label: 'sw-settings-message-stats.general.mainMenuItemGeneral',
                    name: 'settings-message-stats',
                },
            ],
            display: true,
        });

        const settingsItem = module.manifest.settingsItem[0];
        expect(typeof settingsItem.group).toBe('string');
        expect(settingsItem.group).toBe('system');
    });

    it('should register module routes', () => {
        const module = Module.getModuleRegistry().get('sw-settings-message-stats');
        expect(module.routes).toBeDefined();
        expect(module.routes.size).toBe(1);

        const route = module.routes.get('sw.settings.message.stats.index');
        expect(route !== undefined).toBe(true);
        expect(route.path).toBe('/sw/settings/message/stats/index');
        expect(route.meta).toEqual({
            parentPath: 'sw.settings.index.system',
            privilege: 'system.system_config',
        });
    });
});
