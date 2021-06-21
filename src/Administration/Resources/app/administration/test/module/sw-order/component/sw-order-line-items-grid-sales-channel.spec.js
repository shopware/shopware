import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-order/component/sw-order-line-items-grid-sales-channel';
import 'src/app/component/data-grid/sw-data-grid';

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
        }
    }
];

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/search/user-config',
    status: 200,
    response: {
        data: []
    }
});

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

function createWrapper() {
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

    return shallowMount(Shopware.Component.build('sw-order-line-items-grid-sales-channel'), {
        localVue,
        propsData: {
            cart: {
                lineItems: [],
                price: {
                    taxStatus: 'net'
                }
            },
            currency: {
                shortName: 'EUR',
                symbol: '€'
            },
            salesChannelId: ''
        },
        stubs: {
            'sw-container': true,
            'sw-button': true,
            'sw-button-group': true,
            'sw-context-button': true,
            'sw-context-menu-item': true,
            'sw-card-filter': true,
            'sw-data-grid': Shopware.Component.build('sw-data-grid'),
            'sw-product-variant-info': true,
            'sw-order-product-select': true,
            'router-link': true
        },
        mocks: {
            $tc: (t, count, value) => {
                if (t === 'sw-order.createBase.taxDetail') {
                    return `${value.taxRate}%: ${value.tax}`;
                }

                return t;
            }
        }
    });
}

describe('src/module/sw-order/component/sw-order-line-items-grid-sales-channel', () => {
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

    it('should show empty state when there is not item', async () => {
        const wrapper = createWrapper({});

        const emptyState = wrapper.find('.sw-order-line-items-grid-sales-channel__empty-container');
        const itemGrid = wrapper.find('img');

        expect(emptyState.exists()).toBeTruthy();
        expect(itemGrid.exists()).toBeTruthy();
    });

    it('only product item should have redirect link', async () => {
        const wrapper = createWrapper({});

        await wrapper.setProps({
            cart: {
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
        const wrapper = createWrapper({});

        await wrapper.setProps({
            cart: {
                lineItems: [...mockItems]
            }
        });

        const creditTax = wrapper.find('.sw-data-grid__row--2').find('.sw-data-grid__cell--tax');
        const creditTaxTooltip = creditTax.find('.sw-order-line-items-grid-sales-channel__item-tax-tooltip');

        expect(creditTaxTooltip.exists()).toBeFalsy();
    });

    it('should show tooltip if item has multiple taxes', async () => {
        const wrapper = createWrapper({});

        await wrapper.setProps({
            cart: {
                lineItems: [{ ...mockMultipleTaxesItem }]
            }
        });

        const creditTax = wrapper.find('.sw-data-grid__row--0').find('.sw-data-grid__cell--tax');
        const taxDetailTooltip = creditTax.find('.sw-order-line-items-grid-sales-channel__item-tax-tooltip');

        expect(taxDetailTooltip.isVisible()).toBeTruthy();
    });

    it('should show tooltip message correctly with item detail', async () => {
        const wrapper = createWrapper({});

        await wrapper.setProps({
            cart: {
                lineItems: [{ ...mockMultipleTaxesItem }]
            }
        });

        const taxDetailTooltip = wrapper.find('.sw-order-line-items-grid-sales-channel__item-tax-tooltip');

        expect(taxDetailTooltip.attributes()['tooltip-message'])
            .toBe('sw-order.createBase.tax<br>10%: -€3.33<br>20%: -€13.33');
    });

    it('should show items correctly when search by search term', async () => {
        const wrapper = createWrapper({});

        await wrapper.setProps({
            cart: {
                lineItems: [...mockItems]
            }
        });

        await wrapper.setData({
            searchTerm: 'item product'
        });

        const productItem = wrapper.find('.sw-data-grid__row--0');
        const productLabel = productItem.find('.sw-data-grid__cell--label');

        expect(productLabel.text()).toEqual('Product item');
    });

    it('should have vat column and price label is not tax free when tax status is tax free', async () => {
        const wrapper = createWrapper({});
        await wrapper.setProps({
            cart: {
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
        const wrapper = createWrapper({});
        await wrapper.setProps({
            cart: {
                lineItems: [...mockItems],
                price: {
                    taxStatus: 'tax-free'
                }
            }
        });

        const header = wrapper.find('.sw-data-grid__header');
        const columnVat = header.find('.sw-data-grid__cell--4');
        const columnPrice = header.find('.sw-data-grid__cell--1');
        expect(columnVat.exists()).toBe(false);
        expect(columnPrice.text()).toEqual('sw-order.createBase.columnPriceTaxFree');
    });

    it('should show total price title based on tax status correctly', async () => {
        const wrapper = createWrapper({});

        let header;
        let columnTotal;

        await wrapper.setProps({
            cart: {
                lineItems: [...mockItems],
                price: {
                    taxStatus: 'tax-free'
                }
            }
        });

        header = wrapper.find('.sw-data-grid__header');
        columnTotal = header.find('.sw-data-grid__cell--3');
        expect(columnTotal.text()).toEqual('sw-order.createBase.columnTotalPriceNet');

        await wrapper.setProps({
            cart: {
                lineItems: [...mockItems],
                price: {
                    taxStatus: 'gross'
                }
            }
        });

        header = wrapper.find('.sw-data-grid__header');
        columnTotal = header.find('.sw-data-grid__cell--3');
        expect(columnTotal.text()).toEqual('sw-order.createBase.columnTotalPriceGross');

        await wrapper.setProps({
            cart: {
                lineItems: [...mockItems],
                price: {
                    taxStatus: 'net'
                }
            }
        });

        header = wrapper.find('.sw-data-grid__header');
        columnTotal = header.find('.sw-data-grid__cell--3');
        expect(columnTotal.text()).toEqual('sw-order.createBase.columnTotalPriceNet');
    });
});
