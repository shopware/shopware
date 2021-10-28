import './page/sw-flow-list';
import './page/sw-flow-detail';
import './view/detail/sw-flow-detail-flow';
import './component/sw-flow-sequence-modal';
import './view/detail/sw-flow-detail-general';
import './component/sw-flow-trigger';
import './component/sw-flow-sequence';
import './component/sw-flow-sequence-action';
import './component/sw-flow-sequence-condition';
import './component/sw-flow-sequence-selector';
import './component/modals/sw-flow-rule-modal';
import './component/modals/sw-flow-tag-modal';
import './component/modals/sw-flow-set-order-state-modal';
import './component/modals/sw-flow-generate-document-modal';
import './component/modals/sw-flow-mail-send-modal';
import './component/modals/sw-flow-create-mail-template-modal';
import './component/modals/sw-flow-event-change-confirm-modal';
import './component/modals/sw-flow-change-customer-group-modal';

import './service/flow-builder.service';
import './acl';

import flowState from './state/flow.state';

const { Module, State } = Shopware;
State.registerModule('swFlowState', flowState);

Module.register('sw-flow', {
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
                privilege: 'flow.viewer',
            },
            props: {
                default(route) {
                    return {
                        flowId: route.params.id,
                    };
                },
            },
            redirect: {
                name: 'sw.flow.detail.general',
            },
            children: {
                general: {
                    component: 'sw-flow-detail-general',
                    path: 'general',
                    meta: {
                        parentPath: 'sw.flow.index',
                        privilege: 'flow.viewer',
                    },
                },
                flow: {
                    component: 'sw-flow-detail-flow',
                    path: 'flow',
                    meta: {
                        parentPath: 'sw.flow.index',
                        privilege: 'flow.viewer',
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
                        privilege: 'flow.viewer',
                    },
                },
                flow: {
                    component: 'sw-flow-detail-flow',
                    path: 'flow',
                    meta: {
                        parentPath: 'sw.flow.index',
                        privilege: 'flow.viewer',
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
