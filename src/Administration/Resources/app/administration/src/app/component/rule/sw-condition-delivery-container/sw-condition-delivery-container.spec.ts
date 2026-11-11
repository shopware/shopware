/**
 * @sw-package fundamentals@after-sales
 */
import { mount } from '@vue/test-utils';
import { reactive } from 'vue';

type ConditionNode = Record<string, unknown>;

const treeNodeStub = {
    inject: ['unwrapDeliveryCondition'],
    props: [
        'condition',
        'parentCondition',
        'level',
        'disabled',
        'insertBefore',
        'insertAfter',
    ],
    template: `
        <div class="tree-node-stub" :data-type="condition.type" :data-level="level" :data-disabled="disabled">
            <button class="tree-node-stub__unwrap" @click="unwrapDeliveryCondition(condition)"></button>
            <button class="tree-node-stub__insert-before" @click="insertBefore()"></button>
            <button class="tree-node-stub__insert-after" @click="insertAfter()"></button>
        </div>
    `,
};

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
            stubs: {
                'sw-condition-tree-node': treeNodeStub,
            },
            provide: {
                conditionDataProviderService: {
                    getByType: (type: string) => ({
                        component: ['cartWeight', 'cartVolume'].includes(type)
                            ? 'sw-condition-generic-delivery'
                            : 'sw-condition-generic',
                    }),
                },
                childAssociationField: 'children',
                createCondition,
                insertNodeIntoTree,
                removeNodeFromTree,
            },
        },
    });

    return { wrapper, condition, createCondition, insertNodeIntoTree, removeNodeFromTree };
}

describe('src/app/component/rule/sw-condition-delivery-container', () => {
    it('renders its condition one level deeper', async () => {
        const { wrapper } = await createWrapper();
        const node = wrapper.find('.tree-node-stub');

        expect(node.attributes('data-type')).toBe('cartWeight');
        expect(node.attributes('data-level')).toBe('2');
    });

    it('passes the disabled state to its condition', async () => {
        const { wrapper } = await createWrapper({ disabled: true });

        expect(wrapper.find('.tree-node-stub').attributes('data-disabled')).toBe('true');
    });

    it('unwraps its condition when the condition asks for it', async () => {
        const { wrapper, condition, createCondition, insertNodeIntoTree, removeNodeFromTree } = await createWrapper();

        await wrapper.find('.tree-node-stub__unwrap').trigger('click');

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

        await wrapper.find(`.tree-node-stub__insert-${position}`).trigger('click');

        expect(wrapper.emitted(`create-${position}`)).toHaveLength(1);
    });
});
