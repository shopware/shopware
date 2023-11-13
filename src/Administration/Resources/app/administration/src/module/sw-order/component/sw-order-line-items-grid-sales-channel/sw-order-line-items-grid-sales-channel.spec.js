import { shallowMount, createLocalVue } from '@vue/test-utils';
import swOrderState from 'src/module/sw-order/state/order.store';
import swOrderLineItemsGridSalesChannel from 'src/module/sw-order/component/sw-order-line-items-grid-sales-channel';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/base/sw-button';

/**
 * @package checkout
 */

Shopware.Component.register('sw-order-line-items-grid-sales-channel', swOrderLineItemsGridSalesChannel);

const mockItems = [
    {
        id: '1',
        type: 'product',
        label: 'Product item',
        quantity: 1,
        payload: {
            options: [],
        },
        price: {
            quantity: 1,
            totalPrice: 200,
            unitPrice: 200,
            calculatedTaxes: [
                {
                    price: 200,
                    tax: 40,
                    taxRate: 20,
                },
            ],
            taxRules: [
                {
                    taxRate: 20,
                    percentage: 100,
                },
            ],
        },
    },
    {
        id: '2',
        type: 'custom',
        label: 'Custom item',
        quantity: 1,
        payload: [],
        price: {
            quantity: 1,
            totalPrice: 100,
            unitPrice: 100,
            calculatedTaxes: [
                {
                    price: 100,
                    tax: 10,
                    taxRate: 10,
                },
            ],
            taxRules: [
                {
                    taxRate: 10,
                    percentage: 100,
                },
            ],
        },
    },
    {
        id: '3',
        type: 'credit',
        label: 'Credit item',
        quantity: 1,
        payload: [],
        price: {
            quantity: 1,
            totalPrice: -100,
            unitPrice: -100,
            calculatedTaxes: [
                {
                    price: -100,
                    tax: -10,
                    taxRate: 10,
                },
            ],
            taxRules: [
                {
                    taxRate: 10,
                    percentage: 100,
                },
            ],
        },
    },
];

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/search/user-config',
    status: 200,
    response: {
        data: [],
    },
});

const mockMultipleTaxesItem = {
    ...mockItems[2],
    price: {
        ...mockItems[2].price,
        calculatedTaxes: [
            {
                price: -66.66,
                tax: -13.33,
                taxRate: 20,
            },
            {
                price: -33.33,
                tax: -3.33,
                taxRate: 10,
            },
        ],
        taxRules: [
            {
                taxRate: 20,
                percentage: 66.66,
            },
            {
                taxRate: 10,
                percentage: 33.33,
            },
        ],
    },
};

async function createWrapper() {
    const localVue = createLocalVue();

    localVue.directive('tooltip', {
        bind(el, binding) {
            el.setAttribute('tooltip-message', binding.value.message);
        },
        inserted(el, binding) {
            el.setAttribute('tooltip-message', binding.value.message);
        },
        update(el, binding) {
            el.setAttribute('tooltip-message', binding.value.message);
        },
    });

    return shallowMount(await Shopware.Component.build('sw-order-line-items-grid-sales-channel'), {
        localVue,
        propsData: {
            cart: {
                token: '6d3960ff30c9413f8dde62ccda81eefd',
                lineItems: [],
                price: {
                    taxStatus: 'net',
                },
            },
            currency: {
                shortName: 'EUR',
                symbol: '€',
            },
            salesChannelId: '',
        },
        stubs: {
            'sw-container': true,
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-button-group': {
                template: '<div class="sw-button-group"><slot></slot></div>',
            },
            'sw-context-button': {
                template: '<div class="sw-context-button"><slot></slot></div>',
            },
            'sw-context-menu-item': {
                template: '<div class="sw-context-menu-item" @click="$emit(\'click\')"><slot></slot></div>',
            },
            'sw-checkbox-field': {
                template: '<input class="sw-checkbox-field" type="checkbox" :checked="value" @change="$emit(\'change\', $event.target.value)" />',
                props: ['value'],
            },
            'sw-number-field': {
                template: '<input class="sw-number-field" type="number" :value="value" @input="$emit(\'change\', Number($event.target.value))" />',
                props: {
                    value: 0,
                },
            },
            'sw-card-filter': true,
            'sw-data-grid': await Shopware.Component.build('sw-data-grid'),
            'sw-product-variant-info': true,
            'sw-order-product-select': {
                template: '<input class="sw-order-product-select" :value="item.label" @input="updateLabel" />',
                props: {
                    item: {},
                },
                methods: {
                    updateLabel(event) {
                        this.item.label = event.target.value;
                    },
                },
            },
            'router-link': true,
            'sw-empty-state': true,
            'sw-icon': true,
        },
        mocks: {
            $tc: (t, count, value) => {
                if (t === 'sw-order.createBase.taxDetail') {
                    return `${value.taxRate}%: ${value.tax}`;
                }

                return t;
            },
        },
    });
}

describe('src/module/sw-order/component/sw-order-line-items-grid-sales-channel', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swOrder', swOrderState);
        Shopware.Service().register('cartStoreService', () => {
            return {
                getLineItemTypes: () => {
                    return Object.freeze({
                        PRODUCT: 'product',
                        CREDIT: 'credit',
                        CUSTOM: 'custom',
                        PROMOTION: 'promotion',
                    });
                },
                getCart: () => {
                    return Promise.resolve({ data: { lineItems: [] } });
                },
            };
        });
        Shopware.Service().register('contextStoreService', () => {
            return {
                getSalesChannelContext: () => {
                    return Promise.resolve({ data: {} });
                },
            };
        });
    });

    it('should show empty state when there is not item', async () => {
        const wrapper = await createWrapper({});

        const emptyState = wrapper.find('sw-empty-state-stub');
        expect(emptyState.exists()).toBeTruthy();
    });

    it('only product item should have redirect link', async () => {
        const wrapper = await createWrapper({});

        await wrapper.setProps({
            cart: {
                lineItems: [...mockItems],
            },
        });

        const productItem = wrapper.find('.sw-data-grid__row--0');
        const productLabel = productItem.find('.sw-data-grid__cell--label');
        const showProductButton1 = productItem.find('.sw-context-menu-item');

        expect(productLabel.find('router-link-stub').exists()).toBeTruthy();
        expect(productLabel.find('router-link-stub').attributes().target).toBe('_blank');
        expect(showProductButton1.attributes().disabled).toBeUndefined();

        const customItem = wrapper.find('.sw-data-grid__row--1');
        const customLabel = customItem.find('.sw-data-grid__cell--label');
        const showProductButton2 = customItem.find('.sw-context-menu-item');

        expect(customLabel.find('router-link-stub').exists()).toBeFalsy();
        expect(showProductButton2.attributes().disabled).toBeTruthy();

        const creditItem = wrapper.find('.sw-data-grid__row--2');
        const creditLabel = creditItem.find('.sw-data-grid__cell--label');
        const showProductButton3 = creditItem.find('.sw-context-menu-item');

        expect(creditLabel.find('router-link-stub').exists()).toBeFalsy();
        expect(showProductButton3.attributes().disabled).toBeTruthy();
    });

    it('should not show tooltip if only items which have single tax', async () => {
        const wrapper = await createWrapper({});

        await wrapper.setProps({
            cart: {
                lineItems: [...mockItems],
            },
        });

        const creditTax = wrapper.find('.sw-data-grid__row--2').find('.sw-data-grid__cell--tax');
        const creditTaxTooltip = creditTax.find('.sw-order-line-items-grid-sales-channel__item-tax-tooltip');

        expect(creditTaxTooltip.exists()).toBeFalsy();
    });

    it('should show tooltip if item has multiple taxes', async () => {
        const wrapper = await createWrapper({});

        await wrapper.setProps({
            cart: {
                lineItems: [{ ...mockMultipleTaxesItem }],
            },
        });

        const creditTax = wrapper.find('.sw-data-grid__row--0').find('.sw-data-grid__cell--tax');
        const taxDetailTooltip = creditTax.find('.sw-order-line-items-grid-sales-channel__item-tax-tooltip');

        expect(taxDetailTooltip.isVisible()).toBeTruthy();
    });

    it('should show tooltip message correctly with item detail', async () => {
        const wrapper = await createWrapper({});

        await wrapper.setProps({
            cart: {
                lineItems: [{ ...mockMultipleTaxesItem }],
            },
        });

        const taxDetailTooltip = wrapper.find('.sw-order-line-items-grid-sales-channel__item-tax-tooltip');

        expect(taxDetailTooltip.attributes()['tooltip-message'])
            .toBe('sw-order.createBase.tax<br>10%: -€3.33<br>20%: -€13.33');
    });

    it('should show items correctly when search by search term', async () => {
        const wrapper = await createWrapper({});

        await wrapper.setProps({
            cart: {
                lineItems: [...mockItems],
            },
        });

        await wrapper.setData({
            searchTerm: 'item product',
        });

        const productItem = wrapper.find('.sw-data-grid__row--0');
        const productLabel = productItem.find('.sw-data-grid__cell--label');

        expect(productLabel.text()).toBe('Product item');
    });

    it('should have vat column and price label is not tax free when tax status is tax free', async () => {
        const wrapper = await createWrapper({});
        await wrapper.setProps({
            cart: {
                lineItems: [...mockItems],
            },
        });

        const header = wrapper.find('.sw-data-grid__header');
        const columnVat = header.find('.sw-data-grid__cell--3');
        const columnPrice = header.find('.sw-data-grid__cell--2');

        expect(columnVat.text()).toBe('sw-order.createBase.columnTax');
        expect(columnPrice.text()).toBe('sw-order.createBase.columnPriceGross');
    });

    it('should not have vat column and price label is tax free when tax status is tax free', async () => {
        const wrapper = await createWrapper({});
        await wrapper.setProps({
            cart: {
                lineItems: [...mockItems],
                price: {
                    taxStatus: 'tax-free',
                },
            },
        });

        const header = wrapper.find('.sw-data-grid__header');
        const columnTotal = header.find('.sw-data-grid__cell--3');
        const columnPrice = header.find('.sw-data-grid__cell--2');

        expect(columnTotal.text()).toBe('sw-order.createBase.columnTotalPriceNet');
        expect(columnPrice.text()).toBe('sw-order.createBase.columnPriceTaxFree');
    });

    it('should show total price title based on tax status correctly', async () => {
        const wrapper = await createWrapper({});

        let header;
        let columnTotal;

        await wrapper.setProps({
            cart: {
                lineItems: [...mockItems],
                price: {
                    taxStatus: 'tax-free',
                },
            },
        });

        header = wrapper.find('.sw-data-grid__header');
        columnTotal = header.find('.sw-data-grid__cell--3');

        expect(columnTotal.text()).toBe('sw-order.createBase.columnTotalPriceNet');

        await wrapper.setProps({
            cart: {
                lineItems: [...mockItems],
                price: {
                    taxStatus: 'gross',
                },
            },
        });

        header = wrapper.find('.sw-data-grid__header');
        columnTotal = header.find('.sw-data-grid__cell--4');

        expect(columnTotal.text()).toBe('sw-order.createBase.columnTotalPriceGross');

        await wrapper.setProps({
            cart: {
                lineItems: [...mockItems],
                price: {
                    taxStatus: 'net',
                },
            },
        });

        header = wrapper.find('.sw-data-grid__header');
        columnTotal = header.find('.sw-data-grid__cell--4');
        expect(columnTotal.text()).toBe('sw-order.createBase.columnTotalPriceNet');
    });

    it('should able to create new empty line item', async () => {
        const wrapper = await createWrapper({});
        Shopware.State.commit('swOrder/setCartToken', 'token');
        await wrapper.setProps({
            cart: {
                token: 'token',
                lineItems: [],
            },
            isCustomerActive: true,
        });

        let itemRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(itemRows).toHaveLength(0);

        const buttonAddItem = wrapper.find('.sw-order-line-items-grid-sales-channel__add-product');
        await buttonAddItem.trigger('click');

        itemRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(itemRows).toHaveLength(1);

        const firstRow = itemRows.at(0);
        expect(firstRow.find('.sw-data-grid__cell--quantity').text()).toBe('1');
        expect(firstRow.find('.sw-data-grid__cell--unitPrice').text()).toBe('...');
        expect(firstRow.find('.sw-data-grid__cell--tax').text()).toBe('0 %');
        expect(firstRow.find('.sw-data-grid__cell--totalPrice').text()).toBe('...');
    });

    it('should able to create new product line item', async () => {
        const wrapper = await createWrapper({});
        Shopware.State.commit('swOrder/setCartToken', 'token');
        await wrapper.setProps({
            cart: {
                token: 'token',
                lineItems: [],
            },
            isCustomerActive: true,
        });
        const buttonAddItem = wrapper.find('.sw-order-line-items-grid-sales-channel__add-product');
        await buttonAddItem.trigger('click');

        const itemRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        const firstRow = itemRows.at(0);

        await firstRow.find('.sw-data-grid__cell--label').trigger('dblclick');

        const labelField = firstRow.find('.sw-data-grid__cell--label input');
        await labelField.setValue('Product 1');
        await labelField.trigger('input');

        const quantityField = firstRow.find('.sw-data-grid__cell--quantity input');
        await quantityField.setValue(3);
        await quantityField.trigger('input');

        const buttonInlineSave = wrapper.find('.sw-data-grid__inline-edit-save');
        await buttonInlineSave.trigger('click');

        expect(firstRow.find('.sw-data-grid__cell--label').exists()).toBeTruthy();
        expect(firstRow.find('.sw-data-grid__cell--quantity').text()).toBe('3');
        expect(wrapper.emitted('on-save-item')).toBeTruthy();
        expect(wrapper.emitted('on-save-item')[0][0].label).toBe('Product 1');
        expect(wrapper.emitted('on-save-item')[0][0].quantity).toBe(3);
        expect(wrapper.emitted('on-save-item')[0][0].type).toBe('product');
    });

    it('should able to create new custom line item', async () => {
        const wrapper = await createWrapper({});
        Shopware.State.commit('swOrder/setCartToken', 'token');
        await wrapper.setProps({
            cart: {
                token: 'token',
                lineItems: [],
            },
            isCustomerActive: true,
        });
        const buttonAddCustomItem = wrapper.find('.sw-order-line-items-grid-sales-channel__add-custom-item');
        await buttonAddCustomItem.trigger('click');

        const itemRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        const firstRow = itemRows.at(0);

        await firstRow.find('.sw-data-grid__cell--label').trigger('dblclick');

        const labelField = firstRow.find('.sw-data-grid__cell--label input');
        await labelField.setValue('Custom item');
        await labelField.trigger('input');

        const buttonInlineSave = wrapper.find('.sw-data-grid__inline-edit-save');
        await buttonInlineSave.trigger('click');

        expect(wrapper.emitted('on-save-item')).toBeTruthy();
        expect(wrapper.emitted('on-save-item')[0][0].label).toBe('Custom item');
        expect(wrapper.emitted('on-save-item')[0][0].type).toBe('custom');
    });

    it('should able to create new credit line item', async () => {
        const wrapper = await createWrapper({});
        Shopware.State.commit('swOrder/setCartToken', 'token');
        await wrapper.setProps({
            cart: {
                token: 'token',
                lineItems: [],
            },
            isCustomerActive: true,
        });
        const buttonAddCreditItem = wrapper.find('.sw-order-line-items-grid-sales-channel__add-credit-item');
        await buttonAddCreditItem.trigger('click');

        const itemRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        const firstRow = itemRows.at(0);

        await firstRow.find('.sw-data-grid__cell--label').trigger('dblclick');

        const labelField = firstRow.find('.sw-data-grid__cell--label input');
        await labelField.setValue('Credit item');
        await labelField.trigger('input');

        const buttonInlineSave = wrapper.find('.sw-data-grid__inline-edit-save');
        await buttonInlineSave.trigger('click');

        expect(wrapper.emitted('on-save-item')).toBeTruthy();
        expect(wrapper.emitted('on-save-item')[0][0].label).toBe('Credit item');
        expect(wrapper.emitted('on-save-item')[0][0].type).toBe('credit');
    });

    it('should able to cancel inline editing item', async () => {
        const wrapper = await createWrapper({});
        Shopware.State.commit('swOrder/setCartToken', 'token');
        await wrapper.setProps({
            cart: {
                token: 'token',
                lineItems: [...mockItems],
            },
            isCustomerActive: true,
        });
        const buttonAddCreditItem = wrapper.find('.sw-order-line-items-grid-sales-channel__add-product');
        await buttonAddCreditItem.trigger('click');

        const itemRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        const firstRow = itemRows.at(0);

        await firstRow.find('.sw-data-grid__cell--quantity').trigger('dblclick');

        const quantityField = firstRow.find('.sw-data-grid__cell--quantity input');
        await quantityField.setValue(3);
        await quantityField.trigger('input');

        const buttonInlineCancel = wrapper.find('.sw-data-grid__inline-edit-cancel');
        await buttonInlineCancel.trigger('click');

        expect(firstRow.find('.sw-data-grid__cell--quantity').text()).toBe('1');
    });

    it('should able to delete items', async () => {
        const wrapper = await createWrapper({});
        Shopware.State.commit('swOrder/setCartToken', 'token');
        await wrapper.setProps({
            cart: {
                token: 'token',
                lineItems: [],
            },
            isCustomerActive: true,
        });
        const buttonAddCreditItem = wrapper.find('.sw-order-line-items-grid-sales-channel__add-credit-item');
        await buttonAddCreditItem.trigger('click');
        expect(Shopware.State.get('swOrder').cart.lineItems).toHaveLength(1);


        const selectAllCheckBox = wrapper.find('.sw-data-grid__select-all');
        await selectAllCheckBox.setChecked(true);

        const deleteAllButton = wrapper.find('.sw-data-grid__bulk-selected .link-danger');
        await deleteAllButton.trigger('click');

        await wrapper.vm.$nextTick();

        expect(Shopware.State.get('swOrder').cart.lineItems).toHaveLength(0);
    });

    it('should change credit value to negative', async () => {
        const wrapper = await createWrapper({});
        Shopware.State.commit('swOrder/setCartToken', 'token');
        await wrapper.setProps({
            cart: {
                token: 'token',
                lineItems: [],
            },
            isCustomerActive: true,
        });
        const buttonAddCreditItem = wrapper.find('.sw-order-line-items-grid-sales-channel__add-credit-item');
        await buttonAddCreditItem.trigger('click');

        const itemRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        const firstRow = itemRows.at(0);

        await firstRow.find('.sw-data-grid__cell--quantity').trigger('dblclick');

        const labelField = firstRow.find('.sw-data-grid__cell--label input');
        await labelField.setValue('Credit item');
        await labelField.trigger('input');

        const unitPriceField = firstRow.find('.sw-data-grid__cell--unitPrice input');
        await unitPriceField.setValue(100);
        await unitPriceField.trigger('input');

        const buttonInlineSave = wrapper.find('.sw-data-grid__inline-edit-save');
        await buttonInlineSave.trigger('click');

        expect(wrapper.emitted('on-save-item')[0][0].label).toBe('Credit item');
        expect(wrapper.emitted('on-save-item')[0][0].priceDefinition.price).toBe(-100);
    });
});
