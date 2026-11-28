import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

const responses = global.repositoryFactoryMock.responses;
const clientMock = global.repositoryFactoryMock.clientMock;

function domain(id, salesChannelId, url, { salesChannelName = 'Test sales channel', languageName = 'English' } = {}) {
    return {
        id,
        type: 'sales_channel_domain',
        attributes: {
            id,
            salesChannelId,
            salesChannel: {
                id: salesChannelId,
                translated: {
                    name: salesChannelName,
                },
            },
            language: {
                name: languageName,
            },
            url,
        },
        relationships: {},
    };
}

function twoSalesChannelDomains() {
    return [
        domain('domain-b', 'sales-channel-b', 'http://b.test', { salesChannelName: 'B-Shop' }),
        domain('domain-a-de', 'sales-channel-a', 'http://a.test/de', { salesChannelName: 'A-Shop', languageName: 'Deutsch' }),
        domain('domain-a-en', 'sales-channel-a', 'http://a.test/en', { salesChannelName: 'A-Shop' }),
    ];
}

function salesChannelDomainSearchRequest() {
    const request = clientMock.history.post.find(({ url }) => url === '/search/sales-channel-domain');

    return JSON.parse(request.data);
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

async function createWrapper({ contextStoreService = {}, customer = {} } = {}) {
    return mount(
        await wrapTestComponent('sw-customer-imitate-customer-modal', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'mt-modal-root': {
                        props: ['isOpen'],
                        emits: ['change'],
                        template: '<div class="mt-modal-root-stub"><slot></slot></div>',
                    },
                    'mt-modal': {
                        props: ['title', 'subtitle'],
                        template:
                            '<div class="mt-modal-stub"><h2>{{ title }}</h2><p class="mt-modal-stub__subtitle">{{ subtitle }}</p><slot></slot><slot name="footer"></slot></div>',
                    },
                    'mt-select': {
                        name: 'mt-select',
                        props: {
                            modelValue: null,
                            options: null,
                            label: null,
                            placeholder: null,
                            hideClearableButton: Boolean,
                        },
                        emits: ['update:modelValue'],
                        template: '<div class="mt-select-stub"></div>',
                    },
                    'mt-modal-close': {
                        template: '<button class="mt-modal-close-stub"><slot></slot></button>',
                    },
                    'mt-loader': {
                        template: '<div class="mt-loader-stub"></div>',
                    },
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
                        ...contextStoreService,
                    },
                },
            },
            props: {
                customer: {
                    id: 'customer-id',
                    firstName: 'Maxwell',
                    lastName: 'Bartell',
                    email: null,
                    boundSalesChannelId: null,
                    ...customer,
                },
            },
        },
    );
}

describe('module/sw-customer-imitate-customer-modal', () => {
    let wrapper;

    beforeEach(() => {
        mockSalesChannelDomains([domain('sales-channel-domain-id', 'sales-channel-id', 'http://localhost:8000')]);
    });

    it('should fetch all sales channel domains', async () => {
        wrapper = await createWrapper();

        await flushPromises();

        expect(wrapper.vm.salesChannelDomains).toHaveLength(1);
    });

    it('should forward modal close', async () => {
        wrapper = await createWrapper();

        wrapper.findComponent('.mt-modal-root-stub').vm.$emit('change', false);

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

    it('should render each domain as a button, so it can be reached and selected with the keyboard', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const item = wrapper.find('.imitate-customer-modal-item');

        expect(item.element.tagName).toBe('BUTTON');
        expect(item.attributes('type')).toBe('button');
        expect(item.find('.imitate-customer-modal-item-header').text()).toBe('English');
        expect(item.find('.imitate-customer-modal-item-url').text()).toBe('http://localhost:8000');
        expect(item.find('.imitate-customer-modal-item-content div').exists()).toBe(false);
    });

    it('should show the customer name as subtitle of the "Log in as customer" modal', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.mt-modal-stub h2').text()).toBe('sw-customer.imitateCustomerModal.modalTitle');
        expect(wrapper.find('.mt-modal-stub__subtitle').text()).toBe('Maxwell Bartell');
    });

    it('should offer the sales channels of the domains in a select, sorted by name, and show no domains yet', async () => {
        mockSalesChannelDomains(twoSalesChannelDomains());

        wrapper = await createWrapper();
        await flushPromises();

        const select = wrapper.findComponent({ name: 'mt-select' });

        expect(select.props('options')).toEqual([
            { value: 'sales-channel-a', label: 'A-Shop' },
            { value: 'sales-channel-b', label: 'B-Shop' },
        ]);
        expect(select.props('hideClearableButton')).toBe(true);
        expect(select.props('modelValue')).toBeNull();
        expect(wrapper.find('.imitate-customer-modal-domains-headline').exists()).toBe(false);
        expect(wrapper.find('.imitate-customer-modal-item').exists()).toBe(false);
    });

    it('should show only the domains of the selected sales channel', async () => {
        mockSalesChannelDomains(twoSalesChannelDomains());

        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.findComponent({ name: 'mt-select' }).vm.$emit('update:modelValue', 'sales-channel-a');

        const items = wrapper.findAll('.imitate-customer-modal-item');

        expect(items.map((item) => item.find('.imitate-customer-modal-item-header').text())).toEqual(['Deutsch', 'English']);
        expect(items.map((item) => item.find('.imitate-customer-modal-item-url').text())).toEqual([
            'http://a.test/de',
            'http://a.test/en',
        ]);
    });

    it('should preselect the sales channel when there is only one', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.findComponent({ name: 'mt-select' }).props('modelValue')).toBe('sales-channel-id');
        expect(wrapper.findAll('.imitate-customer-modal-item')).toHaveLength(1);
    });

    it('should show the language and the URL of each domain under a "Domains" headline', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.imitate-customer-modal-domains-headline').text()).toBe(
            'sw-customer.imitateCustomerModal.labelDomains',
        );

        const item = wrapper.find('.imitate-customer-modal-item');

        expect(item.find('.imitate-customer-modal-item-header').text()).toBe('English');
        expect(item.find('.imitate-customer-modal-item-url').text()).toBe('http://localhost:8000');
    });

    it('should load the language of each domain with the domain query', async () => {
        clientMock.resetHistory();

        wrapper = await createWrapper();
        await flushPromises();

        expect(salesChannelDomainSearchRequest().associations).toHaveProperty('language');
    });

    it('should only load the domains of the sales channel a bound customer belongs to', async () => {
        clientMock.resetHistory();

        wrapper = await createWrapper({ customer: { boundSalesChannelId: 'sales-channel-id' } });
        await flushPromises();

        expect(salesChannelDomainSearchRequest().filter).toContainEqual({
            type: 'equals',
            field: 'salesChannelId',
            value: 'sales-channel-id',
        });
    });

    it('should notify the user when no token could be generated', async () => {
        wrapper = await createWrapper({
            contextStoreService: {
                generateImitateCustomerToken: async () => {
                    throw new Error('token generation failed');
                },
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
        expect(wrapper.find('.mt-loader-stub').exists()).toBe(true);
        expect(wrapper.find('.imitate-customer-modal-item').exists()).toBe(false);

        await flushPromises();

        expect(wrapper.vm.isLoading).toBe(false);
        expect(wrapper.find('.mt-loader-stub').exists()).toBe(false);
        expect(wrapper.find('.imitate-customer-modal-item').exists()).toBe(true);
    });
});
