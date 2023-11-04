import { shallowMount, createLocalVue } from '@vue/test-utils';
import swOrderLineItemsGrid from 'src/module/sw-order/component/sw-order-line-items-grid';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/base/sw-button';

/**
 * @package customer-order
 */

Shopware.Component.register('sw-order-line-items-grid', swOrderLineItemsGrid);

const mockItems = [
    {
        id: '1',
        type: 'product',
        label: 'Product item',
        quantity: 1,
        payload: {
            options: [],
            productNumber: 'product number',
        },
        priceDefinition: {
            price: 200,
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
        isNew: () => false,
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
        priceDefinition: {
            price: 100,
        },
        isNew: () => false,
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
        priceDefinition: {
            price: 100,
        },
        isNew: () => false,
    },
];

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

const deleteEndpoint = jest.fn(() => Promise.resolve());

async function createWrapper({ privileges = [] }) {
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

    localVue.filter('currency', (currency) => currency);

    return shallowMount(await Shopware.Component.build('sw-order-line-items-grid'), {
        localVue,
        propsData: {
            order: {
                price: {
                    taxStatus: '',
                },
                currency: {
                    shortName: 'EUR',
                },
                lineItems: [],
                taxStatus: '',
                itemRounding: {
                    decimals: 2,
                },
            },
            context: {
                authToken: {
                    access: 'token',
                },
            },
            isLoading: false,
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    create: () => {
                        return {
                            isNew: () => true,
                            id: Shopware.Utils.createId(),
                        };
                    },
                    delete: deleteEndpoint,
                }),
            },
            orderService: {
                addProductToOrder: () => Promise.resolve({}),
                addCustomLineItemToOrder: () => Promise.resolve({}),
                addCreditItemToOrder: () => Promise.resolve({}),
            },
            acl: {
                can: (key) => {
                    if (!key) return true;

                    return privileges.includes(key);
                },
            },
            feature: {
                isActive: () => true,
            },
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
            'sw-context-menu-divider': true,
            'sw-context-menu-item': {
                template: '<div class="sw-context-menu-item" @click="$emit(\'click\')"><slot></slot></div>',
            },
            'sw-card-filter': true,
            'sw-checkbox-field': {
                template: '<input class="sw-checkbox-field" type="checkbox" :checked="value" @change="$emit(\'change\', $event.target.value)" />',
                props: ['value'],
            },
            'sw-data-grid': await Shopware.Component.build('sw-data-grid'),
            'sw-data-grid-settings': true,
            'sw-icon': true,
            'sw-product-variant-info': true,
            'sw-switch-field': true,
            'router-link': true,
            'sw-number-field': {
                template: '<input class="sw-number-field" type="number" v-model="value" />',
                props: {
                    value: 0,
                },
            },
            'sw-order-product-select': {
                template: '<input class="sw-order-product-select" v-model="value" />',
                props: {
                    value: 0,
                },
            },
            'sw-modal': {
                template: `
                    <div class="sw-modal">
                      <slot></slot>
                      <slot name="modal-footer"></slot>
                    </div>
                `,
            },
        },
        mocks: {
            $tc: (t, count, value) => {
                if (t === 'sw-order.detailBase.taxDetail') {
                    return `${value.taxRate}%: ${value.tax}`;
                }

                return t;
            },
        },
    });
}

describe('src/module/sw-order/component/sw-order-line-items-grid', () => {
    beforeAll(() => {
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
            };
        });
    });

    it('the create discounts button should be disabled', async () => {
        const wrapper = await createWrapper({
            privileges: ['orders.create_discounts'],
        });

        const button = wrapper.find('.sw-order-line-items-grid__can-create-discounts-button');
        expect(button.attributes()).not.toHaveProperty('disabled');
    });

    it('the create discounts button should not be disabled', async () => {
        const wrapper = await createWrapper({});

        const button = wrapper.find('.sw-order-line-items-grid__can-create-discounts-button');
        expect(button.attributes()).toHaveProperty('disabled');
    });

    it('only product item should have redirect link', async () => {
        const wrapper = await createWrapper({});

        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [...mockItems],
            },
        });

        const productItem = wrapper.find('.sw-data-grid__row--0');
        const productLabel = productItem.find('.sw-data-grid__cell--label');
        const showProductButton1 = productItem.find('.sw-context-menu-item');

        expect(productLabel.find('router-link-stub').exists()).toBeTruthy();
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
            order: {
                ...wrapper.props().order,
                lineItems: [...mockItems],
            },
        });

        const creditItem = wrapper.find('.sw-data-grid__row--2');
        const creditTaxTooltip = creditItem.find('.sw-order-line-items-grid__item-tax-tooltip');

        expect(creditTaxTooltip.exists()).toBeFalsy();
    });

    it('should show tooltip if item has multiple taxes', async () => {
        const wrapper = await createWrapper({});

        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [{ ...mockMultipleTaxesItem }],
            },
        });

        const creditItem = wrapper.find('.sw-data-grid__row--0');
        const taxDetailTooltip = creditItem.find('.sw-order-line-items-grid__item-tax-tooltip');

        expect(taxDetailTooltip.isVisible()).toBeTruthy();
    });

    it('should show tooltip message correctly with item detail', async () => {
        const wrapper = await createWrapper({});

        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [{ ...mockMultipleTaxesItem }],
            },
        });

        const taxDetailTooltip = wrapper.find('.sw-order-line-items-grid__item-tax-tooltip');

        expect(taxDetailTooltip.attributes()['tooltip-message'])
            .toBe('sw-order.detailBase.tax<br>10%: -€3.33<br>20%: -€13.33');
    });

    it('should show items correctly when search by search term', async () => {
        const wrapper = await createWrapper({});

        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [...mockItems],
            },
        });

        await wrapper.setData({
            searchTerm: 'item product',
        });

        const firstRow = wrapper.find('.sw-data-grid__row--0');
        const productLabel = firstRow.find('.sw-data-grid__cell--label');

        expect(productLabel.text()).toBe('Product item');
    });

    it('should automatically convert negative value of credit item price when user enter positive value', async () => {
        const wrapper = await createWrapper({});

        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [...mockItems],
            },
            editable: true,
        });

        const creditItem = wrapper.vm.order.lineItems[2];
        wrapper.vm.checkItemPrice(creditItem.priceDefinition.price, creditItem);
        expect(creditItem.priceDefinition.price < 0).toBe(true);

        const customItem = wrapper.vm.order.lineItems[1];
        wrapper.vm.checkItemPrice(customItem.priceDefinition.price, customItem);
        expect(customItem.priceDefinition.price > 0).toBe(true);
    });

    it('should have vat column and price label is not tax free when tax status is tax free', async () => {
        const wrapper = await createWrapper({});
        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [...mockItems],
            },
        });

        const header = wrapper.find('.sw-data-grid__header');
        const columnVat = header.find('.sw-data-grid__cell--4');
        const columnPrice = header.find('.sw-data-grid__cell--1');
        expect(columnVat.exists()).toBe(true);
        expect(columnPrice.text()).not.toBe('sw-order.createBase.columnPriceTaxFree');
    });

    it('should not have vat column and price label is tax free when tax status is tax free', async () => {
        const wrapper = await createWrapper({});
        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [...mockItems],
                taxStatus: 'tax-free',
            },
        });

        const header = wrapper.find('.sw-data-grid__header');
        const columnVat = header.find('.sw-data-grid__cell--5');
        const columnPrice = header.find('.sw-data-grid__cell--3');
        expect(columnVat.exists()).toBe(false);
        expect(columnPrice.text()).toBe('sw-order.detailBase.columnPriceTaxFree');
    });

    // eslint-disable-next-line max-len
    it('should automatically set price definition quantity value of custom item when the user enters a change quantity value', async () => {
        const wrapper = await createWrapper({});

        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [...mockItems],
            },
            editable: true,
        });

        const productItem = wrapper.vm.order.lineItems[0];
        wrapper.vm.updateItemQuantity(productItem);
        expect(productItem).toMatchObject(productItem);

        const customItem = wrapper.vm.order.lineItems[1];
        expect(customItem.priceDefinition).toMatchObject(customItem.priceDefinition);

        wrapper.vm.updateItemQuantity(customItem);
        expect(customItem.priceDefinition.quantity === customItem.quantity).toBe(true);
    });

    it('should show total price title based on tax status correctly', async () => {
        const wrapper = await createWrapper({});

        let header;
        let columnTotal;

        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [...mockItems],
                taxStatus: 'tax-free',
            },
        });

        header = wrapper.find('.sw-data-grid__header');
        columnTotal = header.find('.sw-data-grid__cell--4');
        expect(columnTotal.text()).toBe('sw-order.detailBase.columnTotalPriceNet');

        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [...mockItems],
                taxStatus: 'gross',
            },
        });

        header = wrapper.find('.sw-data-grid__header');
        columnTotal = header.find('.sw-data-grid__cell--5');
        expect(columnTotal.text()).toBe('sw-order.detailBase.columnTotalPriceGross');

        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [...mockItems],
                taxStatus: 'net',
            },
        });

        header = wrapper.find('.sw-data-grid__header');
        columnTotal = header.find('.sw-data-grid__cell--5');
        expect(columnTotal.text()).toBe('sw-order.detailBase.columnTotalPriceNet');
    });

    it('should able to create new empty line item', async () => {
        const wrapper = await createWrapper({
            privileges: [
                'order.viewer',
                'order.editor',
            ],
        });

        let itemRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(itemRows).toHaveLength(0);

        const buttonAddItem = wrapper.find('.sw-order-line-items-grid__actions-container-add-product-btn');
        await buttonAddItem.trigger('click');

        itemRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(itemRows).toHaveLength(1);

        const firstRow = itemRows.at(0);
        expect(firstRow.find('.sw-data-grid__cell--quantity').text()).toBe('1 x');
        expect(firstRow.find('.sw-data-grid__cell--unitPrice').text()).toBe('...');
        expect(firstRow.find('.sw-data-grid__cell--price-taxRules\\[0\\]').text()).toBe('0 %');
        expect(firstRow.find('.sw-data-grid__cell--totalPrice').text()).toBe('...');
    });

    it('should able to create new product line item', async () => {
        const wrapper = await createWrapper({
            privileges: [
                'order.viewer',
                'order.editor',
            ],
        });

        const buttonAddItem = wrapper.find('.sw-order-line-items-grid__actions-container-add-product-btn');
        await buttonAddItem.trigger('click');

        const itemRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(itemRows).toHaveLength(1);

        const firstRow = itemRows.at(0);
        await firstRow.find('.sw-data-grid__cell--label').trigger('dblclick');

        const buttonInlineSave = wrapper.find('.sw-data-grid__inline-edit-save');
        await buttonInlineSave.trigger('click');

        await flushPromises();
        expect(wrapper.emitted('item-edit')).toBeTruthy();
    });

    it('should able to create new custom line item', async () => {
        const wrapper = await createWrapper({
            privileges: [
                'order.viewer',
                'order.editor',
            ],
        });

        const buttonAddCustomItem = wrapper.find('.sw-order-line-items-grid__create-custom-item');
        await buttonAddCustomItem.trigger('click');

        const itemRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(itemRows).toHaveLength(1);

        const firstRow = itemRows.at(0);
        await firstRow.find('.sw-data-grid__cell--label').trigger('dblclick');

        const buttonInlineSave = wrapper.find('.sw-data-grid__inline-edit-save');
        await buttonInlineSave.trigger('click');
    });

    it('should able to create new credit line item', async () => {
        const wrapper = await createWrapper({
            privileges: [
                'order.viewer',
                'order.editor',
                'orders.create_discounts',
            ],
        });

        const buttonAddCreditItem = wrapper.find('.sw-order-line-items-grid__can-create-discounts-button');
        await buttonAddCreditItem.trigger('click');

        const itemRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(itemRows).toHaveLength(1);

        const firstRow = itemRows.at(0);
        await firstRow.find('.sw-data-grid__cell--label').trigger('dblclick');

        const buttonInlineSave = wrapper.find('.sw-data-grid__inline-edit-save');
        await buttonInlineSave.trigger('click');
    });

    it('should able to cancel inline edit', async () => {
        const wrapper = await createWrapper({
            privileges: [
                'order.viewer',
                'order.editor',
            ],
        });

        const buttonAddItem = wrapper.find('.sw-order-line-items-grid__actions-container-add-product-btn');
        await buttonAddItem.trigger('click');

        const itemRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(itemRows).toHaveLength(1);

        const firstRow = itemRows.at(0);
        await firstRow.find('.sw-data-grid__cell--label').trigger('dblclick');

        const buttonInlineCancel = wrapper.find('.sw-data-grid__inline-edit-cancel');
        await buttonInlineCancel.trigger('click');

        await flushPromises();
        expect(wrapper.emitted('item-cancel')).toBeTruthy();
    });

    it('should able to delete single item', async () => {
        const wrapper = await createWrapper({
            privileges: [
                'order.viewer',
                'order.editor',
            ],
        });

        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [{ ...mockItems[0] }],
                taxStatus: 'gross',
            },
        });

        const itemRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(itemRows).toHaveLength(1);

        const firstRow = itemRows.at(0);
        await firstRow.find('.sw-data-grid__cell--actions .sw-context-menu-item[variant="danger"]')
            .trigger('click');

        const deleteItemModal = wrapper.find('.sw-order-line-items-grid__delete-item-modal');
        expect(deleteItemModal.exists()).toBeTruthy();
    });

    it('should able to delete empty single item', async () => {
        const wrapper = await createWrapper({
            privileges: [
                'order.viewer',
                'order.editor',
            ],
        });

        const buttonAddItem = wrapper.find('.sw-order-line-items-grid__actions-container-add-product-btn');
        await buttonAddItem.trigger('click');

        let itemRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(itemRows).toHaveLength(1);

        const firstRow = itemRows.at(0);
        await firstRow.find('.sw-data-grid__cell--actions .sw-context-menu-item[variant="danger"]')
            .trigger('click');

        itemRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(itemRows).toHaveLength(0);
    });

    it('should able to delete multiple items', async () => {
        const wrapper = await createWrapper({
            privileges: [
                'order.viewer',
                'order.editor',
            ],
        });

        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [{ ...mockItems[0] }],
                taxStatus: 'gross',
            },
        });

        const selectAllCheckBox = wrapper.find('.sw-data-grid__select-all');
        await selectAllCheckBox.setChecked(true);

        const deleteAllButton = wrapper.find('.sw-data-grid__bulk-selected .link-danger');
        await deleteAllButton.trigger('click');

        await flushPromises();
        expect(wrapper.emitted('item-delete')).toBeTruthy();
    });

    it('should able to delete empty items', async () => {
        const wrapper = await createWrapper({
            privileges: [
                'order.viewer',
                'order.editor',
            ],
        });

        const buttonAddItem = wrapper.find('.sw-order-line-items-grid__actions-container-add-product-btn');
        await buttonAddItem.trigger('click');
        await buttonAddItem.trigger('click');


        let itemRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(itemRows).toHaveLength(2);

        const selectAllCheckBox = wrapper.find('.sw-data-grid__select-all');
        await selectAllCheckBox.setChecked(true);

        const deleteAllButton = wrapper.find('.sw-data-grid__bulk-selected .link-danger');
        await deleteAllButton.trigger('click');

        itemRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(itemRows).toHaveLength(0);
    });

    it('should able to edit single item', async () => {
        const wrapper = await createWrapper({
            privileges: [
                'order.viewer',
                'order.editor',
            ],
        });

        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [{ ...mockItems[0] }],
                taxStatus: 'gross',
            },
        });

        const itemRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(itemRows).toHaveLength(1);

        const firstRow = itemRows.at(0);
        await firstRow.find('.sw-data-grid__cell--label').trigger('dblclick');

        const buttonInlineSave = wrapper.find('.sw-data-grid__inline-edit-save');
        await buttonInlineSave.trigger('click');

        expect(wrapper.emitted('existing-item-edit')).toBeTruthy();
    });

    it('should open and close modal', async () => {
        const wrapper = await createWrapper({});
        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [...mockItems],
                taxStatus: 'gross',
            },
        });
        await wrapper.vm.$nextTick();

        let deleteItemModal = wrapper.find('.sw-order-line-items-grid__delete-item-modal');
        expect(deleteItemModal.exists()).toBeFalsy();

        const deleteActions = wrapper.findAll('.sw_order_line_items_grid-item__delete-action');
        await deleteActions.at(0).trigger('click');

        deleteItemModal = wrapper.find('.sw-order-line-items-grid__delete-item-modal');
        expect(deleteItemModal.exists()).toBeTruthy();

        const closeAction = wrapper.find('.sw_order_line_items_grid-actions_modal__close-action');
        await closeAction.trigger('click');

        deleteItemModal = wrapper.find('.sw-order-line-items-grid__delete-item-modal');
        expect(deleteItemModal.exists()).toBeFalsy();
    });

    it('should open modal and delete entry', async () => {
        const wrapper = await createWrapper({});
        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [...mockItems],
                taxStatus: 'gross',
            },
        });

        const deleteActions = wrapper.findAll('.sw_order_line_items_grid-item__delete-action');
        await deleteActions.at(0).trigger('click');

        let deleteItemModal = wrapper.find('.sw-order-line-items-grid__delete-item-modal');
        expect(deleteItemModal.exists()).toBeTruthy();

        const confirmAction = wrapper.find('.sw_order_line_items_grid-actions_modal__confirm-action');
        await confirmAction.trigger('click');
        expect(wrapper.emitted('item-delete')).toBeTruthy();

        expect(deleteEndpoint).toHaveBeenCalledTimes(1);

        deleteItemModal = wrapper.find('.sw-order-line-items-grid__delete-item-modal');
        expect(deleteItemModal.exists()).toBeFalsy();
    });

    it('should show product number column', async () => {
        const wrapper = await createWrapper({});

        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [...mockItems],
            },
        });

        const header = wrapper.find('.sw-data-grid__header');
        const columnProductNumber = header.find('.sw-data-grid__cell--2');

        expect(columnProductNumber.text()).toBe('sw-order.detailBase.columnProductNumber');
    });

    it('should show items correctly when search by product number', async () => {
        const wrapper = await createWrapper({});

        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [...mockItems],
            },
        });

        wrapper.vm.$refs.dataGrid.currentColumns.map((item) => {
            if (item.property === 'payload.productNumber') {
                item.visible = true;
            }

            return item;
        });

        await wrapper.setData({
            searchTerm: 'product number',
        });

        const firstRow = wrapper.find('.sw-data-grid__row--0');
        const productLabel = firstRow.find('.sw-data-grid__cell--label');

        expect(productLabel.text()).toBe('Product item');
    });
});
