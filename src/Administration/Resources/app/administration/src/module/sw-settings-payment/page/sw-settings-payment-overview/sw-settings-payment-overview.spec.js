import { mount } from '@vue/test-utils';

/**
 * @package checkout
 */

async function createWrapper(methods = [], cards = [], privileges = []) {
    if (typeof Shopware.State.get('paymentOverviewCardState') !== 'undefined') {
        Shopware.State.unregisterModule('paymentOverviewCardState');
    }

    Shopware.State.registerModule('paymentOverviewCardState', {
        namespaced: true,
        state: { cards },
    });

    return mount(await wrapTestComponent('sw-settings-payment-overview', {
        sync: true,
    }), {
        global: {
            renderStubDefaultSlot: true,
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => {
                            return Promise.resolve(methods);
                        },
                    }),
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
            },
            stubs: {
                'sw-page': {
                    template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content">CONTENT</slot>
                        <slot></slot>
                    </div>`,
                },
                'sw-button': true,
                'sw-button-process': true,
                'sw-card': true,
                'sw-card-view': true,
                'sw-context-menu-item': true,
                'sw-internal-link': true,
                'sw-alert': true,
                'sw-payment-card': true,
                'sw-empty-state': true,
                'sw-extension-component-section': true,
                'router-link': true,
                'sw-language-switch': true,
                'sw-settings-payment-sorting-modal': true,
            },
        },
    });
}

describe('module/sw-settings-payment/page/sw-settings-payment-overview', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be able to create a new payment method', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-settings-payment-overview__button-create');

        expect(createButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to create a new payment method', async () => {
        const wrapper = await createWrapper([], [], [
            'payment.creator',
        ]);
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-settings-payment-overview__button-create');

        expect(createButton.attributes().disabled).toBeFalsy();
    });

    it('should show default card if no custom card is defined', async () => {
        const wrapper = await createWrapper([
            {
                id: '1a2b3c4e',
                name: 'Test settings-payment',
            },
        ]);
        await flushPromises();

        const defaultCard = wrapper.find('sw-payment-card-stub');
        expect(defaultCard.exists()).toBeTruthy();
    });

    it('should add location if custom is defined', async () => {
        const wrapper = await createWrapper([
            {
                id: '1a2b3c4e',
                name: 'Test settings-payment',
                formattedHandlerIdentifier: 'handler',
            },
            {
                id: '5e6f7g8h',
                name: 'Test settings-payment 2',
                formattedHandlerIdentifier: 'handler2',
            },
        ], [
            {
                positionId: 'positionId',
                paymentMethodHandlers: [
                    'handler',
                    'handler2',
                ],
            },
        ]);
        await flushPromises();

        const customLocation = wrapper.find('sw-extension-component-section-stub');
        expect(customLocation.exists()).toBeTruthy();
        expect(customLocation.attributes()['position-identifier']).toBe('positionId');

        const emptyState = wrapper.find('sw-payment-card-stub');
        expect(emptyState.exists()).toBeFalsy();
    });

    it('should add location and component if custom component is defined', async () => {
        const wrapper = await createWrapper([
            {
                id: '1a2b3c4e',
                name: 'Test settings-payment',
                formattedHandlerIdentifier: 'handler',
            },
            {
                id: '5e6f7g8h',
                name: 'Test settings-payment 2',
                formattedHandlerIdentifier: 'handler2',
            },
        ], [
            {
                positionId: 'positionId',
                component: 'sw-card',
                paymentMethodHandlers: [
                    'handler',
                    'handler2',
                ],
            },
        ]);
        await flushPromises();

        const customLocation = wrapper.find('sw-extension-component-section-stub');
        expect(customLocation.exists()).toBeTruthy();
        expect(customLocation.attributes()['position-identifier']).toBe('positionId');

        const customCard = wrapper.find('sw-card-stub[payment-methods]');
        expect(customCard.exists()).toBeTruthy();

        const emptyState = wrapper.find('sw-payment-card-stub');
        expect(emptyState.exists()).toBeFalsy();
    });
});

