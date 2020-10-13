import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-order/component/sw-order-line-items-grid';
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

function createWrapper({ privileges = [] }) {
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

    return shallowMount(Shopware.Component.build('sw-order-line-items-grid'), {
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
            orderService: {},
            acl: {
                can: (key) => {
                    if (!key) return true;

                    return privileges.includes(key);
                }
            }
        },
        stubs: {
            'sw-container': true,
            'sw-button': true,
            'sw-button-group': true,
            'sw-context-button': true,
            'sw-context-menu-item': true,
            'sw-card-filter': true,
            'sw-data-grid': Shopware.Component.build('sw-data-grid')
        },
        mocks: {
            $tc: (t, count, value) => {
                if (t === 'sw-order.detailBase.taxDetail') {
                    return `${value.taxRate}%: ${value.tax}`;
                }

                return t;
            },
            $te: t => t,
            $device: { onResize: () => {} }
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
        const wrapper = createWrapper({
            privileges: ['orders.create_discounts']
        });

        const button = wrapper.find('.sw-order-line-items-grid__can-create-discounts-button');
        expect(button.attributes()).not.toHaveProperty('disabled');
    });

    it('the create discounts button should not be disabled', async () => {
        const wrapper = createWrapper({});

        const button = wrapper.find('.sw-order-line-items-grid__can-create-discounts-button');
        expect(button.attributes()).toHaveProperty('disabled');
    });

    it('only product item should have redirect link', async () => {
        const wrapper = createWrapper({});

        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [...mockItems]
            }
        });

        await wrapper.vm.$nextTick();

        const productItem = wrapper.find('.sw-data-grid__row--0');
        const productLabel = productItem.find('.sw-data-grid__cell--label');
        const showProductButton1 = productItem.find('sw-context-menu-item-stub');

        expect(productLabel.contains('router-link')).toBeTruthy();
        expect(showProductButton1.attributes().disabled).toBeUndefined();


        const customItem = wrapper.find('.sw-data-grid__row--1');
        const customLabel = customItem.find('.sw-data-grid__cell--label');
        const showProductButton2 = customItem.find('sw-context-menu-item-stub');

        expect(customLabel.contains('router-link')).toBeFalsy();
        expect(showProductButton2.attributes().disabled).toBeTruthy();

        const creditItem = wrapper.find('.sw-data-grid__row--2');
        const creditLabel = creditItem.find('.sw-data-grid__cell--label');
        const showProductButton3 = creditItem.find('sw-context-menu-item-stub');

        expect(creditLabel.contains('router-link')).toBeFalsy();
        expect(showProductButton3.attributes().disabled).toBeTruthy();
    });

    it('should not show tooltip if only items which have single tax', async () => {
        const wrapper = createWrapper({});

        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [...mockItems]
            }
        });

        await wrapper.vm.$nextTick();

        const creditItem = wrapper.find('.sw-data-grid__row--2');

        const creditTaxTooltip = creditItem.find('.sw-order-line-items-grid__item-tax-tooltip');

        expect(creditTaxTooltip.exists()).toBeFalsy();
    });

    it('should show tooltip if item has multiple taxes', async () => {
        const wrapper = createWrapper({});

        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [{ ...mockMultipleTaxesItem }]
            }
        });

        await wrapper.vm.$nextTick();

        const creditItem = wrapper.find('.sw-data-grid__row--0');
        const taxDetailTooltip = creditItem.find('.sw-order-line-items-grid__item-tax-tooltip');

        expect(taxDetailTooltip.isVisible()).toBeTruthy();
    });

    it('should show tooltip if item has multiple taxes', async () => {
        const wrapper = createWrapper({});

        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [{ ...mockMultipleTaxesItem }]
            }
        });

        await wrapper.vm.$nextTick();

        const creditItem = wrapper.find('.sw-data-grid__row--0');
        const taxDetailTooltip = creditItem.find('.sw-order-line-items-grid__item-tax-tooltip');

        expect(taxDetailTooltip.isVisible()).toBeTruthy();
    });

    it('should show tooltip message correctly with item detail', async () => {
        const wrapper = createWrapper({});

        await wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [{ ...mockMultipleTaxesItem }]
            }
        });

        await wrapper.vm.$nextTick();

        const taxDetailTooltip = wrapper.find('.sw-order-line-items-grid__item-tax-tooltip');

        expect(taxDetailTooltip.attributes()['tooltip-message'])
            .toBe('sw-order.detailBase.tax<br>10%: -€3.33<br>20%: -€13.33');
    });

    it('should show items correctly when search by search tearm', async () => {
        const wrapper = createWrapper({});

        wrapper.setProps({
            order: {
                ...wrapper.props().order,
                lineItems: [...mockItems]
            }
        });

        wrapper.setData({
            searchTerm: 'item product'
        });

        await wrapper.vm.$nextTick();

        const firstRow = wrapper.find('.sw-data-grid__row--0');

        expect(firstRow.contains('Product item'));
    });
});
