import { shallowMount, createLocalVue } from '@vue/test-utils';
import swOrderLineItemsGrid from 'src/module/sw-order/component/sw-order-line-items-grid';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/context-menu/sw-context-button';
import 'src/app/component/context-menu/sw-context-menu-item';
import 'src/app/component/base/sw-modal';
import 'src/app/component/base/sw-button';

Shopware.Component.register('sw-order-line-items-grid', swOrderLineItemsGrid);

const mockItems = [
    {
        id: '1',
        type: 'product',
        label: 'Product item',
        quantity: 1,
        payload: {
            options: []
        },
        price: {
            quantity: 1,
            totalPrice: 200,
            unitPrice: 200,
            calculatedTaxes: [
                {
                    price: 200,
                    tax: 40,
                    taxRate: 20
                }
            ],
            taxRules: [
                {
                    taxRate: 20,
                    percentage: 100
                }
            ]
        }
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
                    taxRate: 10
                }
            ],
            taxRules: [
                {
                    taxRate: 10,
                    percentage: 100
                }
            ]
        },
        priceDefinition: {
            price: 100
        }
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
                    taxRate: 10
                }
            ],
            taxRules: [
                {
                    taxRate: 10,
                    percentage: 100
                }
            ]
        },
        priceDefinition: {
            price: 100
        }
    }
];

const mockMultipleTaxesItem = {
    ...mockItems[2],
    price: {
        ...mockItems[2].price,
        calculatedTaxes: [
            {
                price: -66.66,
                tax: -13.33,
                taxRate: 20
            },
            {
                price: -33.33,
                tax: -3.33,
                taxRate: 10
            }
        ],
        taxRules: [
            {
                taxRate: 20,
                percentage: 66.66
            },
            {
                taxRate: 10,
                percentage: 33.33
            }
        ]
    }
};

const deleteEndpoint = jest.fn(() => Promise.resolve());

async function createAdvancedWrapper() {
    return shallowMount(await Shopware.Component.build('sw-order-line-items-grid'), {
        localVue: createLocalVue(),
        propsData: {
            order: {
                price: {
                    taxStatus: ''
                },
                currency: {
                    shortName: 'EUR'
                },
                lineItems: [],
                taxStatus: ''
            },
            context: {}
        },
        provide: {
            repositoryFactory: {
                create: () => (
                    {
                        delete: deleteEndpoint
                    }
                ),
            },
            shortcutService: { stopEventListener: () => {}, startEventListener: () => {} },
            orderService: {},
            acl: {
                can: () => false
            },
            feature: {
                isActive: () => true
            }
        },
        stubs: {
            'sw-container': true,
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-button-group': true,
            'sw-context-button': true,
            'sw-context-menu-divider': true,
            'sw-context-menu-item': await Shopware.Component.build('sw-context-menu-item'),
            'sw-card-filter': true,
            'sw-checkbox-field': true,
            'sw-data-grid': await Shopware.Component.build('sw-data-grid'),
            'sw-modal': await Shopware.Component.build('sw-modal'),
            'sw-data-grid-settings': true,
            'sw-icon': true,
            'sw-product-variant-info': true,
            'sw-switch-field': true,
            'router-link': true
        },
        mocks: {
            $tc: (t, count, value) => {
                if (t === 'sw-order.detailBase.taxDetail') {
                    return `${value.taxRate}%: ${value.tax}`;
                }

                return t;
            }
        }
    });
}

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
        }
    });

    localVue.filter('currency', (currency) => currency);

    return shallowMount(await Shopware.Component.build('sw-order-line-items-grid'), {
        localVue,
        propsData: {
            order: {
                price: {
                    taxStatus: ''
                },
                currency: {
                    shortName: 'EUR'
                },
                lineItems: [],
                taxStatus: ''
            },
            context: {}
        },
        provide: {
            repositoryFactory: {
                create: () => ({ search: () => Promise.resolve([]) })
            },
            orderService: {},
            acl: {
                can: (key) => {
                    if (!key) return true;

                    return privileges.includes(key);
                }
            },
            feature: {
                isActive: () => true
            }
        },
        stubs: {
            'sw-container': true,
            'sw-button': true,
            'sw-button-group': true,
            'sw-context-button': true,
            'sw-context-menu-divider': true,
            'sw-context-menu-item': true,
            'sw-card-filter': true,
            'sw-checkbox-field': true,
            'sw-data-grid': await Shopware.Component.build('sw-data-grid'),
            'sw-data-grid-settings': true,
            'sw-icon': true,
            'sw-product-variant-info': true,
            'sw-switch-field': true,
            'router-link': true
        },
        mocks: {
            $tc: (t, count, value) => {
                if (t === 'sw-order.detailBase.taxDetail') {
                    return `${value.taxRate}%: ${value.tax}`;
                }

                return t;
            }
        }
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
                        PROMOTION: 'promotion'
                    });
                }
            };
        });
    });

    it('the create discounts button should not be disabled', async () => {
        const wrapper = await createWrapper({
            privileges: ['orders.create_discounts']
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
                lineItems: [...mockItems]
            }
        });

        const productItem = wrapper.find('.sw-data-grid__row--0');
        const productLabel = productItem.find('.sw-data-grid__cell--label');
        const showProductButton1 = productItem.find('sw-context-menu-item-stub');

        expect(productLabel.find('router-link-stub').exists()).toBeTruthy();
        expect(showProductButton1.attributes().disabled).toBeUndefined();


        const customItem = wrapper.find('.sw-data-grid__row--1');
        const customLabel = customItem.find('.sw-data-grid__cell--label');
        const showProductButton2 = customItem.find('sw-context-menu-item-stub');

        expect(customLabel.find('router-link-stub').exists()).toBeFalsy();
        expect(showProductButton2.attributes().disabled).toBeTruthy();

        const creditItem = wrapper.find('.sw-data-grid__row--2');
        const creditLabel = creditItem.find('.sw-data-grid__cell--label');
        const showProductButton3 = creditItem.find('sw-context-menu-item-stub');

        expect(creditLabel.find('router-link-stub').exists()).toBeFalsy();
        expect(showProductButton3.attributes().disabled).toBeTruthy();
    });

    it('should not show tooltip if only items which have single tax', async () => {
        const wrapper = await createWrapper({});

        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [...mockItems]
            }
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
                lineItems: [{ ...mockMultipleTaxesItem }]
            }
        });

        const creditItem = wrapper.find('.sw-data-grid__row--0');
        const taxDetailTooltip = creditItem.find('.sw-order-line-items-grid__item-tax-tooltip');

        expect(taxDetailTooltip.isVisible()).toBeTruthy();
    });

    it('should show tooltip if item has multiple taxes', async () => {
        const wrapper = await createWrapper({});

        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [{ ...mockMultipleTaxesItem }]
            }
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
                lineItems: [{ ...mockMultipleTaxesItem }]
            }
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
                lineItems: [...mockItems]
            }
        });

        await wrapper.setData({
            searchTerm: 'item product'
        });

        const firstRow = wrapper.find('.sw-data-grid__row--0');
        const productLabel = firstRow.find('.sw-data-grid__cell--label');

        expect(productLabel.text()).toEqual('Product item');
    });

    it('should automatically convert negative value of credit item price when user enter positive value', async () => {
        const wrapper = await createWrapper({});

        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [...mockItems]
            },
            editable: true
        });

        const creditItem = wrapper.vm.order.lineItems[2];

        wrapper.vm.checkItemPrice(creditItem.priceDefinition.price, creditItem);

        expect(creditItem.priceDefinition.price < 0).toEqual(true);

        const customItem = wrapper.vm.order.lineItems[1];

        wrapper.vm.checkItemPrice(customItem.priceDefinition.price, customItem);

        expect(customItem.priceDefinition.price > 0).toEqual(true);
    });

    it('should have vat column and price label is not tax free when tax status is tax free', async () => {
        const wrapper = await createWrapper({});
        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [...mockItems]
            }
        });

        const header = wrapper.find('.sw-data-grid__header');
        const columnVat = header.find('.sw-data-grid__cell--4');
        const columnPrice = header.find('.sw-data-grid__cell--1');
        expect(columnVat.exists()).toBe(true);
        expect(columnPrice.text()).not.toEqual('sw-order.createBase.columnPriceTaxFree');
    });

    it('should not have vat column and price label is tax free when tax status is tax free', async () => {
        const wrapper = await createWrapper({});
        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [...mockItems],
                taxStatus: 'tax-free'
            }
        });

        const header = wrapper.find('.sw-data-grid__header');
        const columnVat = header.find('.sw-data-grid__cell--4');
        const columnPrice = header.find('.sw-data-grid__cell--2');
        expect(columnVat.exists()).toBe(false);
        expect(columnPrice.text()).toEqual('sw-order.detailBase.columnPriceTaxFree');
    });

    // eslint-disable-next-line max-len
    it('should automatically set price definition quantity value of custom item when the user enters a change quantity value', async () => {
        const wrapper = await createWrapper({});

        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [...mockItems]
            },
            editable: true
        });

        const productItem = wrapper.vm.order.lineItems[0];
        wrapper.vm.updateItemQuantity(productItem);

        expect(productItem).toMatchObject(productItem);

        const customItem = wrapper.vm.order.lineItems[1];

        expect(customItem.priceDefinition).toMatchObject(customItem.priceDefinition);

        wrapper.vm.updateItemQuantity(customItem);

        expect(customItem.priceDefinition.quantity === customItem.quantity).toEqual(true);
    });

    it('should show total price title based on tax status correctly', async () => {
        const wrapper = await createWrapper({});

        let header;
        let columnTotal;

        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [...mockItems],
                taxStatus: 'tax-free'
            }
        });

        header = wrapper.find('.sw-data-grid__header');
        columnTotal = header.find('.sw-data-grid__cell--3');
        expect(columnTotal.text()).toEqual('sw-order.detailBase.columnTotalPriceNet');

        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [...mockItems],
                taxStatus: 'gross'
            }
        });

        header = wrapper.find('.sw-data-grid__header');
        columnTotal = header.find('.sw-data-grid__cell--4');
        expect(columnTotal.text()).toEqual('sw-order.detailBase.columnTotalPriceGross');

        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [...mockItems],
                taxStatus: 'net'
            }
        });

        header = wrapper.find('.sw-data-grid__header');
        columnTotal = header.find('.sw-data-grid__cell--4');
        expect(columnTotal.text()).toEqual('sw-order.detailBase.columnTotalPriceNet');
    });

    it('should open and close modal', async () => {
        const wrapper = await createAdvancedWrapper();
        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [...mockItems],
                taxStatus: 'gross'
            }
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showDeleteModal).toBeFalsy();

        const deleteActions = wrapper.findAll('.sw_order_line_items_grid-item__delete-action');
        await deleteActions.at(0).trigger('click');
        expect(wrapper.vm.showDeleteModal).toEqual('1');

        const closeAction = wrapper.find('.sw_order_line_items_grid-actions_modal__close-action');
        await closeAction.trigger('click');

        expect(wrapper.vm.showDeleteModal).toBeFalsy();
    });

    it('should open modal and delete entry', async () => {
        const wrapper = await createAdvancedWrapper();
        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [...mockItems],
                taxStatus: 'gross'
            }
        });

        const deleteActions = wrapper.findAll('.sw_order_line_items_grid-item__delete-action');
        await deleteActions.at(0).trigger('click');
        expect(wrapper.vm.showDeleteModal).toEqual('1');

        const confirmAction = wrapper.find('.sw_order_line_items_grid-actions_modal__confirm-action');
        await confirmAction.trigger('click');

        expect(deleteEndpoint).toBeCalledTimes(1);
        expect(wrapper.vm.showDeleteModal).toBeFalsy();
    });
});
