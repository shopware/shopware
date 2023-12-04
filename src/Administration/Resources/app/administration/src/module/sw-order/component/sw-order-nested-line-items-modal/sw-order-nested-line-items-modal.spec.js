import { mount } from '@vue/test-utils';

/**
 * @package customer-order
 */

const localCurrency = 'EUR';

function getMockChild(id, parentId) {
    const mockValue = id.split('.').join('');
    // id: 1.2.3.4.5 -> tax: 1.2345%
    const mockTax = parseFloat(id.slice(0, 2) + id.slice(2).split('.').join(''));

    return {
        id,
        parentId,
        type: 'product',
        label: `lineItem ${id}`,
        quantity: mockValue,
        children: [],
        totalPrice: mockValue * 100,
        unitPrice: mockValue * 10,
        price: {
            taxRules: [
                {
                    taxRate: mockTax,
                },
            ],
        },
    };
}

const mockParent = {
    id: 'parent',
    type: 'product',
    label: 'Parent Item',
    quantity: 1,
    children: [
        getMockChild('1', 'parent'),
        getMockChild('2', 'parent'),
    ],
    totalPrice: 200,
    unitPrice: 200,
    price: {
        taxRules: [
            {
                taxRate: 20,
            },
        ],
    },
};

const mockChildrenCollection = [
    getMockChild('1.1', '1'),
    getMockChild('1.1.1', '1.1'),
    getMockChild('1.1.1.1', '1.1.1'),
    getMockChild('1.1.1.1.1', '1.1.1.1'),
    getMockChild('1.1.2', '1.1'),
    getMockChild('1.2', '1'),
    getMockChild('1.3', '1'),
    getMockChild('2.1', '2'),
];

async function createWrapper() {
    return mount(await wrapTestComponent('sw-order-nested-line-items-modal', { sync: true }), {
        props: {
            order: {
                currency: {
                    shortName: localCurrency,
                },
            },
            lineItem: mockParent,
            context: {},
        },
        global: {
            provide: {
                shortcutService: {
                    startEventListener: () => {
                    },
                    stopEventListener: () => {
                    },
                },
                repositoryFactory: {
                    create: () => ({
                        search: (criteria) => {
                            const parentIds = criteria.filters.find(filter => filter.field === 'parentId')
                                .value.split('|');
                            const entities = mockChildrenCollection.filter(entity => parentIds.includes(entity.parentId));

                            // children association mock
                            entities.forEach((entity) => {
                                entity.children = mockChildrenCollection.filter(child => child.parentId === entity.id);
                            });

                            return Promise.resolve(entities);
                        },
                    }),
                },
            },
            stubs: {
                'sw-modal': await wrapTestComponent('sw-modal', { sync: true }),
                'sw-loader': await wrapTestComponent('sw-loader', { sync: true }),
                'sw-button': await wrapTestComponent('sw-button', { sync: true }),
                'sw-order-nested-line-items-row': await wrapTestComponent('sw-order-nested-line-items-row', { sync: true }),
            },
            mocks: {
                $tc: snippet => snippet,
            },
        },
    });
}

describe('src/module/sw-order/component/sw-order-nested-line-items-modal', () => {
    it('should show the loading indicator, when loading', async () => {
        const wrapper = await createWrapper();
        const loader = wrapper.find('.sw-order-nested-line-items-modal__loader');

        expect(loader.exists()).toBe(true);
    });

    it('should not show the loading indicator, when loading is done', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const loader = wrapper.find('.sw-order-nested-line-items-modal__loader');

        expect(loader.exists()).toBe(false);
    });

    it('should render the correct amount of total nested line items', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const content = wrapper.findAll('.sw-order-nested-line-items-row__content');
        expect(content).toHaveLength(10);
    });

    it('should render the items in the correct order with correct indentation class and properties', async () => {
        const currencyFilter = Shopware.Filter.getByName('currency');
        const wrapper = await createWrapper();
        await flushPromises();

        const content = wrapper.findAll('.sw-order-nested-line-items-row__content');

        const dataProvider = [
            {
                label: 'lineItem 1',
                nestingLevel: 1,
                quantity: 1,
                unitPrice: 10,
                totalPrice: 100,
                taxRate: 1,
            }, {
                label: 'lineItem 1.1',
                nestingLevel: 2,
                quantity: 11,
                unitPrice: 110,
                totalPrice: 1100,
                taxRate: 1.1,
            }, {
                label: 'lineItem 1.1.1',
                nestingLevel: 3,
                quantity: 111,
                unitPrice: 1110,
                totalPrice: 11100,
                taxRate: 1.11,
            }, {
                label: 'lineItem 1.1.1.1',
                nestingLevel: 4,
                quantity: 1111,
                unitPrice: 11110,
                totalPrice: 111100,
                taxRate: 1.111,
            }, {
                label: 'lineItem 1.1.1.1.1',
                nestingLevel: 5,
                quantity: 11111,
                unitPrice: 111110,
                totalPrice: 1111100,
                taxRate: 1.1111,
            }, {
                label: 'lineItem 1.1.2',
                nestingLevel: 3,
                quantity: 112,
                unitPrice: 1120,
                totalPrice: 11200,
                taxRate: 1.12,
            }, {
                label: 'lineItem 1.2',
                nestingLevel: 2,
                quantity: 12,
                unitPrice: 120,
                totalPrice: 1200,
                taxRate: 1.2,
            }, {
                label: 'lineItem 1.3',
                nestingLevel: 2,
                quantity: 13,
                unitPrice: 130,
                totalPrice: 1300,
                taxRate: 1.3,
            }, {
                label: 'lineItem 2',
                nestingLevel: 1,
                quantity: 2,
                unitPrice: 20,
                totalPrice: 200,
                taxRate: 2,
            }, {
                label: 'lineItem 2.1',
                nestingLevel: 2,
                quantity: 21,
                unitPrice: 210,
                totalPrice: 2100,
                taxRate: 2.1,
            },
        ];

        dataProvider.forEach((data, index) => {
            const currentRow = content.at(index);

            const currentNestingLevels = currentRow.findAll('.sw-order-nested-line-items-row__nesting-level');
            const currentLabel = currentRow.find('.sw-order-nested-line-items-row__label-content');
            const currentUnitPrice = currentRow.find('.sw-order-nested-line-items-row__unit-price');
            const currentQuantity = currentRow.find('.sw-order-nested-line-items-row__quantity');
            const currentTotalPrice = currentRow.find('.sw-order-nested-line-items-row__total-price');
            const currentTax = currentRow.find('.sw-order-nested-line-items-row__tax');

            expect(currentNestingLevels).toHaveLength(data.nestingLevel - 1);
            expect(currentLabel.text()).toContain(data.label);
            expect(currentUnitPrice.text()).toContain(`${currencyFilter(data.unitPrice, localCurrency)}`);
            expect(currentQuantity.text()).toContain(`${data.quantity}`);
            expect(currentTotalPrice.text()).toContain(`${currencyFilter(data.totalPrice, localCurrency)}`);
            expect(currentTax.text()).toContain(`${data.taxRate} %`);
        });
    });
    resetFilters();
});
