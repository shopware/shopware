/**
 * @sw-package inventory
 */
import './acl';
import './component/sw-settings-tag-detail-assignments/register';
import './component/sw-settings-tag-detail-modal/register';
import './page/sw-settings-tag-list/register';

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-tag', {
    type: 'core',
    name: 'settings-tag',
    title: 'sw-settings-tag.general.mainMenuItemGeneral',
    description: 'Tag section in the settings module',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-tag',
    favicon: 'icon-module-settings.svg',
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
        group: 'content',
        to: 'sw.settings.tag.index',
        icon: 'regular-tag',
        privilege: 'tag.viewer',
    },
});
