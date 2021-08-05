import './page/sw-flow-list';
import './page/sw-flow-detail';

import './acl';

const { Module } = Shopware;

Module.register('sw-flow', {
    flag: 'FEATURE_NEXT_8225',
    type: 'core',
    name: 'flow',
    title: 'sw-flow.general.mainMenuItemGeneral',
    description: 'sw-flow.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'flow',

    routes: {
        index: {
            component: 'sw-flow-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'flow.viewer',
            },
        },
        detail: {
            component: 'sw-flow-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.flow.index',
                privilege: 'flow.creator',
            },
            props: {
                default(route) {
                    return {
                        flowId: route.params.id,
                    };
                },
            },
            redirect: {
                name: 'sw.flow.create.general',
            },
            children: {
                general: {
                    component: 'sw-flow-detail-general',
                    path: 'general',
                    meta: {
                        parentPath: 'sw.flow.index',
                        privilege: 'flow.creator',
                    },
                },
                flow: {
                    component: 'sw-flow-detail-flow',
                    path: 'flow',
                    meta: {
                        parentPath: 'sw.flow.index',
                        privilege: 'flow.creator',
                    },
                },
            },
        },
        create: {
            component: 'sw-flow-detail',
            path: 'create',
            meta: {
                parentPath: 'sw.flow.index',
                privilege: 'flow.creator',
            },
            redirect: {
                name: 'sw.flow.create.general',
            },
            children: {
                general: {
                    component: 'sw-flow-detail-general',
                    path: 'general',
                    meta: {
                        parentPath: 'sw.flow.index',
                        privilege: 'flow.creator',
                    },
                },
                flow: {
                    component: 'sw-flow-detail-flow',
                    path: 'flow',
                    meta: {
                        parentPath: 'sw.flow.index',
                        privilege: 'flow.creator',
                    },
                },
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.flow.index',
        icon: 'default-symbol-flow',
        privilege: 'flow.viewer',
    },
});
