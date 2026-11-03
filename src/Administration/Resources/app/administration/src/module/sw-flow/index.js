import './service';
import './acl';
import './store/flow.store';
import defaultSearchConfiguration from './default-search-configuration';
import './component/modals/sw-flow-affiliate-and-campaign-code-modal/register';
import './component/modals/sw-flow-app-action-modal/register';
import './component/modals/sw-flow-change-customer-group-modal/register';
import './component/modals/sw-flow-change-customer-status-modal/register';
import './component/modals/sw-flow-create-mail-template-modal/register';
import './component/modals/sw-flow-event-change-confirm-modal/register';
import './component/modals/sw-flow-generate-document-modal/register';
import './component/modals/sw-flow-grant-download-access-modal/register';
import './component/modals/sw-flow-leave-page-modal/register';
import './component/modals/sw-flow-mail-send-modal/register';
import './component/modals/sw-flow-rule-modal/register';
import './component/modals/sw-flow-set-entity-custom-field-modal/register';
import './component/modals/sw-flow-set-order-state-modal/register';
import './component/modals/sw-flow-tag-modal/register';
import './component/sw-flow-sequence/register';
import './component/sw-flow-sequence-action/register';
import './component/sw-flow-sequence-action-error/register';
import './component/sw-flow-sequence-condition/register';
import './component/sw-flow-sequence-modal/register';
import './component/sw-flow-sequence-selector/register';
import './component/sw-flow-trigger/register';
import './page/sw-flow-detail/register';
import './page/sw-flow-index/register';
import './view/detail/sw-flow-detail-flow/register';
import './view/detail/sw-flow-detail-general/register';
import './view/listing/sw-flow-list/register';
import './view/listing/sw-flow-list-flow-templates/register';

const { Module } = Shopware;

/**
 * @private
 * @sw-package after-sales
 */
Module.register('sw-flow', {
    type: 'core',
    name: 'flow',
    title: 'sw-flow.general.mainMenuItemGeneral',
    description: 'sw-flow.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-red-default)',
    icon: 'regular-flow',
    favicon: 'icon-module-settings.svg',
    entity: 'flow',
    defaultSearchConfiguration,

    routes: {
        index: {
            component: 'sw-flow-index',
            path: 'index',
            meta: {
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
                        privilege: 'flow.viewer',
                    },
                },
                templates: {
                    component: 'sw-flow-list-flow-templates',
                    path: 'templates',
                    meta: {
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
                        flowId: route.params.id.toLowerCase(),
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
            path: 'create/:flowTemplateId?',
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

    navigation: [
        {
            id: 'sw-flow',
            label: 'sw-flow.general.mainMenuItemGeneral',
            path: 'sw.flow.index',
            icon: 'regular-flow',
            color: 'var(--sw-color-module-red-default)',
            parent: 'sw-automation',
            privilege: 'flow.viewer',
            position: 20,
        },
    ],

    settingsItem: {
        group: 'automation',
        to: 'sw.flow.index',
        icon: 'regular-flow',
        privilege: 'flow.viewer',
    },
});
