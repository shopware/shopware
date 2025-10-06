/**
 * @sw-package fundamentals@after-sales
 */
import { mount } from '@vue/test-utils';
import { RULE_CONDITION_TYPES, RULE_CONDITIONS } from 'src/app/decorator/condition-type-data-provider.decorator';

const RULE_CONDITION_CONFIG_STORE_ID = 'ruleConditionsConfig';

const conditionFixture = {
    id: 'condition-1',
    rule_id: 'rule-1',
    parent_id: null,
    type: RULE_CONDITION_TYPES.CART_LINE_ITEM_PRODUCT_STATES,
    value: {
        operator: '=',
        productState: 'is-physical',
    },
};

const conditionDataProviderServiceMock = {
    getComponentByCondition: () => RULE_CONDITIONS[RULE_CONDITION_TYPES.CART_LINE_ITEM_PRODUCT_STATES].component,
};

const defaultProvides = {
    availableTypes: [],
    conditionScopes: null,
    availableGroups: {},
    childAssociationField: {},
    createCondition: () => {},
    insertNodeIntoTree: () => {},
    removeNodeFromTree: () => {},
    unwrapAllLineItemsCondition: () => {},
    conditionDataProviderService: conditionDataProviderServiceMock,
};

const defaultProps = {
    condition: conditionFixture,
};

async function createWrapper(props = defaultProps, provide = defaultProvides) {
    return mount(await wrapTestComponent('sw-condition-base-line-item', { sync: true }), {
        props,
        global: {
            stubs: {
                'sw-field-error': true,
                'sw-single-select': true,
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-condition-type-select': true,
            },
            provide,
        },
    });
}

describe('components/rule/sw-condition-base-line-item', () => {
    it.each([
        { name: 'cart', expected: true, conditionScopes: ['cart'] },
        { name: 'customer', expected: false, conditionScopes: ['customer'] },
        { name: 'null', expected: false, conditionScopes: null },
    ])('should display matches all selection if condition scope is cart: $name', async ({ expected, conditionScopes }) => {
        const wrapper = await createWrapper(defaultProps, {
            ...defaultProvides,
            conditionScopes,
        });
        await flushPromises();

        expect(wrapper.find('.sw-condition-base-line-item__matches-all').exists()).toBe(expected);
    });

    it.each([
        { name: 'true', expected: true, isMatchAny: true },
        { name: 'false', expected: false, isMatchAny: false },
    ])(
        'should display matches all selection if match any is set in operatorSet: $name',
        async ({ expected, isMatchAny }) => {
            Shopware.Store.get(RULE_CONDITION_CONFIG_STORE_ID).config = {
                [RULE_CONDITION_TYPES.CART_LINE_ITEM_PRODUCT_STATES]: {
                    operatorSet: {
                        isMatchAny,
                    },
                },
            };

            const wrapper = await createWrapper();
            await flushPromises();

            expect(wrapper.find('.sw-condition-base-line-item__matches-all').exists()).toBe(expected);
        },
    );

    it.each([
        { name: 'true', expected: true, isMatchAny: true },
        { name: 'false', expected: false, isMatchAny: false },
    ])(
        'should display matches all selection if match any is set in field config: $name',
        async ({ expected, isMatchAny }) => {
            Shopware.Store.get(RULE_CONDITION_CONFIG_STORE_ID).config = {
                [RULE_CONDITION_TYPES.CART_LINE_ITEM_PROMOTED]: {
                    fields: {
                        isPromoted: {
                            name: 'isPromoted',
                            type: 'bool',
                            config: {
                                isMatchAny,
                            },
                        },
                    },
                },
            };

            const wrapper = await createWrapper({
                ...defaultProps,
                condition: {
                    ...conditionFixture,
                    type: RULE_CONDITION_TYPES.CART_LINE_ITEM_PROMOTED,
                    value: {
                        isPromoted: true,
                    },
                },
            });
            await flushPromises();

            expect(wrapper.find('.sw-condition-base-line-item__matches-all').exists()).toBe(expected);
        },
    );
});
