import './page/sw-settings-rule-list';
import './page/sw-settings-rule-detail';

const { Module } = Shopware;

Module.register('sw-settings-rule', {
    type: 'core',
    name: 'settings-rule',
    title: 'sw-settings-rule.general.mainMenuItemGeneral',
    description: 'sw-settings-rule.general.descriptionTextModule',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'rule',

    routes: {
        index: {
            component: 'sw-settings-rule-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        },
        detail: {
            component: 'sw-settings-rule-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.rule.index'
            },
            props: {
                default(route) {
                    return {
                        ruleId: route.params.id
                    };
                }
            }
        },
        create: {
            component: 'sw-settings-rule-detail',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.rule.index'
            }
        }
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.rule.index',
        icon: 'default-symbol-rule'
    }
});
