/**
 * @sw-package inventory
 */
import { mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, reactive } from 'vue';
import EntityDefinitionFactory from 'src/core/factory/entity-definition.factory';
import ProductStreamConditionService from 'src/app/service/product-stream-condition.service';

type Condition = {
    field: string | null;
    type: string | null;
    value: string | null;
    parameters: Record<string, number | null> | null;
    queries?: Condition[];
};

const FIELD = 'cheapestPrice.percentage';

const productStreamValue = defineComponent({
    name: 'sw-product-stream-value',
    emits: [
        'type-change',
        'empty-change',
    ],
    template: '<div class="sw-product-stream-value"></div>',
});

function condition(type: string, value: string | null, parameters: Condition['parameters'] = null): Condition {
    return { field: FIELD, type, value, parameters };
}

function negated(query: Condition): Condition {
    return { field: null, type: 'not', value: null, parameters: null, queries: [query] };
}

async function createWrapper(initialCondition: Condition) {
    Shopware.EntityDefinition = EntityDefinitionFactory;
    Shopware.EntityDefinition.add('product', { entity: 'product', properties: {} });

    return mount(await wrapTestComponent('sw-product-stream-filter', { sync: true }), {
        props: {
            condition: reactive(initialCondition),
        },
        global: {
            stubs: {
                'sw-condition-type-select': true,
                'sw-text-field': true,
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-field-error': true,
                'sw-product-stream-value': productStreamValue,
                'sw-product-stream-field-select': true,
            },
            provide: {
                conditionDataProviderService: ProductStreamConditionService() as unknown,
                availableTypes: {},
                availableGroups: [],
                childAssociationField: {},
                createCondition: (conditionData: Condition) => ({ ...conditionData }),
                insertNodeIntoTree: (parent: Condition, child: Condition) => {
                    parent.queries = [child];
                },
                removeNodeFromTree: (parent: Condition, child: Condition) => {
                    parent.queries = (parent.queries ?? []).filter((query) => query !== child);
                },
                productCustomFields: {},
                acl: { can: () => true },
            },
        },
    });
}

describe('src/module/sw-product-stream/component/sw-product-stream-filter/sw-product-stream-filter.empty-operator.spec.ts', () => {
    let wrapper: VueWrapper;

    afterEach(() => {
        wrapper?.unmount();
    });

    function currentCondition(): Condition {
        return wrapper.props('condition') as Condition;
    }

    it('saves "Is empty" as an equals condition without a value and without the bounds of the range before', async () => {
        wrapper = await createWrapper(condition('range', null, { lt: 100 }));

        wrapper.getComponent(productStreamValue).vm.$emit('empty-change', { type: 'equals' });

        expect(currentCondition()).toEqual(condition('equals', null));
    });

    it('saves "Is not empty" as a negated equals condition without a value', async () => {
        wrapper = await createWrapper(condition('equals', '100'));

        wrapper.getComponent(productStreamValue).vm.$emit('empty-change', { type: 'notEquals' });

        expect(currentCondition().type).toBe('not');
        expect(currentCondition().queries).toEqual([condition('equals', null)]);
    });

    it('changes "Is not empty" to "Is empty"', async () => {
        wrapper = await createWrapper(negated(condition('equals', null)));

        wrapper.getComponent(productStreamValue).vm.$emit('empty-change', { type: 'equals' });

        expect(currentCondition()).toEqual({ ...condition('equals', null), queries: [] });
    });

    it.each([
        ['"Is empty" to "Is equal to"', condition('equals', null), 'equals'],
        ['"Is not empty" to "Is equal to"', negated(condition('equals', null)), 'equals'],
    ])('sets the default value when changing %s', async (_description, initialCondition, type) => {
        wrapper = await createWrapper(initialCondition);

        wrapper.getComponent(productStreamValue).vm.$emit('type-change', { type, parameters: null });

        expect(currentCondition().type).toBe('equals');
        expect(currentCondition().value).toBe('100');
    });

    it.each([
        ['"Is empty" to "Is not equal to"', condition('equals', null)],
        ['"Is not empty" to "Is not equal to"', negated(condition('equals', null))],
        ['a range to "Is not equal to"', condition('range', null, { lt: 100 })],
    ])('sets the default value when changing %s', async (_description, initialCondition) => {
        wrapper = await createWrapper(initialCondition);

        wrapper.getComponent(productStreamValue).vm.$emit('type-change', { type: 'notEquals', parameters: null });

        expect(currentCondition().type).toBe('not');
        expect(currentCondition().queries).toEqual([condition('equals', '100')]);
    });

    it('keeps the value when a condition with a value is negated', async () => {
        wrapper = await createWrapper(condition('equals', '50'));

        wrapper.getComponent(productStreamValue).vm.$emit('type-change', { type: 'notEquals', parameters: null });

        expect(currentCondition().queries).toEqual([condition('equals', '50')]);
    });
});
