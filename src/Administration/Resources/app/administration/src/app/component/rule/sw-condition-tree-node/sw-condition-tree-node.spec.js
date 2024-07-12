/**
 * @group disabledCompat
 */
import { mount, config } from '@vue/test-utils';

const subComponent = {
    props: ['condition', 'parentCondition', 'level', 'disabled'],
    template: '<div class="sw-condition-sub-component"></div>',
};

async function createWrapper(additionalProps = {}) {
    return mount(await wrapTestComponent('sw-condition-tree-node', { sync: true }), {
        props: {
            level: 3,
            condition: {
                id: 'condition-id',
                type: 'test-condition',
                position: 3,
            },
            parentCondition: {
                id: 'parent-condition-id',
                type: 'parent-condition-type',
                position: 0,
            },
            ...additionalProps,
        },
    });
}

describe('src/app/component/rule/sw-condition-tree-node', () => {
    beforeEach(() => {
        config.global = {
            ...config.global,
            stubs: {
                'sw-condition-sub-component': subComponent,
            },
            provide: {
                createCondition() {
                    return {
                        id: 'new-condition-id',
                        type: 'new-condition',
                    };
                },
                insertNodeIntoTree() {},
                removeNodeFromTree() {},
                conditionDataProviderService: {
                    getComponentByCondition() {
                        return 'sw-condition-sub-component';
                    },
                    getPlaceholderData() {
                        return {
                            type: 'placeholder',
                            id: 'placeholder-id',
                        };
                    },
                },
            },
        };
    });

    it('renders correct sub component', async () => {
        const wrapper = await createWrapper();

        const nodeComponent = wrapper.getComponent(subComponent);

        expect(nodeComponent.classes('sw-condition-sub-component')).toBe(true);
        expect(nodeComponent.classes('condition-tree-node')).toBe(true);
    });

    it('passes correct props to sub component', async () => {
        const wrapper = await createWrapper();

        const nodeComponent = wrapper.getComponent(subComponent);

        expect(nodeComponent.props('disabled')).toBe(false);
        expect(nodeComponent.props('condition')).toEqual({
            id: 'condition-id',
            type: 'test-condition',
            position: 3,
        });
        expect(nodeComponent.props('parentCondition')).toEqual({
            id: 'parent-condition-id',
            type: 'parent-condition-type',
            position: 0,
        });
        expect(nodeComponent.props('level')).toBe(3);
    });

    it('deletes condition if triggered from sub component', async () => {
        const removeNodeFromTreeSpy = jest.spyOn(config.global.provide, 'removeNodeFromTree');

        const wrapper = await createWrapper();
        const nodeComponent = wrapper.getComponent(subComponent);

        nodeComponent.vm.$emit('condition-delete');

        expect(removeNodeFromTreeSpy).toHaveBeenCalled();
        expect(removeNodeFromTreeSpy).toHaveBeenCalledWith(
            wrapper.props('parentCondition'),
            wrapper.props('condition'),
        );
    });

    it('inserts a new node before the current one if triggered from sub component', async () => {
        const createConditionSpy = jest.spyOn(config.global.provide, 'createCondition');
        const insertNodeIntoTreeSpy = jest.spyOn(config.global.provide, 'insertNodeIntoTree');

        const wrapper = await createWrapper();
        const nodeComponent = wrapper.getComponent(subComponent);

        nodeComponent.vm.$emit('create-before');

        expect(createConditionSpy).toHaveBeenCalled();
        expect(createConditionSpy).toHaveBeenCalledWith(
            {
                type: 'placeholder',
                id: 'placeholder-id',
            },
            wrapper.props('parentCondition').id,
            wrapper.props('condition').position,
            [],
        );

        expect(insertNodeIntoTreeSpy).toHaveBeenCalled();
        expect(insertNodeIntoTreeSpy).toHaveBeenCalledWith(
            wrapper.props('parentCondition'),
            {
                id: 'new-condition-id',
                type: 'new-condition',
            },
        );
    });

    it('inserts node before with custom function if given', async () => {
        const insertBefore = jest.fn();

        const wrapper = await createWrapper({
            insertBefore,
        });
        const nodeComponent = wrapper.getComponent(subComponent);

        nodeComponent.vm.$emit('create-before');

        expect(insertBefore).toHaveBeenCalled();
    });

    it('inserts a new node after the current one if triggered from sub component', async () => {
        const createConditionSpy = jest.spyOn(config.global.provide, 'createCondition');
        const insertNodeIntoTreeSpy = jest.spyOn(config.global.provide, 'insertNodeIntoTree');

        const wrapper = await createWrapper();
        const nodeComponent = wrapper.getComponent(subComponent);

        nodeComponent.vm.$emit('create-after');

        expect(createConditionSpy).toHaveBeenCalled();
        expect(createConditionSpy).toHaveBeenCalledWith(
            {
                type: 'placeholder',
                id: 'placeholder-id',
            },
            wrapper.props('parentCondition').id,
            wrapper.props('condition').position + 1,
            [],
        );

        expect(insertNodeIntoTreeSpy).toHaveBeenCalled();
        expect(insertNodeIntoTreeSpy).toHaveBeenCalledWith(
            wrapper.props('parentCondition'),
            {
                id: 'new-condition-id',
                type: 'new-condition',
            },
        );
    });

    it('inserts node after with custom function if given', async () => {
        const insertAfter = jest.fn();

        const wrapper = await createWrapper({
            insertAfter,
        });
        const nodeComponent = wrapper.getComponent(subComponent);

        nodeComponent.vm.$emit('create-after');

        expect(insertAfter).toHaveBeenCalled();
    });
});
