/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';
import CustomerVatIdService, { type VatIdCountry } from 'src/app/service/customer-vat-id.service';

const { Context } = Shopware;
const { EntityCollection } = Shopware.Data;

const germany: VatIdCountry = {
    isEu: true,
    vatIdRequired: true,
    checkVatIdPattern: true,
    vatIdPattern: 'DE\\d{9}',
};

const saveMock = jest.fn(() => Promise.resolve());

async function createWrapper() {
    const repositoryFactory = {
        create: (entity: string) => {
            if (entity === 'customer') {
                return {
                    create: () => ({
                        id: '1',
                        addresses: new EntityCollection('/customer_address', 'customer_address', Context.api, null, []),
                    }),
                    save: saveMock,
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

            if (entity === 'salutation' || entity === 'language') {
                return { searchIds: () => Promise.resolve({ total: 1, data: ['1'] }) };
            }

            return { create: () => ({ id: 'billing-address-id', countryId: 'countryId' }) };
        },
    };

    return mount(await wrapTestComponent('sw-order-new-customer-modal', { sync: true }), {
        global: {
            stubs: {
                'sw-modal': { template: '<div><slot></slot><slot name="modal-footer"></slot></div>' },
                'sw-tabs': { template: '<div><slot name="content" active="details"></slot></div>' },
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
    await wrapper.setData({ customer: { accountType: 'business', vatIds, company: 'Shopware AG' } });

    return wrapper;
}

async function clickSave(wrapper: Awaited<ReturnType<typeof createWrapper>>) {
    await wrapper.find('[data-analytics-id="sw-order-new-customer-modal.save"]').trigger('click');
    await flushPromises();
}

describe('src/module/sw-order/component/sw-order-new-customer-modal/vat-id-validation', () => {
    beforeEach(() => {
        saveMock.mockClear();
        Shopware.Store.get('error').resetApiErrors();
    });

    it.each([
        { vatIds: [''], code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3' },
        { vatIds: ['DE12345'], code: '463d3548-1caf-11eb-adc1-0242ac120002' },
    ])('should not save a business customer with the VAT IDs $vatIds', async ({ vatIds, code }) => {
        const wrapper = await createBusinessCustomer(vatIds);

        await clickSave(wrapper);

        expect(saveMock).not.toHaveBeenCalled();
        expect(Shopware.Store.get('error').getApiErrorFromPath('customer', '1', ['vatIds'])).toEqual(
            expect.objectContaining({ code }),
        );
    });

    it('should require the VAT ID when the billing country requires it', async () => {
        const wrapper = await createBusinessCustomer([]);
        await flushPromises();

        expect(wrapper.find('sw-customer-base-form-stub').attributes('is-vat-id-required')).toBe('true');

        await wrapper.setData({ customer: { accountType: 'private' } });

        expect(wrapper.find('sw-customer-base-form-stub').attributes('is-vat-id-required')).toBe('false');
    });

    it('should save a business customer with a VAT ID of another member state', async () => {
        const wrapper = await createBusinessCustomer(['ATU12345678']);

        await clickSave(wrapper);

        expect(saveMock).toHaveBeenCalledTimes(1);
    });

    it('should remove a stale VAT ID error once the VAT IDs pass', async () => {
        const wrapper = await createBusinessCustomer([]);
        const errorStore = Shopware.Store.get('error');

        await clickSave(wrapper);
        expect(errorStore.getApiErrorFromPath('customer', '1', ['vatIds'])).not.toBeNull();

        await wrapper.setData({ customer: { vatIds: ['ATU12345678'] } });
        await clickSave(wrapper);

        expect(saveMock).toHaveBeenCalledTimes(1);
        expect(errorStore.getApiErrorFromPath('customer', '1', ['vatIds'])).toBeNull();
    });
});
