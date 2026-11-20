/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { MtModal, MtModalClose, MtModalAction, MtModalTrigger, MtModalRoot } from '@shopware-ag/meteor-component-library';
import SwSettingsServicesIndex from '../index';
import { useShopwareServicesStore } from '../../../store/shopware-services.store';

export function registerServiceMocks() {
    Shopware.Service().register('serviceRegistryClient', () => ({
        getCurrentRevision: jest.fn(async () => ({
            'latest-revision': '2025-06-25',
            'available-revisions': [
                {
                    revision: '2025-06-25',
                    links: {
                        'feedback-url': 'https://shopware.com/feedback',
                        'docs-url': 'https://docs.shopware.com/services',
                        'tos-url': 'https://shopware.com/agb',
                    },
                },
            ],
        })),
    }));

    Shopware.Service().register('shopwareServicesService', () => ({
        getInstalledServices: jest.fn(async () => [
            {
                id: 'service-id',
                active: true,
                name: 'first-service-name',
                label: 'First Service',
                requirements: ['service_consent'],
            },
            {
                id: 'service-id-2',
                active: true,
                name: 'second-service-name',
                label: 'Second Service',
                requirements: ['shopware_account'],
            },
        ]),
        getServicesContext: jest.fn(async () => ({
            disabled: false,
            permissionsConsent: {
                identifier: 'revision-id',
                revision: '2025-06-25',
                consentingUserId: 'user-id',
                grantedAt: '2025-07-08',
            },
        })),
        getAvailableServices: jest.fn(async () => []),
        acceptRevision: jest.fn(async () => ({
            disabled: false,
            permissionsConsent: {
                identifier: 'revision-id',
                revision: '2025-06-25',
                consentingUserId: 'user-id',
                grantedAt: '2025-07-08',
            },
        })),
        revokePermissions: jest.fn(async () => ({
            disabled: false,
            permissionsConsent: null,
        })),
        enableAllServices: jest.fn(async () => ({
            disabled: false,
            permissionsConsent: null,
        })),
    }));
}

export function resetServicesConfig() {
    Shopware.Context.app.config.services = { enabled: true, setting: 'auto' };
    Shopware.Context.app.config.settings = { ...Shopware.Context.app.config.settings, appEnv: 'prod' };
}

export async function mountPage(mocks = {}) {
    const pinia = createPinia();
    setActivePinia(pinia);
    useShopwareServicesStore();

    return mount(SwSettingsServicesIndex, {
        global: {
            mocks,
            stubs: {
                'sw-page': {
                    template: `
                    <div class="sw-page">
                        <slot name="smart-bar-header"></slot>
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content"></slot>
                    </div>`,
                },
                'sw-settings-services-service-card': true,
                'mt-modal': MtModal,
                'mt-modal-close': MtModalClose,
                'mt-modal-action': MtModalAction,
                'mt-modal-trigger': MtModalTrigger,
                'mt-modal-root': MtModalRoot,
                'sw-extension-component-section': true,
            },
            plugins: [pinia],
        },
    });
}
