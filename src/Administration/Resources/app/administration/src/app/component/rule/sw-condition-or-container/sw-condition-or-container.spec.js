/**
 * @group disabledCompat
 */
import { shallowMount, config } from '@vue/test-utils';

async function createWrapper(customProps = {}) {
    return shallowMount(await wrapTestComponent('sw-condition-or-container', { sync: true }), {
        props: {
            condition: {
                id: 'base-condition-id',
                type: 'condition-or-container',
                children: [{
                    type: null,
                    position: 0,
                    children: [],
                }],
            },
            level: 1,
            ...customProps,
        },
    });
}

describe('src/app/component/rule/sw-condition-or-container', () => {
    beforeAll(async () => {
        config.global = {
            ...config.global,
            stubs: {
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                'sw-condition-tree-node': true,
                'sw-loader': true,
                'router-link': true,
                'mt-button': true,
            },
            provide: {
                conditionDataProviderService: {
                    getPlaceholderData() {
                        return {
                            type: 'placeholder',
                            children: [],
                        };
                    },
                    getAndContainerData() {
                        return {
                            type: 'condition-and-container',
                            children: [],
                        };
                    },
                },
                createCondition: (data, parentId, position) => {
                    return { ...data, parentId, position };
                },
                insertNodeTree: {},
                insertNodeIntoTree: (condition, node) => {
                    condition.children.push(node);
                },
                removeNodeFromTree(condition, node) {
                    condition.children = condition.children.filter((child) => {
                        return child !== node;
                    }).map((child, index) => {
                        return { ...child, position: index };
                    });
                },
                childAssociationField: 'children',
                acl: {
                    can: () => true,
                },
            },
        };
    });

    it('should have enabled fields', async () => {
        const wrapper = await createWrapper();

        const conditionTreeNode = wrapper.find('sw-condition-tree-node-stub');
        const buttons = wrapper.findAllComponents('.sw-button');

        expect(conditionTreeNode.attributes().disabled).toBeUndefined();

        expect(buttons.length).toBeGreaterThan(0);
        buttons.forEach(button => {
            expect(button.attributes('disabled')).toBeUndefined();
        });
    });

    it('should have disabled fields', async () => {
        const wrapper = await createWrapper({
            disabled: true,
        });

        const conditionTreeNode = wrapper.find('sw-condition-tree-node-stub');
        const buttons = wrapper.findAllComponents('.sw-button');

        expect(conditionTreeNode.attributes().disabled).toBe('true');

        expect(buttons.length).toBeGreaterThan(0);
        buttons.forEach(button => {
            expect(button.attributes('disabled')).toBeDefined();
        });
    });

    it('creates a new and container if level is zero', async () => {
        const insertNodeIntoTreeSpy = jest.spyOn(config.global.provide, 'insertNodeIntoTree');

        const wrapper = await createWrapper({
            condition: {
                id: 'base-condition-id',
                type: 'condition-or-container',
                position: 0,
                children: [],
            },
            level: 0,
        });

        expect(insertNodeIntoTreeSpy).toHaveBeenCalled();
        expect(insertNodeIntoTreeSpy).toHaveBeenCalledWith(
            wrapper.props('condition'),
            {
                type: 'condition-and-container',
                children: [],
                parentId: wrapper.props('condition').id,
                position: 0,
            },
        );
    });

    it('creates placeholder if child list ist empty for deeper levels', async () => {
        const insertNodeIntoTreeSpy = jest.spyOn(config.global.provide, 'insertNodeIntoTree');

        const wrapper = await createWrapper({
            condition: {
                id: 'base-condition-id',
                type: 'condition-and-container',
                position: 0,
                children: [],
            },
        });

        expect(insertNodeIntoTreeSpy).toHaveBeenCalled();
        expect(insertNodeIntoTreeSpy).toHaveBeenCalledWith(
            wrapper.props('condition'),
            {
                type: 'placeholder',
                children: [],
                parentId: wrapper.props('condition').id,
                position: 0,
            },
        );
    });

    it('creates a new and condition container and replaces placeholder child', async () => {
        const wrapper = await createWrapper();

        const addNewAndContainerButton = wrapper.getComponent(
            '.sw-button.sw-condition-or-container__actions--sub',
        );

        await addNewAndContainerButton.trigger('click');

        const condition = wrapper.props('condition');
        expect(condition.children).toHaveLength(1);
        expect(condition.children[0].type).toBe('condition-and-container');
        expect(condition.children[0].position).toBe(0);
    });

    it('creates a new or condition container after existing element node', async () => {
        const wrapper = await createWrapper({
            condition: {
                type: 'condition-or-container',
                children: [{
                    type: 'placeholder',
                    position: 0,
                    children: [],
                }],
            },
        });

        const addNewAndContainerButton = wrapper.getComponent(
            '.sw-button.sw-condition-or-container__actions--sub',
        );

        await addNewAndContainerButton.trigger('click');

        const condition = wrapper.props('condition');
        expect(condition.children).toHaveLength(2);
        expect(condition.children[0].type).toBe('placeholder');
        expect(condition.children[0].position).toBe(0);
        expect(condition.children[1].type).toBe('condition-and-container');
        expect(condition.children[1].position).toBe(1);
    });

    it('can be removed from tree', async () => {
        const removeNodeFromTreeSpy = jest.spyOn(config.global.provide, 'removeNodeFromTree');

        const orContainer = {
            type: 'condition-or-container',
            id: 'condition-id',
            children: [],
        };

        const wrapper = await createWrapper({
            parentCondition: {
                id: 'parent-condition',
                type: 'condition-and-container',
                children: [orContainer],
            },
            condition: orContainer,
        });

        const deleteAllButton = wrapper.getComponent(
            '.sw-button.sw-condition-or-container__actions--delete',
        );

        await deleteAllButton.trigger('click');

        expect(removeNodeFromTreeSpy).toHaveBeenCalled();
        expect(removeNodeFromTreeSpy).toHaveBeenCalledWith(
            wrapper.props('parentCondition'),
            orContainer,
        );

        const parentCondition = wrapper.props('parentCondition');
        expect(parentCondition.children).toHaveLength(0);
    });

    it('only removes children if from tree if level is zero', async () => {
        const removeNodeFromTreeSpy = jest.spyOn(config.global.provide, 'removeNodeFromTree');

        const subCondition = {
            type: 'sub-condition',
            id: 'condition-id',
            position: 0,
            children: [],
        };

        const wrapper = await createWrapper({
            condition: {
                type: 'condition-or-container',
                id: 'condition-id',
                children: [subCondition],
            },
            level: 0,
        });

        const deleteAllButton = wrapper.getComponent(
            '.sw-button.sw-condition-or-container__actions--delete',
        );

        await deleteAllButton.trigger('click');

        expect(removeNodeFromTreeSpy).toHaveBeenCalled();
        expect(removeNodeFromTreeSpy).toHaveBeenCalledWith(
            wrapper.props('condition'),
            subCondition,
        );

        // automatically added new and container
        expect(wrapper.props('condition').children).toHaveLength(1);
    });
});
