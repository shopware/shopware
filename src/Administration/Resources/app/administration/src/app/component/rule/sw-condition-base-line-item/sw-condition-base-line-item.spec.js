/**
 * @sw-package fundamentals@after-sales
 */
import { mount } from '@vue/test-utils';

const MATCHES_ALL_SELECTOR = '.sw-condition-base-line-item__matches-all';

const conditionFixture = {
    id: 'condition-1',
    rule_id: 'rule-1',
    parent_id: null,
    type: 'cartLineItemProductStates',
    value: {
        operator: '=',
        productState: 'is-physical',
    },
};

const defaultProvides = {
    availableTypes: [],
    conditionScopes: ['cart'],
    availableGroups: {},
    childAssociationField: {},
    createCondition: () => {},
    insertNodeIntoTree: () => {},
    removeNodeFromTree: () => {},
    unwrapAllLineItemsCondition: () => {},
    conditionDataProviderService: {
        getComponentByCondition: () => 'sw-condition-generic-line-item',
    },
};

async function createWrapper(condition = conditionFixture, provide = defaultProvides) {
    return mount(await wrapTestComponent('sw-condition-base-line-item', { sync: true }), {
        props: { condition },
        global: {
            stubs: {
                'sw-field-error': true,
                'sw-single-select': true,
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-condition-type-select': true,
                'sw-condition-generic-line-item': true,
            },
            provide,
        },
    });
}

function setConfig(config) {
    Shopware.Store.get('ruleConditionsConfig').config = config;
}

describe('components/rule/sw-condition-base-line-item', () => {
    beforeEach(() => {
        setConfig(null);
    });

    it.each([
        { name: 'cart', conditionScopes: ['cart'], expected: true },
        { name: 'customer (non-cart)', conditionScopes: ['customer'], expected: false },
    ])('only offers the match-all toggle in cart scope: $name', async ({ conditionScopes, expected }) => {
        setConfig({
            cartLineItemProductStates: {
                operatorSet: {
                    isMatchAny: true,
                },
            },
        });

        const wrapper = await createWrapper(conditionFixture, { ...defaultProvides, conditionScopes });
        await flushPromises();

        expect(wrapper.find(MATCHES_ALL_SELECTOR).exists()).toBe(expected);
    });

    it.each([
        { name: 'isMatchAny true', isMatchAny: true, expected: true },
        { name: 'isMatchAny false', isMatchAny: false, expected: false },
    ])('toggles match-all from operatorSet.isMatchAny: $name', async ({ isMatchAny, expected }) => {
        setConfig({
            cartLineItemProductStates: {
                operatorSet: {
                    isMatchAny,
                },
            },
        });

        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find(MATCHES_ALL_SELECTOR).exists()).toBe(expected);
    });

    it('shows the match-all toggle when a field declares isMatchAny', async () => {
        setConfig({
            cartLineItemPromoted: {
                fields: {
                    isPromoted: {
                        name: 'isPromoted',
                        type: 'bool',
                        config: {
                            isMatchAny: true,
                        },
                    },
                },
            },
        });

        const wrapper = await createWrapper({
            ...conditionFixture,
            type: 'cartLineItemPromoted',
            value: { isPromoted: true },
        });
        await flushPromises();

        expect(wrapper.find(MATCHES_ALL_SELECTOR).exists()).toBe(true);
    });

    it('detects isMatchAny on any field, not just the first one', async () => {
        setConfig({
            cartLineItemPromoted: {
                operatorSet: { isMatchAny: false },
                fields: {
                    amount: { name: 'amount', type: 'float', config: {} },
                    isPromoted: { name: 'isPromoted', type: 'bool', config: { isMatchAny: true } },
                },
            },
        });

        const wrapper = await createWrapper({
            ...conditionFixture,
            type: 'cartLineItemPromoted',
            value: { isPromoted: true },
        });
        await flushPromises();

        expect(wrapper.find(MATCHES_ALL_SELECTOR).exists()).toBe(true);
    });

    it('hides the match-all toggle when neither the operator set nor any field declares isMatchAny', async () => {
        setConfig({
            cartLineItemProductStates: {
                operatorSet: { operators: ['='] },
                fields: {
                    productState: { name: 'productState', type: 'single-select', config: {} },
                },
            },
        });

        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find(MATCHES_ALL_SELECTOR).exists()).toBe(false);
    });

    it('shows the match-all toggle for bespoke line item conditions without a generic config (cartLineItem)', async () => {
        setConfig(null);

        const wrapper = await createWrapper({
            ...conditionFixture,
            type: 'cartLineItem',
            value: { operator: '=', identifiers: [] },
        });
        await flushPromises();

        expect(wrapper.find(MATCHES_ALL_SELECTOR).exists()).toBe(true);
    });

    it('hides the match-all toggle for cartLineItemWithQuantity (single product, not match-all capable)', async () => {
        setConfig(null);

        const wrapper = await createWrapper({
            ...conditionFixture,
            type: 'cartLineItemWithQuantity',
            value: { operator: '=', id: 'product-1', quantity: 1 },
        });
        await flushPromises();

        expect(wrapper.find(MATCHES_ALL_SELECTOR).exists()).toBe(false);
    });
});
