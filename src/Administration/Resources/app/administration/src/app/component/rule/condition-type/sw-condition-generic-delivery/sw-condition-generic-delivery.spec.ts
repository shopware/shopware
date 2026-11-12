/**
 * @sw-package fundamentals@after-sales
 */
import { mount } from '@vue/test-utils';
import ConditionDataProviderService from 'src/app/service/rule-condition.service';

const APPLIES_TO_SELECTOR = '.sw-condition-generic-delivery__applies-to';
const APPLIES_TO_SELECT = `${APPLIES_TO_SELECTOR} select`;

const singleSelectStub = {
    props: ['value', 'options', 'disabled'],
    emits: ['update:value'],
    template: `
        <div>
            <select
                :value="value"
                :disabled="disabled"
                @change="$emit('update:value', $event.target.value)"
            >
                <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <template v-for="(item, index) in options" :key="item.value">
                <slot
                    name="result-item"
                    v-bind="{
                        item,
                        index,
                        labelProperty: 'label',
                        isSelected: () => item.value === value,
                        setValue: () => $emit('update:value', item.value),
                        getKey: (option, property) => option[property],
                    }"
                />
            </template>
        </div>
    `,
};

const selectResultStub = {
    props: [
        'item',
        'index',
        'selected',
        'disabled',
    ],
    template: '<div class="select-result-stub" :data-disabled="disabled"><slot /></div>',
};

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
        global: {
            stubs: {
                'sw-single-select': singleSelectStub,
                'sw-select-result': selectResultStub,
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

    return { wrapper, condition };
}

describe('src/app/component/rule/condition-type/sw-condition-generic-delivery', () => {
    // @deprecated tag:v6.8.0 - The select is always shown once the flag is removed
    it.deprecated('v6.8.0.0')('hides the applies to select', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find(APPLIES_TO_SELECTOR).exists()).toBe(false);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('offers the whole cart and this delivery', async () => {
        const { wrapper } = await createWrapper();
        const options = wrapper.findAll(`${APPLIES_TO_SELECT} option`);

        expect(options.map((option) => option.attributes('value'))).toEqual(['cart', 'delivery']);
        expect(options.map((option) => option.text())).toEqual([
            'global.sw-condition.condition.deliveryCondition.cart',
            'global.sw-condition.condition.deliveryCondition.delivery',
        ]);
    });

    it.activeFeatureFlags(['v6.8.0.0']).each([
        ['orContainer', 'cart'],
        ['deliveryContainer', 'delivery'],
    ])('reads the scope from a %s parent', async (parentType, expected) => {
        const { wrapper } = await createWrapper({ parentCondition: { id: 'parent', type: parentType } });

        expect((wrapper.find(APPLIES_TO_SELECT).element as HTMLSelectElement).value).toBe(expected);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('offers this delivery while the rule has no blocking assignment', async () => {
        const { wrapper } = await createWrapper();
        const delivery = wrapper.find('.sw-select-option--delivery');

        expect(delivery.attributes('data-disabled')).toBe('false');
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
        const delivery = wrapper.find('.sw-select-option--delivery');

        expect(delivery.attributes('data-disabled')).toBe('true');
        expect(delivery.attributes('tooltip-mock-disabled')).toBe('false');
        expect(delivery.attributes('tooltip-mock-message')).toBe(
            'sw-restricted-rules.restrictedConditions.restrictedConditionTooltip',
        );
        expect(wrapper.find('.sw-select-option--cart').attributes('data-disabled')).toBe('false');
    });

    it.activeFeatureFlags(['v6.8.0.0'])('disables the select with the condition', async () => {
        const { wrapper } = await createWrapper({ disabled: true });

        expect(wrapper.find(APPLIES_TO_SELECT).attributes('disabled')).toBeDefined();
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

        await wrapper.find(APPLIES_TO_SELECT).setValue('delivery');

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

        await wrapper.find(APPLIES_TO_SELECT).setValue('cart');

        expect(unwrapDeliveryCondition).toHaveBeenCalledWith(expect.objectContaining({ id: condition.id }));
    });
});
