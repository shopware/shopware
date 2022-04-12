import './page/sw-settings-tag-list';
import './component/sw-settings-tag-detail-modal';
import './component/sw-settings-tag-detail-assignments';

import './acl';

const { Module } = Shopware;

Module.register('sw-settings-tag', {
    type: 'core',
    name: 'settings-tag',
    title: 'sw-settings-tag.general.mainMenuItemGeneral',
    description: 'Tag section in the settings module',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'tag',

    routes: {
        index: {
            component: 'sw-settings-tag-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'tag.viewer',
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.tag.index',
        icon: 'default-action-tags',
        privilege: 'tag.viewer',
    },
});
