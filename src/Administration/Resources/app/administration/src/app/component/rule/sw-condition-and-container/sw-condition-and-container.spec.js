/**
 * @group disabledCompat
 */
import { mount, config } from '@vue/test-utils';

async function createWrapper(customProps = {}) {
    return mount(await wrapTestComponent('sw-condition-and-container', { sync: true }), {
        props: {
            condition: {
                id: 'base-condition-id',
                type: 'condition-and-container',
                children: [{
                    type: null,
                    position: 0,
                    children: [],
                }],
            },
            level: 0,
            ...customProps,
        },
    });
}

describe('src/app/component/rule/sw-condition-and-container', () => {
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
                    getOrContainerData() {
                        return {
                            type: 'condition-or-container',
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

    it('should have enabled condition tree', async () => {
        const wrapper = await createWrapper();

        const conditionTreeNode = wrapper.find('sw-condition-tree-node-stub');

        expect(conditionTreeNode.attributes().disabled).toBeUndefined();
    });

    it('should have disabled condition tree', async () => {
        const wrapper = await createWrapper({
            disabled: true,
        });

        const conditionTreeNode = wrapper.find('sw-condition-tree-node-stub');

        expect(conditionTreeNode.attributes().disabled).toBe('true');
    });

    it('should have enabled buttons', async () => {
        const wrapper = await createWrapper();

        const buttons = wrapper.findAllComponents('.sw-button');

        expect(buttons.length).toBeGreaterThan(0);
        buttons.forEach(button => {
            expect(button.attributes('disabled')).toBeUndefined();
        });
    });

    it('should have disabled buttons', async () => {
        const wrapper = await createWrapper({
            disabled: true,
        });

        const buttons = wrapper.findAllComponents('.sw-button');

        expect(buttons.length).toBeGreaterThan(0);
        buttons.forEach(button => {
            expect(button.attributes('disabled')).toBeDefined();
        });
    });

    it('creates placeholder if child list ist empty', async () => {
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

    it('creates a new or condition container and replaces placeholder child', async () => {
        const wrapper = await createWrapper();

        const addNewOrContainerButton = wrapper.getComponent(
            '.sw-button.sw-condition-and-container__actions--sub',
        );

        await addNewOrContainerButton.trigger('click');

        const condition = wrapper.props('condition');
        expect(condition.children).toHaveLength(1);
        expect(condition.children[0].type).toBe('condition-or-container');
        expect(condition.children[0].position).toBe(0);
    });

    it('creates a new or condition container after existing element node', async () => {
        const wrapper = await createWrapper({
            condition: {
                type: 'condition-and-container',
                children: [{
                    type: 'placeholder',
                    position: 0,
                    children: [],
                }],
            },
        });

        const addNewOrContainerButton = wrapper.getComponent(
            '.sw-button.sw-condition-and-container__actions--sub',
        );

        await addNewOrContainerButton.trigger('click');

        const condition = wrapper.props('condition');
        expect(condition.children).toHaveLength(2);
        expect(condition.children[0].type).toBe('placeholder');
        expect(condition.children[0].position).toBe(0);
        expect(condition.children[1].type).toBe('condition-or-container');
        expect(condition.children[1].position).toBe(1);
    });

    it('can be removed from tree', async () => {
        const removeNodeFromTreeSpy = jest.spyOn(config.global.provide, 'removeNodeFromTree');

        const andContainer = {
            type: 'condition-and-container',
            id: 'condition-id',
            children: [],
        };

        const wrapper = await createWrapper({
            parentCondition: {
                id: 'parent-condition',
                type: 'condition-or-container',
                children: [andContainer],
            },
            condition: andContainer,
        });

        const deleteButton = wrapper.getComponent(
            '.sw-button.sw-condition-and-container__actions--delete',
        );

        await deleteButton.trigger('click');

        expect(removeNodeFromTreeSpy).toHaveBeenCalled();
        expect(removeNodeFromTreeSpy).toHaveBeenCalledWith(
            wrapper.props('parentCondition'),
            andContainer,
        );

        const parentCondition = wrapper.props('parentCondition');
        expect(parentCondition.children).toHaveLength(0);
    });
});
