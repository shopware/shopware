import './extension/sw-settings-index';
import './page/sw-settings-newsletter-config';

const { Module } = Shopware;

Module.register('sw-settings-newsletter-config', {
    type: 'core',
    name: 'settings-newsletter-config',
    title: 'sw-settings-newsletter-config.general.mainMenuItemGeneral',
    description: 'sw-settings-newsletter-config.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',

    routes: {
        index: {
            component: 'sw-settings-newsletter-config',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        }
    }
});
