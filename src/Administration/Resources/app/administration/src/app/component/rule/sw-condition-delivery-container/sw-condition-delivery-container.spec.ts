/**
 * @sw-package fundamentals@after-sales
 */
import { mount } from '@vue/test-utils';
import { reactive, type ComponentPublicInstance } from 'vue';
import RuleConditionService from 'src/app/service/rule-condition.service';

type ConditionNode = Record<string, unknown>;

function createChildren(children: ConditionNode[] = []): EntityCollection<'rule_condition'> {
    return new Shopware.Data.EntityCollection(
        '/rule-condition',
        'rule_condition',
        Shopware.Context.api,
        null,
        children as unknown as EntitySchema.Entity<'rule_condition'>[],
    );
}

function createContainer(): { children: EntityCollection<'rule_condition'> } & ConditionNode {
    return reactive({
        id: 'delivery-container',
        type: 'deliveryContainer',
        position: 3,
        children: createChildren([
            {
                id: 'weight-condition',
                type: 'cartWeight',
                value: { operator: '>', weight: 30 },
                children: createChildren(),
            },
        ]),
    });
}

const parentCondition = { id: 'or-container', type: 'orContainer' };

function createConditionDataProviderService(): RuleConditionService {
    const service = new RuleConditionService();
    service.addCondition('cartWeight', { component: 'sw-condition-generic-delivery' });
    service.addCondition('cartVolume', { component: 'sw-condition-generic-delivery' });
    service.addCondition('customerGroup', { component: 'sw-condition-generic' });
    service.addCondition('scriptRule', {
        component: 'sw-condition-script',
        scriptId: 'script-id' as EntityKey<'script'>,
    });

    return service;
}

async function createWrapper({ disabled = false }: { disabled?: boolean } = {}) {
    const createCondition = jest.fn(() => ({ id: 'unwrapped-condition' }));
    const insertNodeIntoTree = jest.fn();
    const removeNodeFromTree = jest.fn();
    const condition = createContainer();

    const wrapper = mount(await wrapTestComponent('sw-condition-delivery-container', { sync: true }), {
        props: {
            condition,
            parentCondition,
            level: 1,
            disabled,
        },
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-condition-tree-node': await wrapTestComponent('sw-condition-tree-node', { sync: true }),
                'sw-condition-generic-delivery': await wrapTestComponent('sw-condition-generic-delivery', { sync: true }),
                'sw-condition-generic': true,
                'sw-condition-script': true,
                'sw-condition-type-select': true,
                'sw-condition-operator-select': true,
                'sw-form-field-renderer': true,
                'sw-condition-unit-menu': true,
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-field-error': true,
                'sw-single-select': true,
            },
            provide: {
                conditionDataProviderService: createConditionDataProviderService(),
                availableTypes: [],
                availableGroups: [],
                childAssociationField: 'children',
                createCondition,
                insertNodeIntoTree,
                removeNodeFromTree,
            },
        },
    });
    await flushPromises();

    return { wrapper, condition, createCondition, insertNodeIntoTree, removeNodeFromTree };
}

describe('src/app/component/rule/sw-condition-delivery-container', () => {
    it('renders its condition one level deeper', async () => {
        const { wrapper } = await createWrapper();
        const node = wrapper.getComponent<ComponentPublicInstance<{ level: number }>>('.sw-condition');

        expect(node.find('.sw-condition__condition-type-cartWeight').exists()).toBe(true);
        expect(node.props('level')).toBe(2);
    });

    it('passes the disabled state to its condition', async () => {
        const { wrapper } = await createWrapper({ disabled: true });

        expect(wrapper.get('.sw-condition__container').classes()).toContain('is--disabled');
    });

    it.activeFeatureFlags(['v6.8.0.0'])('unwraps its condition when it applies to the whole cart', async () => {
        const { wrapper, condition, createCondition, insertNodeIntoTree, removeNodeFromTree } = await createWrapper();

        await wrapper
            .getComponent<ComponentPublicInstance>('.sw-condition-generic-delivery__applies-to')
            .setValue('cart', 'value');

        expect(removeNodeFromTree).toHaveBeenCalledWith(parentCondition, condition);
        expect(createCondition).toHaveBeenCalledWith(
            expect.objectContaining({ type: 'cartWeight', value: { operator: '>', weight: 30 } }),
            'or-container',
            3,
        );
        expect(insertNodeIntoTree).toHaveBeenCalledWith(parentCondition, { id: 'unwrapped-condition' });
    });

    it('unwraps its condition when it changes to a type without the applies to choice', async () => {
        const { condition, createCondition, removeNodeFromTree } = await createWrapper();

        condition.children.first()!.type = 'customerGroup';
        await flushPromises();

        expect(removeNodeFromTree).toHaveBeenCalledWith(parentCondition, condition);
        expect(createCondition).toHaveBeenCalledWith(expect.objectContaining({ type: 'customerGroup' }), 'or-container', 3);
    });

    it('keeps the app script of a condition it unwraps', async () => {
        const { condition, createCondition } = await createWrapper();
        const appScriptCondition = { id: 'app-script-condition-id' };
        const child = condition.children.first()!;

        child.type = 'scriptRule';
        child.scriptId = 'script-id' as typeof child.scriptId;
        child.appScriptCondition = appScriptCondition as unknown as EntitySchema.Entity<'app_script_condition'>;
        await flushPromises();

        expect(createCondition).toHaveBeenCalledWith(
            expect.objectContaining({ type: 'scriptRule', scriptId: 'script-id', appScriptCondition }),
            'or-container',
            3,
        );
    });

    it('stays when its condition changes to another type with the applies to choice', async () => {
        const { condition, removeNodeFromTree } = await createWrapper();

        condition.children.first()!.type = 'cartVolume';
        await flushPromises();

        expect(removeNodeFromTree).not.toHaveBeenCalled();
    });

    it('removes itself once its condition is deleted', async () => {
        const { condition, createCondition, removeNodeFromTree } = await createWrapper();

        condition.children = createChildren();
        await flushPromises();

        expect(removeNodeFromTree).toHaveBeenCalledWith(parentCondition, condition);
        expect(createCondition).not.toHaveBeenCalled();
    });

    it.each(['before', 'after'])('inserts a new condition %s itself', async (position) => {
        const { wrapper } = await createWrapper();

        await wrapper.get(`.sw-condition__create-${position}-action`).trigger('click');

        expect(wrapper.emitted(`create-${position}`)).toHaveLength(1);
    });
});
