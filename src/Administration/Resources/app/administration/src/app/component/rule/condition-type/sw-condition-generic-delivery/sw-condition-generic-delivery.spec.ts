/**
 * @sw-package fundamentals@after-sales
 */
import { DOMWrapper, mount } from '@vue/test-utils';
import ConditionDataProviderService from 'src/app/service/rule-condition.service';

const APPLIES_TO_SELECTOR = '.sw-condition-generic-delivery__applies-to';

function createChildren(): EntityCollection<'rule_condition'> {
    return new Shopware.Data.EntityCollection('/rule-condition', 'rule_condition', Shopware.Context.api, null, []);
}

function createCondition(): Record<string, unknown> {
    return {
        id: 'weight-condition',
        type: 'cartWeight',
        position: 2,
        value: { operator: '>', weight: 30 },
        children: createChildren(),
    };
}

async function createWrapper({
    parentCondition = { id: 'or-container', type: 'orContainer' },
    disabled = false,
    provide = {},
}: {
    parentCondition?: Record<string, unknown>;
    disabled?: boolean;
    provide?: Record<string, unknown>;
} = {}) {
    const conditionDataProviderService = new ConditionDataProviderService();
    conditionDataProviderService.addCondition('cartWeight', {
        component: 'sw-condition-generic-delivery',
        label: 'global.sw-condition.condition.weightOfCartRule',
        scopes: ['cart'],
        group: 'cart',
    });

    const condition = createCondition();

    const wrapper = mount(await wrapTestComponent('sw-condition-generic-delivery', { sync: true }), {
        props: {
            condition,
            parentCondition,
            disabled,
        },
        attachTo: document.body,
        global: {
            stubs: {
                'sw-single-select': await wrapTestComponent('sw-single-select'),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                'sw-loader': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
                'sw-condition-type-select': true,
                'sw-condition-operator-select': true,
                'sw-form-field-renderer': true,
                'sw-condition-unit-menu': true,
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-field-error': true,
            },
            provide: {
                conditionDataProviderService,
                availableTypes: [],
                availableGroups: [],
                restrictedConditions: [],
                childAssociationField: 'children',
                repositoryFactory: {
                    create: () => ({ create: () => ({ id: 'copied-condition' }) }),
                },
                createCondition: jest.fn(() => ({ id: 'delivery-container' })),
                insertNodeIntoTree: jest.fn(),
                removeNodeFromTree: jest.fn(),
                ...provide,
            },
        },
    });
    await flushPromises();

    return { wrapper, condition };
}

async function openAppliesTo(wrapper: Awaited<ReturnType<typeof createWrapper>>['wrapper']): Promise<void> {
    await wrapper.get(`${APPLIES_TO_SELECTOR} .sw-select__selection`).trigger('click');
    await flushPromises();
}

function getOption(value: string) {
    return new DOMWrapper(document.body).get(`.sw-select-option--${value}`);
}

describe('src/app/component/rule/condition-type/sw-condition-generic-delivery', () => {
    // @deprecated tag:v6.8.0 - The select is always shown once the flag is removed
    it.deprecated('v6.8.0.0')('hides the applies to select', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find(APPLIES_TO_SELECTOR).exists()).toBe(false);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('offers the whole cart and this delivery', async () => {
        const { wrapper } = await createWrapper();

        await openAppliesTo(wrapper);

        expect(new DOMWrapper(document.body).findAll('.sw-select-result').map((option) => option.text())).toEqual([
            'global.sw-condition.condition.deliveryCondition.cart',
            'global.sw-condition.condition.deliveryCondition.delivery',
        ]);
    });

    it.activeFeatureFlags(['v6.8.0.0']).each([
        ['orContainer', 'cart'],
        ['deliveryContainer', 'delivery'],
    ])('reads the scope from a %s parent', async (parentType, expected) => {
        const { wrapper } = await createWrapper({ parentCondition: { id: 'parent', type: parentType } });

        expect(wrapper.get(`${APPLIES_TO_SELECTOR} .sw-single-select__selection-text`).text()).toBe(
            `global.sw-condition.condition.deliveryCondition.${expected}`,
        );
    });

    it.activeFeatureFlags(['v6.8.0.0'])('offers this delivery while the rule has no blocking assignment', async () => {
        const { wrapper } = await createWrapper();

        await openAppliesTo(wrapper);
        const delivery = getOption('delivery');

        expect(delivery.classes()).not.toContain('is--disabled');
        expect(delivery.attributes('tooltip-mock-disabled')).toBe('true');
    });

    it.activeFeatureFlags(['v6.8.0.0'])('blocks this delivery while the rule is assigned to a payment method', async () => {
        const { wrapper } = await createWrapper({
            provide: {
                restrictedConditions: {
                    deliveryContainer: [
                        {
                            associationName: 'paymentMethods',
                            snippet: 'sw-restricted-rules.restrictedAssignment.paymentMethods',
                        },
                    ],
                },
            },
        });

        await openAppliesTo(wrapper);
        const delivery = getOption('delivery');

        expect(delivery.classes()).toContain('is--disabled');
        expect(delivery.attributes('tooltip-mock-disabled')).toBe('false');
        expect(delivery.attributes('tooltip-mock-message')).toBe(
            'sw-restricted-rules.restrictedConditions.restrictedConditionTooltip',
        );
        expect(getOption('cart').classes()).not.toContain('is--disabled');
    });

    it.activeFeatureFlags(['v6.8.0.0'])('disables the select with the condition', async () => {
        const { wrapper } = await createWrapper({ disabled: true });

        expect(wrapper.get(APPLIES_TO_SELECTOR).classes()).toContain('is--disabled');
    });

    it.activeFeatureFlags(['v6.8.0.0'])('wraps a copy of the condition into a delivery container', async () => {
        const createConditionMock = jest.fn(() => ({ id: 'delivery-container' }));
        const insertNodeIntoTree = jest.fn();
        const removeNodeFromTree = jest.fn();
        const parentCondition = { id: 'or-container', type: 'orContainer' };
        const { wrapper, condition } = await createWrapper({
            parentCondition,
            provide: { createCondition: createConditionMock, insertNodeIntoTree, removeNodeFromTree },
        });

        await openAppliesTo(wrapper);
        await getOption('delivery').trigger('click');

        expect(removeNodeFromTree).toHaveBeenCalledWith(parentCondition, expect.objectContaining({ id: condition.id }));
        expect(createConditionMock).toHaveBeenCalledWith(
            expect.objectContaining({ type: 'deliveryContainer', value: {} }),
            'or-container',
            2,
        );

        const [containerData] = createConditionMock.mock.calls[0] as unknown as [
            { children: EntityCollection<'rule_condition'> },
        ];
        expect(containerData.children.first()).toEqual(
            expect.objectContaining({
                id: 'copied-condition',
                type: 'cartWeight',
                value: { operator: '>', weight: 30 },
            }),
        );
        expect(insertNodeIntoTree).toHaveBeenCalledWith(parentCondition, { id: 'delivery-container' });
    });

    it.activeFeatureFlags(['v6.8.0.0'])('unwraps the condition from its delivery container', async () => {
        const unwrapDeliveryCondition = jest.fn();
        const { wrapper, condition } = await createWrapper({
            parentCondition: { id: 'delivery-container', type: 'deliveryContainer' },
            provide: { unwrapDeliveryCondition },
        });

        await openAppliesTo(wrapper);
        await getOption('cart').trigger('click');

        expect(unwrapDeliveryCondition).toHaveBeenCalledWith(expect.objectContaining({ id: condition.id }));
    });
});
