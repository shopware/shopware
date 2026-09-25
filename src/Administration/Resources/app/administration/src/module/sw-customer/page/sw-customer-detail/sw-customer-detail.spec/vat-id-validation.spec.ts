/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';
import CustomerVatIdService, { type VatIdCountry } from 'src/app/service/customer-vat-id.service';

type Address = { id: string; country: VatIdCountry };

type Customer = {
    id?: string;
    company?: string;
    accountType?: string;
    vatIds?: string[];
    defaultBillingAddressId?: string;
    defaultBillingAddress?: Address | { country: VatIdCountry };
    addresses?: { get: (id: string) => Address | null };
};

type DetailVm = {
    customer: Customer;
    onSave: () => Promise<boolean>;
    createNotificationError: jest.Mock;
    createNotificationSuccess: jest.Mock;
};

const germany: VatIdCountry = {
    isEu: true,
    vatIdRequired: true,
    checkVatIdPattern: true,
    vatIdPattern: 'DE\\d{9}',
};

const saveMock = jest.fn(() => Promise.resolve());

async function createWrapper(customer: Customer) {
    const repositoryFactory = {
        create: () => ({
            get: () => Promise.resolve({ id: '1', accountType: 'private' }),
            search: () =>
                Promise.resolve([
                    { vatIdPattern: 'ATU\\d{8}' },
                    { vatIdPattern: 'DE\\d{9}' },
                ]),
            searchIds: () => Promise.resolve({ total: 1, data: ['salutationId'] }),
            save: saveMock,
        }),
    };

    const wrapper = mount(await wrapTestComponent('sw-customer-detail', { sync: true }), {
        shallow: true,
        props: {
            customerId: 'customerId',
        },
        global: {
            mocks: {
                $route: { name: 'sw.customer.detail.base', query: { edit: true } },
                $router: { push: jest.fn() },
            },
            provide: {
                repositoryFactory,
                customerVatIdService: new CustomerVatIdService(
                    repositoryFactory as unknown as ConstructorParameters<typeof CustomerVatIdService>[0],
                ),
                acl: { can: () => true },
                customerGroupRegistrationService: {},
                customerValidationService: {},
                feature: { isActive: () => false },
            },
        },
    });
    await flushPromises();

    const vm = wrapper.vm as unknown as DetailVm;
    vm.createNotificationError = jest.fn();
    vm.createNotificationSuccess = jest.fn();
    await wrapper.setData({ editMode: true, customer: { id: '1', company: 'Shopware AG', ...customer } });

    return { wrapper, vm };
}

function businessCustomer(vatIds: string[], country: VatIdCountry = germany): Customer {
    return { accountType: 'business', vatIds, defaultBillingAddress: { country } };
}

describe('module/sw-customer/page/sw-customer-detail/vat-id-validation', () => {
    beforeEach(() => {
        saveMock.mockClear();
        Shopware.Store.get('error').resetApiErrors();
    });

    it.each([
        { label: 'a missing required VAT ID', vatIds: [''], code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3' },
        { label: 'a malformed VAT ID', vatIds: ['DE12345'], code: '463d3548-1caf-11eb-adc1-0242ac120002' },
    ])('should not save a business customer with $label', async ({ vatIds, code }) => {
        const { vm } = await createWrapper(businessCustomer(vatIds));

        await expect(vm.onSave()).resolves.toBe(false);

        expect(saveMock).not.toHaveBeenCalled();
        expect(Shopware.Store.get('error').getApiErrorFromPath('customer', '1', ['vatIds'])).toEqual(
            expect.objectContaining({ code }),
        );
        expect(vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-customer.detail.messageSaveError',
        });
    });

    it.each([
        {
            label: 'a VAT ID of the billing country',
            customer: businessCustomer([
                ' DE123456789 ',
                '',
            ]),
            saved: ['DE123456789'],
        },
        { label: 'a VAT ID of another member state', customer: businessCustomer(['ATU12345678']), saved: ['ATU12345678'] },
        {
            label: 'a malformed VAT ID without format check',
            customer: businessCustomer(['DE1'], { ...germany, checkVatIdPattern: false }),
            saved: ['DE1'],
        },
        {
            label: 'no VAT ID when none is required',
            customer: businessCustomer([], { ...germany, vatIdRequired: false }),
            saved: [],
        },
        { label: 'no billing country', customer: { accountType: 'business', vatIds: [] }, saved: [] },
    ])('should save a business customer with $label', async ({ customer, saved }) => {
        const { vm } = await createWrapper(customer);

        await vm.onSave();

        expect(saveMock).toHaveBeenCalledTimes(1);
        expect(vm.customer.vatIds).toEqual(saved);
    });

    it('should ignore the VAT ID rules of the billing country for a private customer', async () => {
        const { vm } = await createWrapper({ ...businessCustomer(['DE12345']), accountType: 'private' });

        await vm.onSave();

        expect(saveMock).toHaveBeenCalledTimes(1);
        expect(vm.customer.vatIds).toEqual([]);
    });

    it.each([
        {
            label: 'requires a VAT ID',
            loadedCountry: { ...germany, vatIdRequired: false },
            newCountry: germany,
            saved: false,
        },
        {
            label: 'does not require a VAT ID',
            loadedCountry: germany,
            newCountry: { ...germany, vatIdRequired: false },
            saved: true,
        },
    ])(
        'should validate against the address made the default billing address, which $label',
        async ({ loadedCountry, newCountry, saved }) => {
            const { vm } = await createWrapper({
                accountType: 'business',
                vatIds: [],
                defaultBillingAddressId: 'address-b',
                defaultBillingAddress: { id: 'address-a', country: loadedCountry },
                addresses: { get: (id) => (id === 'address-b' ? { id, country: newCountry } : null) },
            });

            await expect(vm.onSave()).resolves.toBe(saved ? undefined : false);

            expect(saveMock).toHaveBeenCalledTimes(saved ? 1 : 0);
        },
    );

    it('should remove a stale VAT ID error once the VAT IDs pass', async () => {
        const { vm } = await createWrapper(businessCustomer(['ATU12345678']));
        const errorStore = Shopware.Store.get('error');

        errorStore.addApiError({
            expression: 'customer.1.vatIds',
            error: new Shopware.Classes.ShopwareError({ code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3' }),
        });

        await vm.onSave();

        expect(saveMock).toHaveBeenCalledTimes(1);
        expect(errorStore.getApiErrorFromPath('customer', '1', ['vatIds'])).toBeNull();
    });
});
