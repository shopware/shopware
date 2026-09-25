/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';
import CustomerVatIdService, { type VatIdCountry } from 'src/app/service/customer-vat-id.service';

const { Context } = Shopware;
const { EntityCollection } = Shopware.Data;

type ModalVm = {
    customer: { id: string; accountType?: string; vatIds?: string[]; company?: string };
    billingAddress: { countryId: string | null };
    isVatIdRequired: boolean;
    validVatIdField: () => Promise<boolean>;
    validateEmail: jest.Mock;
    createNotificationError: jest.Mock;
    saveCustomer: () => Promise<unknown>;
    onSave: () => Promise<boolean>;
};

const germany: VatIdCountry = {
    isEu: true,
    vatIdRequired: true,
    checkVatIdPattern: true,
    vatIdPattern: 'DE\\d{9}',
};

async function createWrapper() {
    const repositoryFactory = {
        create: (entity: string) => {
            if (entity === 'customer') {
                return {
                    create: () => ({
                        id: '1',
                        addresses: new EntityCollection('/customer_address', 'customer_address', Context.api, null, []),
                    }),
                };
            }

            if (entity === 'country') {
                return {
                    get: () => Promise.resolve(germany),
                    search: () =>
                        Promise.resolve([
                            { vatIdPattern: 'ATU\\d{8}' },
                            { vatIdPattern: 'DE\\d{9}' },
                        ]),
                };
            }

            if (entity === 'salutation') {
                return { searchIds: () => Promise.resolve({ total: 1, data: ['salutationId'] }) };
            }

            return { create: () => ({ id: 'billing-address-id' }) };
        },
    };

    return mount(await wrapTestComponent('sw-order-new-customer-modal', { sync: true }), {
        global: {
            stubs: {
                'sw-modal': { template: '<div><slot></slot><slot name="modal-footer"></slot></div>' },
                'sw-tabs': { template: '<div><slot name="content" active="customer"></slot></div>' },
                'mt-tabs': true,
                'sw-customer-address-form': true,
                'sw-customer-base-form': true,
                'sw-extension-component-section': true,
                'sw-loader': true,
            },
            provide: {
                repositoryFactory,
                customerVatIdService: new CustomerVatIdService(
                    repositoryFactory as unknown as ConstructorParameters<typeof CustomerVatIdService>[0],
                ),
                numberRangeService: {},
                systemConfigApiService: { getValues: () => Promise.resolve({}) },
                customerValidationService: {},
                feature: { isActive: () => false },
            },
        },
    });
}

async function createBusinessCustomer(vatIds: string[]) {
    const wrapper = await createWrapper();
    await flushPromises();

    const vm = wrapper.vm as unknown as ModalVm;
    Object.assign(vm.customer, { accountType: 'business', vatIds });
    vm.billingAddress.countryId = 'countryId';

    return vm;
}

describe('src/module/sw-order/component/sw-order-new-customer-modal/vat-id-validation', () => {
    beforeEach(() => {
        Shopware.Store.get('error').resetApiErrors();
    });

    it.each([
        { vatIds: [''], code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3' },
        { vatIds: ['DE12345'], code: '463d3548-1caf-11eb-adc1-0242ac120002' },
    ])('should reject the VAT IDs $vatIds of a business customer', async ({ vatIds, code }) => {
        const vm = await createBusinessCustomer(vatIds);

        await expect(vm.validVatIdField()).resolves.toBe(false);
        expect(Shopware.Store.get('error').getApiErrorFromPath('customer', '1', ['vatIds'])).toEqual(
            expect.objectContaining({ code }),
        );
    });

    it('should require the VAT ID when the billing country requires it', async () => {
        const vm = await createBusinessCustomer([]);
        await flushPromises();

        expect(vm.isVatIdRequired).toBe(true);

        vm.customer.accountType = 'private';

        expect(vm.isVatIdRequired).toBe(false);
    });

    it('should accept a VAT ID of another member state for a business customer', async () => {
        const vm = await createBusinessCustomer(['ATU12345678']);

        await expect(vm.validVatIdField()).resolves.toBe(true);
    });

    it('should remove a stale VAT ID error once the VAT IDs pass', async () => {
        const vm = await createBusinessCustomer([]);
        const errorStore = Shopware.Store.get('error');

        await expect(vm.validVatIdField()).resolves.toBe(false);
        expect(errorStore.getApiErrorFromPath('customer', '1', ['vatIds'])).not.toBeNull();

        vm.customer.vatIds = ['ATU12345678'];
        await expect(vm.validVatIdField()).resolves.toBe(true);

        expect(errorStore.getApiErrorFromPath('customer', '1', ['vatIds'])).toBeNull();
    });

    it('should not save a business customer with a malformed VAT ID', async () => {
        const vm = await createBusinessCustomer(['DE12345']);
        vm.createNotificationError = jest.fn();
        vm.validateEmail = jest.fn(() => Promise.resolve({ isValid: true }));
        vm.customer.company = 'Shopware AG';
        const saveCustomerSpy = jest.spyOn(vm, 'saveCustomer');

        await expect(vm.onSave()).resolves.toBe(false);
        expect(saveCustomerSpy).not.toHaveBeenCalled();
    });
});
