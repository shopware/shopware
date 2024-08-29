/**
 * @package services-settings
 */
import { shallowMount, config } from '@vue/test-utils';
import RuleConditionService from 'src/app/service/rule-condition.service';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';

const swConditionTreeNode = {
    inject: [
        'availableTypes',
        'availableGroups',
        'createCondition',
        'insertNodeIntoTree',
        'removeNodeFromTree',
        'childAssociationField',
        'conditionDataProviderService',
        'conditionScopes',
        'restrictedConditions',
    ],
    props: ['disabled'],
    template: '<div class="sw-condition-tree-node"> {{ availableTypes }} </div>',
};

config.global = {
    ...config.global,
    stubs: {
        'sw-condition-tree-node': swConditionTreeNode,
    },
};

async function createWrapper(customProps = {}) {
    return shallowMount(await wrapTestComponent('sw-condition-tree', { sync: true }), {
        props: {
            conditionDataProviderService: new RuleConditionService(),
            conditionRepository: {
                create: () => {
                    return {
                        apiAlias: null,
                        children: new EntityCollection('', 'rule_condition', Shopware.Context.api),
                        customFields: null,
                        id: 'id1',
                        parentId: null,
                        position: 0,
                        type: 'orContainer',
                        updatedAt: null,
                        value: {},
                    };
                },
            },
            associationField: 'ruleId',
            associationValue: 'rule_uuid',
            ...customProps,
        },
        global: {
            stubs: {
                'sw-loader': {
                    template: '<div class="sw-loader"></div>',
                },
            },
        },
    });
}

describe('src/app/component/rule/sw-condition-tree', () => {
    describe('renders correct components', () => {
        it('should show loader when condition tree is null', async () => {
            const wrapper = await createWrapper({
                initialConditions: null,
            });
            await flushPromises();

            expect(wrapper.vm.conditionTree).toBeNull();

            const swLoader = wrapper.find('.sw-loader');

            expect(swLoader.exists()).toBe(true);
        });

        it('should have enabled condition tree node', async () => {
            const wrapper = await createWrapper();
            await wrapper.setData({ conditionTree: {} });

            const conditionTreeNode = wrapper.getComponent(swConditionTreeNode);

            expect(conditionTreeNode.props('disabled')).toBe(false);
        });

        it('should have disabled condition type select', async () => {
            const wrapper = await createWrapper({
                disabled: true,
            });
            await wrapper.setData({ conditionTree: {} });

            const conditionTreeNode = wrapper.getComponent(swConditionTreeNode);

            expect(conditionTreeNode.props('disabled')).toBe(true);
        });
    });

    describe('created', () => {
        function createInitialConditionsCollection() {
            return new EntityCollection(
                null,
                'rule_condition',
                null,
                new Criteria(),
                [
                    {
                        apiAlias: null,
                        children: new EntityCollection('', 'rule_condition', Shopware.Context.api),
                        customFields: null,
                        id: 'first-initial_condition_id',
                        parentId: null,
                        ruleId: 'rule_uuid',
                        position: 0,
                        type: 'customerCustomerGroup',
                    }, {
                        apiAlias: null,
                        children: new EntityCollection('', 'rule_condition', Shopware.Context.api),
                        customFields: null,
                        id: 'second-initial_condition_id',
                        parentId: null,
                        ruleId: 'rule_uuid',
                        position: 1,
                        type: 'customerCustomerGroup',
                    },
                ],
            );
        }

        it('should emit initial-loading-done after initial loading', async () => {
            const wrapper = await createWrapper({
                initialConditions: createInitialConditionsCollection(),
            });

            expect(wrapper.emitted()).toHaveProperty('initial-loading-done');
        });

        it('should add root container to initial conditions', async () => {
            const wrapper = await createWrapper({
                initialConditions: createInitialConditionsCollection(),
            });

            await flushPromises();

            expect(wrapper.emitted('conditions-changed')).toHaveLength(1);

            const { deletedIds, conditions } = wrapper.emitted('conditions-changed')[0][0];

            expect(deletedIds).toEqual([]);

            expect(conditions).toEqual(expect.arrayContaining([
                expect.objectContaining({
                    id: 'id1',
                    type: 'orContainer',
                    parentId: null,
                    ruleId: 'rule_uuid',
                    children: expect.arrayContaining([
                        expect.objectContaining({
                            apiAlias: null,
                            children: expect.arrayContaining([]),
                            customFields: null,
                            id: 'first-initial_condition_id',
                            parentId: 'id1',
                            ruleId: 'rule_uuid',
                            position: 0,
                            type: 'customerCustomerGroup',
                        }), expect.objectContaining({
                            apiAlias: null,
                            children: expect.arrayContaining([]),
                            customFields: null,
                            id: 'second-initial_condition_id',
                            parentId: 'id1',
                            ruleId: 'rule_uuid',
                            position: 1,
                            type: 'customerCustomerGroup',
                        }),
                    ]),
                }),
            ]));
        });
    });

    describe('provides', () => {
        function createInitialOrContainer() {
            return new EntityCollection(
                null,
                'rule_condition',
                Shopware.Context.api,
                new Criteria(),
                [
                    {
                        id: 'id1',
                        children: new EntityCollection('', 'rule_condition', Shopware.Context.api),
                        type: 'orContainer',
                        parentId: null,
                    },
                ],
            );
        }

        describe('availableTypes', () => {
            const conditionDataProviderService = new RuleConditionService();
            conditionDataProviderService.addCondition('checkout', {
                component: 'sw-condition-component',
                label: 'test condition',
                scopes: ['checkout'],
                appId: null,
                appScriptCondition: null,
            });

            conditionDataProviderService.addCondition('cart', {
                component: 'sw-condition-component',
                label: 'test condition',
                scopes: ['cart'],
                group: 'customer',
                appId: null,
                appScriptCondition: null,
            });

            it('returns unordered conditions if no groups are available', async () => {
                const wrapper = await createWrapper({
                    initialConditions: createInitialOrContainer(),
                    conditionDataProviderService,
                });

                const node = wrapper.getComponent(swConditionTreeNode);

                expect(node.vm.availableTypes).toBeDefined();
                expect(node.vm.availableTypes).toEqual([
                    expect.objectContaining({
                        type: 'cart',
                        group: 'customer',
                        scopes: ['cart'],
                    }), expect.objectContaining({
                        type: 'checkout',
                        group: 'misc',
                        scopes: ['checkout'],
                    }),
                ]);
            });

            it('orders by group', async () => {
                const wrapper = await createWrapper({
                    initialConditions: createInitialOrContainer(),
                    conditionDataProviderService,
                });

                const node = wrapper.getComponent(swConditionTreeNode);

                expect(node.vm.availableTypes).toBeDefined();
                expect(node.vm.availableTypes).toEqual([
                    expect.objectContaining({
                        type: 'cart',
                        group: 'customer',
                        scopes: ['cart'],
                    }), expect.objectContaining({
                        type: 'checkout',
                        group: 'misc',
                        scopes: ['checkout'],
                    }),
                ]);
            });

            it('provides filters for available types', async () => {
                const wrapper = await createWrapper({
                    initialConditions: createInitialOrContainer(),
                    allowedTypes: ['cart'],
                    conditionDataProviderService,
                });

                const node = wrapper.getComponent(swConditionTreeNode);

                expect(node.vm.availableTypes).toBeDefined();
                expect(node.vm.availableTypes).toEqual([
                    expect.objectContaining({
                        type: 'cart',
                        group: 'customer',
                        scopes: ['cart'],
                    }),
                ]);
            });

            it('filters for scopes', async () => {
                const wrapper = await createWrapper({
                    initialConditions: createInitialOrContainer(),
                    scopes: ['checkout'],
                    conditionDataProviderService,
                });

                const node = wrapper.getComponent(swConditionTreeNode);

                expect(node.vm.availableTypes).toBeDefined();
                expect(node.vm.availableTypes).toEqual([
                    expect.objectContaining({
                        type: 'checkout',
                        group: 'misc',
                        scopes: ['checkout'],
                    }),
                ]);
            });
        });

        describe('availableGroups', () => {
            it('provides correct groups from service and moves misc to the end', async () => {
                const conditionDataProviderService = new RuleConditionService();
                conditionDataProviderService.getGroups = () => {
                    return [{
                        id: 'misc',
                        name: 'misc.group.name',
                    }, {
                        id: 'test-group',
                        name: 'test.group.name',
                    }];
                };

                const wrapper = await createWrapper({
                    initialConditions: createInitialOrContainer(),
                    conditionDataProviderService,
                });

                const node = wrapper.getComponent(swConditionTreeNode);

                expect(node.vm.availableGroups).toBeDefined();

                expect(node.vm.availableGroups).toEqual([{
                    id: 'test-group',
                    name: 'test.group.name',
                    label: 'test.group.name',
                }, {
                    id: 'misc',
                    name: 'misc.group.name',
                    label: 'misc.group.name',
                }]);
            });

            it('should sort "general" group to the start', async () => {
                const conditionDataProviderService = new RuleConditionService();
                conditionDataProviderService.getGroups = () => {
                    return [{
                        id: 'test-group',
                        name: 'test.group.name',
                    }, {
                        id: 'general',
                        name: 'general.group.name',
                    }];
                };

                const wrapper = await createWrapper({
                    initialConditions: createInitialOrContainer(),
                    conditionDataProviderService,
                });

                const node = wrapper.getComponent(swConditionTreeNode);

                expect(node.vm.availableGroups).toBeDefined();

                expect(node.vm.availableGroups).toEqual([{
                    id: 'general',
                    name: 'general.group.name',
                    label: 'general.group.name',
                }, {
                    id: 'test-group',
                    name: 'test.group.name',
                    label: 'test.group.name',
                }]);
            });

            it('return empty array if getGroups is not defined', async () => {
                const conditionDataProviderService = new RuleConditionService();
                conditionDataProviderService.getGroups = undefined;

                const wrapper = await createWrapper({
                    initialConditions: createInitialOrContainer(),
                    conditionDataProviderService,
                });

                const node = wrapper.getComponent(swConditionTreeNode);

                expect(node.vm.availableGroups).toBeDefined();
                expect(node.vm.availableGroups).toEqual([]);
            });
        });

        describe('restrictedConditions', () => {
            it('provides correct conditions from conditionDataProviderService.getRestrictedConditions', async () => {
                const conditionDataProviderService = new RuleConditionService();
                conditionDataProviderService.getRestrictedConditions = () => {
                    return [{
                        type: 'checkout',
                        id: 'test-condition',
                    }];
                };

                const wrapper = await createWrapper({
                    initialConditions: createInitialOrContainer(),
                    conditionDataProviderService,
                });

                const node = wrapper.getComponent(swConditionTreeNode);

                expect(node.vm.restrictedConditions).toBeDefined();
                expect(node.vm.restrictedConditions).toEqual([{
                    type: 'checkout',
                    id: 'test-condition',
                }]);
            });

            it('return empty array if getRestrictedConditions is not defined', async () => {
                const conditionDataProviderService = new RuleConditionService();
                conditionDataProviderService.getRestrictedConditions = undefined;

                const wrapper = await createWrapper({
                    initialConditions: createInitialOrContainer(),
                    conditionDataProviderService,
                });

                const node = wrapper.getComponent(swConditionTreeNode);

                expect(node.vm.restrictedConditions).toBeDefined();
                expect(node.vm.restrictedConditions).toEqual([]);
            });
        });

        describe('createCondition', () => {
            it('can create a new condition', async () => {
                const wrapper = await createWrapper({
                    initialConditions: createInitialOrContainer(),
                });

                const node = wrapper.getComponent(swConditionTreeNode);

                expect(node.vm.createCondition).toBeDefined();
                expect(node.vm.createCondition({ someField: 'foo' }, 'parent-id', 23)).toEqual(expect.objectContaining({
                    id: 'id1',
                    type: 'orContainer',
                    someField: 'foo',
                    position: 23,
                    ruleId: 'rule_uuid',
                }));
            });
        });

        function createInitialTree() {
            return {
                id: 'parent-id',
                children: new EntityCollection('', 'rule_condition', Shopware.Context.api, null, [{
                    id: 'id-first-child',
                    position: 0,
                    parentId: 'parent-id',
                }, {
                    id: 'id-last-child',
                    position: 1,
                    parentId: 'parent-id',
                }]),
            };
        }

        describe('insertNodeIntoTree', () => {
            it('inserts a node at the beginning of a tree', async () => {
                const wrapper = await createWrapper({
                    initialConditions: createInitialOrContainer(),
                });

                const node = wrapper.getComponent(swConditionTreeNode);

                expect(node.vm.insertNodeIntoTree).toBeDefined();
                const tree = createInitialTree();

                node.vm.insertNodeIntoTree(tree, {
                    id: 'to-be-inserted-id',
                    parentId: 'parent-id',
                });

                expect(tree).toEqual({
                    id: 'parent-id',
                    children: expect.arrayContaining([{
                        id: 'to-be-inserted-id',
                        parentId: 'parent-id',
                        position: 0,
                    }, {
                        id: 'id-first-child',
                        position: 1,
                        parentId: 'parent-id',
                    }, {
                        id: 'id-last-child',
                        position: 2,
                        parentId: 'parent-id',
                    }]),
                });
            });

            it('bounds position to zero and the length of children', async () => {
                const wrapper = await createWrapper({
                    initialConditions: createInitialOrContainer(),
                });

                const node = wrapper.getComponent(swConditionTreeNode);

                expect(node.vm.insertNodeIntoTree).toBeDefined();
                const tree = createInitialTree();

                node.vm.insertNodeIntoTree(tree, {
                    id: 'should-be-position-0',
                    parentId: 'parent-id',
                    position: -500,
                });

                node.vm.insertNodeIntoTree(tree, {
                    id: 'should-be-position-3',
                    parentId: 'parent-id',
                    position: 9001,
                });

                expect(tree).toEqual({
                    id: 'parent-id',
                    children: expect.arrayContaining([{
                        id: 'should-be-position-0',
                        parentId: 'parent-id',
                        position: 0,
                    }, {
                        id: 'id-first-child',
                        position: 1,
                        parentId: 'parent-id',
                    }, {
                        id: 'id-last-child',
                        position: 2,
                        parentId: 'parent-id',
                    }, {
                        id: 'should-be-position-3',
                        parentId: 'parent-id',
                        position: 3,
                    }]),
                });
            });

            it('throws error if parent condition is not set', async () => {
                const wrapper = await createWrapper({
                    initialConditions: createInitialOrContainer(),
                });

                const node = wrapper.getComponent(swConditionTreeNode);

                expect(node.vm.insertNodeIntoTree).toBeDefined();
                expect(() => { node.vm.insertNodeIntoTree(null, { id: 'id' }); }).toThrow();
            });
        });

        describe('removeNodeFromTree', () => {
            it('removes node and emits changes', async () => {
                const wrapper = await createWrapper({
                    initialConditions: createInitialOrContainer(),
                });

                const node = wrapper.getComponent(swConditionTreeNode);

                expect(node.vm.removeNodeFromTree).toBeDefined();

                const tree = createInitialTree();
                const nodeToBeRemoved = tree.children[0];

                nodeToBeRemoved.isNew = () => true;
                nodeToBeRemoved.children = [{
                    id: 'child-node-to-be-removed',
                    parentId: nodeToBeRemoved.id,
                    position: 0,
                    isNew: () => false,
                    children: [{
                        id: 'child-of-child-node-to-be-removed',
                        parentId: 'child-node-to-be-removed',
                        position: 0,
                        isNew: () => false,
                    }],
                }];

                node.vm.removeNodeFromTree(tree, nodeToBeRemoved);

                expect(tree).toEqual({
                    id: 'parent-id',
                    children: expect.arrayContaining([{
                        id: 'id-last-child',
                        position: 0,
                        parentId: 'parent-id',
                    }]),
                });

                // conditions-changes is also emitted at mounting
                expect(wrapper.emitted('conditions-changed')).toHaveLength(2);

                const { deletedIds } = wrapper.emitted('conditions-changed')[1][0];
                expect(deletedIds).toEqual([
                    'child-node-to-be-removed',
                ]);
            });

            it('throws error if parent condition is not set', async () => {
                const wrapper = await createWrapper({
                    initialConditions: createInitialOrContainer(),
                });

                const node = wrapper.getComponent(swConditionTreeNode);

                expect(node.vm.removeNodeFromTree).toBeDefined();
                expect(() => { node.vm.removeNodeFromTree(null, { id: 'id' }); }).toThrow();
            });
        });
    });
});
