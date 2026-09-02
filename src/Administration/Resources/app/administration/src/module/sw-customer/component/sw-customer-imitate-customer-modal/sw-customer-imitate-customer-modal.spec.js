import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

const responses = global.repositoryFactoryMock.responses;

function domain(id, salesChannelId, url) {
    return {
        id,
        type: 'sales_channel_domain',
        attributes: {
            id,
            salesChannelId,
            salesChannel: {
                translated: {
                    name: 'Test sales channel',
                },
            },
            url,
        },
        relationships: {},
    };
}

function mockSalesChannelDomains(domains) {
    responses.addResponse({
        method: 'Post',
        url: '/search/sales-channel-domain',
        status: 200,
        response: {
            data: domains,
        },
    });
}

async function createWrapper(contextStoreServiceOverrides = {}) {
    return mount(
        await wrapTestComponent('sw-customer-imitate-customer-modal', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-modal': await wrapTestComponent('sw-modal', {
                        sync: true,
                    }),
                    'sw-container': await wrapTestComponent('sw-container'),
                    'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
                    'i18n-t': {
                        template: '<div class="i18n-stub"><slot></slot></div>',
                    },
                    'sw-loader': true,
                    'router-link': true,
                },
                provide: {
                    shortcutService: {
                        stopEventListener: () => {},
                        startEventListener: () => {},
                    },
                    contextStoreService: {
                        generateImitateCustomerToken: async () => ({
                            token: 'a-token',
                        }),
                        redirectToSalesChannelUrl: () => {},
                        ...contextStoreServiceOverrides,
                    },
                },
            },
            props: {
                customer: {
                    id: 'customer-id',
                    email: null,
                    boundSalesChannelId: null,
                },
            },
        },
    );
}

describe('module/sw-customer-imitate-customer-modal', () => {
    let wrapper;

    beforeEach(() => {
        mockSalesChannelDomains([
            domain('sales-channel-domain-id', 'sales-channel-id', 'http://localhost:8000'),
        ]);
    });

    it('should fetch all sales channel domains', async () => {
        wrapper = await createWrapper();

        await flushPromises();

        expect(wrapper.vm.salesChannelDomains).toHaveLength(1);
    });

    it('should forward modal close', async () => {
        wrapper = await createWrapper();

        const closeButton = await wrapper.find('.sw-modal__close');
        expect(closeButton.exists()).toBe(true);

        await closeButton.trigger('click');

        await flushPromises();

        expect(wrapper.emitted('modal-close')).toBeDefined();
    });

    it('should generate the imitation token upfront, before any item is clicked', async () => {
        wrapper = await createWrapper();

        const generateTokenSpy = jest.spyOn(wrapper.vm.contextStoreService, 'generateImitateCustomerToken');

        await flushPromises();

        expect(generateTokenSpy).toHaveBeenCalledWith('customer-id', 'sales-channel-id');
        expect(wrapper.vm.imitateCustomerTokens).toEqual({ 'sales-channel-id': 'a-token' });
    });

    it('should generate one token per sales channel, not per domain', async () => {
        mockSalesChannelDomains([
            domain('domain-de', 'sales-channel-id', 'http://localhost:8000/de'),
            domain('domain-en', 'sales-channel-id', 'http://localhost:8000/en'),
            domain('domain-other', 'other-sales-channel-id', 'http://other.localhost:8000'),
        ]);

        wrapper = await createWrapper();

        const generateTokenSpy = jest.spyOn(wrapper.vm.contextStoreService, 'generateImitateCustomerToken');

        await flushPromises();

        expect(generateTokenSpy).toHaveBeenCalledTimes(2);
        expect(generateTokenSpy).toHaveBeenCalledWith('customer-id', 'sales-channel-id');
        expect(generateTokenSpy).toHaveBeenCalledWith('customer-id', 'other-sales-channel-id');
    });

    it('should redirect synchronously within the click handler, so the popup blocker allows the new tab', async () => {
        wrapper = await createWrapper();

        const redirectSalesChannelSpy = jest.spyOn(wrapper.vm.contextStoreService, 'redirectToSalesChannelUrl');

        await flushPromises();

        const item = await wrapper.find('.imitate-customer-modal-item');
        expect(item.exists()).toBe(true);

        item.element.dispatchEvent(new MouseEvent('click'));

        expect(redirectSalesChannelSpy).toHaveBeenCalledWith('http://localhost:8000', 'a-token', 'customer-id', undefined);
    });

    it('should notify the user when no token could be generated', async () => {
        wrapper = await createWrapper({
            generateImitateCustomerToken: async () => {
                throw new Error('token generation failed');
            },
        });

        const redirectSalesChannelSpy = jest.spyOn(wrapper.vm.contextStoreService, 'redirectToSalesChannelUrl');
        wrapper.vm.createNotificationError = jest.fn();

        await flushPromises();

        const item = await wrapper.find('.imitate-customer-modal-item');
        expect(item.exists()).toBe(true);

        await item.trigger('click');
        await flushPromises();

        expect(redirectSalesChannelSpy).not.toHaveBeenCalled();
        expect(wrapper.vm.createNotificationError).toHaveBeenCalled();
    });

    it('should show the loading state until the domains and tokens are resolved', async () => {
        wrapper = await createWrapper();

        expect(wrapper.vm.isLoading).toBe(true);
        expect(wrapper.find('.imitate-customer-modal-item').exists()).toBe(false);

        await flushPromises();

        expect(wrapper.vm.isLoading).toBe(false);
        expect(wrapper.find('.imitate-customer-modal-item').exists()).toBe(true);
    });
});
