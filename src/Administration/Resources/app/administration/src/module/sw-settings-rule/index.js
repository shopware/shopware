import './component/sw-settings-rule-add-assignment-modal';
import './component/sw-settings-rule-add-assignment-listing';
import './component/sw-settings-rule-assignment-listing';
import './component/sw-settings-rule-category-tree';
import './component/sw-settings-rule-tree-item';
import './component/sw-settings-rule-tree';
import './page/sw-settings-rule-list';
import './page/sw-settings-rule-detail';
import './view/sw-settings-rule-detail-base';
import './view/sw-settings-rule-detail-assignments';
import './acl';

const { Module } = Shopware;

Module.register('sw-settings-rule', {
    type: 'core',
    name: 'settings-rule',
    title: 'sw-settings-rule.general.mainMenuItemGeneral',
    description: 'sw-settings-rule.general.descriptionTextModule',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',
    entity: 'rule',

    routes: {
        index: {
            component: 'sw-settings-rule-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'rule.viewer',
            },
        },
        detail: {
            component: 'sw-settings-rule-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.rule.index',
                privilege: 'rule.viewer',
            },
            props: {
                default(route) {
                    return {
                        ruleId: route.params.id,
                    };
                },
            },
            redirect: {
                name: 'sw.settings.rule.detail.base',
            },
            children: {
                base: {
                    component: 'sw-settings-rule-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.settings.rule.index',
                        privilege: 'rule.viewer',
                    },
                },
                assignments: {
                    component: 'sw-settings-rule-detail-assignments',
                    path: 'assignments',
                    meta: {
                        parentPath: 'sw.settings.rule.index',
                        privilege: 'rule.viewer',
                    },
                },
            },
        },
        create: {
            component: 'sw-settings-rule-detail',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.rule.index',
                privilege: 'rule.creator',
            },
            redirect: {
                name: 'sw.settings.rule.create.base',
            },
            children: {
                base: {
                    component: 'sw-settings-rule-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.settings.rule.index',
                        privilege: 'rule.viewer',
                    },
                },
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.rule.index',
        icon: 'regular-rule',
        privilege: 'rule.viewer',
    },
});
