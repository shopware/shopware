import { mount } from '@vue/test-utils';
import CustomerVatIdService from 'src/app/service/customer-vat-id.service';

/**
 * @sw-package checkout
 */

const customer = {
    id: '1',
    email: null,
    boundSalesChannelId: null,
    vatIds: ['9f8f091c-db81-4ef3-862c-9c554a34cdc4'],
};

const germany = {
    isEu: true,
    vatIdRequired: true,
    checkVatIdPattern: true,
    vatIdPattern: 'DE\\d{9}',
};

function mockEuCountries() {
    global.repositoryFactoryMock.responses.addResponse({
        method: 'Post',
        url: '/search/country',
        status: 200,
        response: {
            data: [
                {
                    id: 'at',
                    attributes: { id: 'at', isEu: true, vatIdPattern: 'ATU\\d{8}' },
                    relationships: [],
                },
                {
                    id: 'de',
                    attributes: { id: 'de', isEu: true, vatIdPattern: 'DE\\d{9}' },
                    relationships: [],
                },
            ],
            meta: { total: 2 },
        },
    });
}

function businessCustomer(vatIds, country = germany) {
    return {
        ...customer,
        accountType: 'business',
        vatIds,
        defaultBillingAddress: { country },
    };
}

async function createWrapper() {
    return mount(await wrapTestComponent('sw-customer-card', { sync: true }), {
        props: {
            customer: {},
            title: '',
        },
        global: {
            provide: {
                contextStoreService: {},
                customerVatIdService: new CustomerVatIdService(Shopware.Service('repositoryFactory')),
            },
            stubs: {
                'sw-avatar': true,
                'sw-entity-single-select': true,
                'sw-text-field': true,
                'sw-page': true,
                'sw-language-switch': true,
                'sw-customer-address-form': true,
                'sw-customer-base-form': true,
                'sw-card-view': true,
                'sw-button-process': true,
                'sw-email-field': true,
                'sw-entity-tag-select': true,
                'sw-card-section': await wrapTestComponent('sw-card-section'),
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-single-select': true,
                'sw-customer-imitate-customer-modal': true,
                'sw-label': true,
                'sw-extension-component-section': true,
                'sw-ai-copilot-badge': true,
                'sw-context-button': true,
                'sw-loader': true,
            },
        },
    });
}

describe('module/sw-customer/page/sw-customer-card', () => {
    beforeEach(() => {
        mockEuCountries();
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('should exclude the default salutation from selectable salutations', async () => {
        const wrapper = await createWrapper();
        const criteria = wrapper.vm.salutationCriteria;
        const expectedCriteria = {
            type: 'not',
            operator: 'or',
            queries: [
                {
                    field: 'id',
                    type: 'equals',
                    value: 'ed643807c9f84cc8b50132ea3ccb1c3b',
                },
            ],
        };

        expect(criteria.filters).toContainEqual(expectedCriteria);
    });

    it('should display the account type switcher', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            editMode: true,
        });
        const accountTypeSelect = wrapper.find('.sw-customer-card__account-type-select');
        expect(accountTypeSelect.exists()).toBeTruthy();
    });

    it('should vat fields when switching to business type', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            editMode: true,
            customer: {
                ...customer,
                accountType: 'business',
            },
        });
        expect(wrapper.find('[aria-label="sw-customer.card.labelCompany"]').exists()).toBeTruthy();
        expect(wrapper.find('[aria-label="sw-customer.card.labelVatId"]').exists()).toBeTruthy();
    });

    it('should hide vat fields when switching to private type', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            editMode: true,
            customer: {
                ...customer,
                accountType: 'private',
            },
        });

        expect(wrapper.find('[label="sw-customer.card.labelVatId"]').exists()).toBeFalsy();
    });

    it('should mark the VAT ID as required when the billing country requires it', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            editMode: true,
            customer: businessCustomer(['DE123456789']),
        });

        expect(wrapper.vm.isVatIdRequired).toBe(true);

        await wrapper.setProps({
            customer: businessCustomer(['DE123456789'], { ...germany, vatIdRequired: false }),
        });

        expect(wrapper.vm.isVatIdRequired).toBe(false);
    });

    it.each([
        [
            'a missing required VAT ID',
            [],
            germany,
            'sw-customer.card.warningVatIdRequired',
        ],
        [
            'a malformed VAT ID',
            ['DE12345'],
            germany,
            'sw-customer.card.warningVatIdFormatNotCorrect',
        ],
        [
            'a malformed VAT ID without format check',
            ['DE12345'],
            { ...germany, checkVatIdPattern: false },
            'sw-customer.card.warningVatIdFormatUnusual',
        ],
    ])('should warn about %s in view mode', async (_, vatIds, country, expectedWarning) => {
        const wrapper = await createWrapper();
        await wrapper.setProps({ customer: businessCustomer(vatIds, country) });
        await flushPromises();

        expect(wrapper.find('.sw-customer-card__vat-id-warning').text()).toBe(expectedWarning);
    });

    it.each([
        [
            'a VAT ID of the billing country',
            businessCustomer(['DE123456789']),
        ],
        [
            'a VAT ID of another member state',
            businessCustomer(['ATU12345678']),
        ],
        [
            'a private customer',
            { ...businessCustomer([]), accountType: 'private' },
        ],
    ])('should not warn about %s', async (_, customerData) => {
        const wrapper = await createWrapper();
        await wrapper.setProps({ customer: customerData });
        await flushPromises();

        expect(wrapper.find('.sw-customer-card__vat-id-warning').exists()).toBe(false);
    });

    it('should remove the VAT ID error once the VAT ID changes', async () => {
        const wrapper = await createWrapper();
        const customerData = { ...businessCustomer([]), getEntityName: () => 'customer' };
        await wrapper.setProps({ editMode: true, customer: customerData });

        Shopware.Store.get('error').addApiError({
            expression: `customer.${customer.id}.vatIds`,
            error: new Shopware.Classes.ShopwareError({ code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3' }),
        });
        await flushPromises();
        expect(wrapper.vm.customerVatIdsError).toBeTruthy();

        wrapper.vm.customer.vatIds.push('DE123456789');
        await flushPromises();

        expect(wrapper.vm.customerVatIdsError).toBeNull();
    });

    it('should not warn about a VAT ID of another member state while the EU patterns are unknown', async () => {
        jest.spyOn(CustomerVatIdService.prototype, 'loadEuVatIdPatterns').mockResolvedValue(null);

        const wrapper = await createWrapper();
        await wrapper.setProps({ customer: businessCustomer(['ATU12345678']) });
        await flushPromises();

        expect(wrapper.vm.euVatIdPatterns).toBeNull();
        expect(wrapper.find('.sw-customer-card__vat-id-warning').exists()).toBe(false);
    });

    it('should follow the address made the default billing address', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            customer: {
                ...businessCustomer([], { ...germany, vatIdRequired: false }),
                defaultBillingAddressId: 'address-b',
                defaultBillingAddress: { id: 'address-a', country: { ...germany, vatIdRequired: false } },
                addresses: { get: (id) => (id === 'address-b' ? { id, country: germany } : null) },
            },
        });
        await flushPromises();

        expect(wrapper.vm.isVatIdRequired).toBe(true);
        expect(wrapper.find('.sw-customer-card__vat-id-warning').text()).toBe('sw-customer.card.warningVatIdRequired');
    });
});
