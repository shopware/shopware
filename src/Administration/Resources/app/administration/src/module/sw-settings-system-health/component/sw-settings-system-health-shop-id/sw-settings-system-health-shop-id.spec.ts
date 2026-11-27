/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { SystemHealthCheck } from '../../types';

const check: SystemHealthCheck = {
    name: 'ShopId',
    category: 'EXTERNAL',
    tags: ['apps'],
    result: {
        status: 'ERROR',
        healthy: false,
        message: 'Shop identity changed',
        extra: {
            fingerprints: [
                {
                    identifier: 'app_url',
                    storedValue: 'https://old.example',
                    currentValue: 'https://new.example',
                    matches: false,
                },
                { identifier: 'installation_path', storedValue: '/var/www', currentValue: '/var/www', matches: true },
            ],
            appsOnHold: ['Acme Payments'],
        },
        checkedAt: '2026-11-24T10:00:00+00:00',
    },
};

async function createWrapper(checkShopId = jest.fn(() => Promise.resolve({ fingerprints: {} }))) {
    return mount(await wrapTestComponent('sw-settings-system-health-shop-id', { sync: true }), {
        props: { check },
        global: {
            provide: { shopIdChangeService: { checkShopId } },
            stubs: {
                'sw-settings-system-health-check-details': {
                    props: ['summary', 'impact'],
                    template:
                        '<div><p class="summary">{{ summary }}</p><p class="impact">{{ impact }}</p><slot name="action"></slot><slot></slot></div>',
                },
                'sw-data-grid': { props: ['dataSource'], template: '<div class="grid">{{ dataSource.length }}</div>' },
                'sw-app-shop-id-change-modal': { template: '<div class="shop-id-modal"></div>' },
            },
        },
    });
}

describe('module/sw-settings-system-health/component/sw-settings-system-health-shop-id', () => {
    it('should explain the changed fingerprint and name the apps on hold', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.summary').text()).toBe('sw-settings-system-health.shop-id.changed.app_url');
        expect(wrapper.find('.impact').text()).toBe('sw-settings-system-health.shop-id.apps-on-hold');
        expect(wrapper.find('.grid').text()).toBe('2');
    });

    it('should open the shop ID change dialog to resolve it', async () => {
        const checkShopId = jest.fn(() => Promise.resolve({ fingerprints: {} }));
        const wrapper = await createWrapper(checkShopId);

        await wrapper.find('.mt-button').trigger('click');
        await flushPromises();

        expect(checkShopId).toHaveBeenCalled();
        expect(wrapper.find('.shop-id-modal').exists()).toBe(true);
    });
});
