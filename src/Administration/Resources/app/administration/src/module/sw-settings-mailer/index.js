import './extension/sw-settings-index';
import './component/sw-settings-mailer-smtp';
import './page/sw-settings-mailer';

Shopware.Module.register('sw-settings-mailer', {
    type: 'core',
    name: 'settings-mailer',
    title: 'sw-settings-store.general.mainMenuItemGeneral', // TODO: Add title
    description: 'sw-settings-store.general.description', // TODO: Add description
    color: '#9AA8B5',
    icon: 'default-communication-envelope',
    favicon: 'icon-module-settings.png',

    routes: {
        index: {
            component: 'sw-settings-mailer',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        }
    }
});
