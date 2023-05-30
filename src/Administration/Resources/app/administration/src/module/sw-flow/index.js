import './service/flow-builder.service';
import './acl';

import flowState from './state/flow.state';

const { Module, State } = Shopware;
State.registerModule('swFlowState', flowState);

/* eslint-disable max-len, sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-flow-index', () => import('./page/sw-flow-index'));
Shopware.Component.register('sw-flow-detail', () => import('./page/sw-flow-detail'));
Shopware.Component.register('sw-flow-detail-flow', () => import('./view/detail/sw-flow-detail-flow'));
Shopware.Component.register('sw-flow-sequence-modal', () => import('./component/sw-flow-sequence-modal'));
Shopware.Component.register('sw-flow-detail-general', () => import('./view/detail/sw-flow-detail-general'));
Shopware.Component.register('sw-flow-list', () => import('./view/listing/sw-flow-list'));
Shopware.Component.register('sw-flow-list-flow-templates', () => import('./view/listing/sw-flow-list-flow-templates'));
Shopware.Component.register('sw-flow-trigger', () => import('./component/sw-flow-trigger'));
Shopware.Component.register('sw-flow-sequence', () => import('./component/sw-flow-sequence'));
Shopware.Component.register('sw-flow-sequence-action', () => import('./component/sw-flow-sequence-action'));
Shopware.Component.register('sw-flow-sequence-condition', () => import('./component/sw-flow-sequence-condition'));
Shopware.Component.register('sw-flow-sequence-selector', () => import('./component/sw-flow-sequence-selector'));
Shopware.Component.register('sw-flow-sequence-action-error', () => import('./component/sw-flow-sequence-action-error'));
Shopware.Component.register('sw-flow-rule-modal', () => import('./component/modals/sw-flow-rule-modal'));
Shopware.Component.register('sw-flow-tag-modal', () => import('./component/modals/sw-flow-tag-modal'));
Shopware.Component.register('sw-flow-set-order-state-modal', () => import('./component/modals/sw-flow-set-order-state-modal'));
Shopware.Component.register('sw-flow-generate-document-modal', () => import('./component/modals/sw-flow-generate-document-modal'));
Shopware.Component.register('sw-flow-grant-download-access-modal', () => import('./component/modals/sw-flow-grant-download-access-modal'));
Shopware.Component.register('sw-flow-mail-send-modal', () => import('./component/modals/sw-flow-mail-send-modal'));
Shopware.Component.register('sw-flow-create-mail-template-modal', () => import('./component/modals/sw-flow-create-mail-template-modal'));
Shopware.Component.register('sw-flow-event-change-confirm-modal', () => import('./component/modals/sw-flow-event-change-confirm-modal'));
Shopware.Component.register('sw-flow-change-customer-group-modal', () => import('./component/modals/sw-flow-change-customer-group-modal'));
Shopware.Component.register('sw-flow-change-customer-status-modal', () => import('./component/modals/sw-flow-change-customer-status-modal'));
Shopware.Component.register('sw-flow-set-entity-custom-field-modal', () => import('./component/modals/sw-flow-set-entity-custom-field-modal'));
Shopware.Component.register('sw-flow-affiliate-and-campaign-code-modal', () => import('./component/modals/sw-flow-affiliate-and-campaign-code-modal'));
Shopware.Component.register('sw-flow-app-action-modal', () => import('./component/modals/sw-flow-app-action-modal'));
Shopware.Component.register('sw-flow-leave-page-modal', () => import('./component/modals/sw-flow-leave-page-modal'));
/* eslint-enable max-len, sw-deprecation-rules/private-feature-declarations */

/**
 * @private
 * @package business-ops
 */
Module.register('sw-flow', {
    type: 'core',
    name: 'flow',
    title: 'sw-flow.general.mainMenuItemGeneral',
    description: 'sw-flow.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',
    entity: 'flow',

    routes: {
        index: {
            component: 'sw-flow-index',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'flow.viewer',
            },
            redirect: {
                name: 'sw.flow.index.flows',
            },
            children: {
                flows: {
                    component: 'sw-flow-list',
                    path: 'flows',
                    meta: {
                        parentPath: 'sw.settings.index',
                        privilege: 'flow.viewer',
                    },
                },
                templates: {
                    component: 'sw-flow-list-flow-templates',
                    path: 'templates',
                    meta: {
                        parentPath: 'sw.settings.index',
                        privilege: 'flow.viewer',
                    },
                },
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
        icon: 'regular-flow',
        privilege: 'flow.viewer',
    },
});
