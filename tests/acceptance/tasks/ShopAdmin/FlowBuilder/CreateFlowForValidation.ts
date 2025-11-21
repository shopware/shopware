import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

export const CreateFlowForValidation = base.extend<{ CreateFlowForValidation: Task }, FixtureTypes>({
    CreateFlowForValidation: async ({ ShopAdmin, AdminApiContext, IdProvider }, use) => {
        let flowId: string;
        const task = (flowConfig) => {
            return async function CreateRule() {
                flowId = flowConfig.flowId;
                const conditionId = IdProvider.getIdPair().uuid;
                const testFlow = {
                    id: flowId,
                    name: 'Test-Flow' + ' - ' + flowId,
                    eventName: 'state_enter.order.state.in_progress',
                    priority: 1,
                    active: true,
                    description:
                        'We wish you a Merry Christmas, we wish you a Merryyyy Chriiiiistmaaaaaaaas! Andahappynewyear!',
                    sequences: [
                        {
                            id: conditionId,
                            flowId: flowId,
                            ruleId: flowConfig.ruleId,
                            actionName: null,
                            config: [],
                            position: 1,
                            displayGroup: 1,
                            trueCase: false,
                            parentId: null,
                        },
                        {
                            flowId: flowId,
                            ruleId: null,
                            actionName: 'action.add.customer.tag',
                            config: {
                                entity: 'customer',
                                tagIds: {
                                    [flowConfig.tagFalse.id]: flowConfig.tagFalse.name,
                                },
                            },
                            position: 1,
                            displayGroup: 1,
                            trueCase: false,
                            parentId: conditionId,
                        },
                        {
                            flowId: flowId,
                            ruleId: null,
                            actionName: 'action.add.customer.tag',
                            config: {
                                entity: 'customer',
                                tagIds: {
                                    [flowConfig.tagTrue.id]: flowConfig.tagTrue.name,
                                },
                            },
                            position: 1,
                            displayGroup: 1,
                            trueCase: true,
                            parentId: conditionId,
                        },
                        {
                            flowId: flowId,
                            ruleId: null,
                            actionName: 'action.set.order.state',
                            config: {
                                order_transaction: 'paid',
                                order_delivery: 'shipped_partially',
                                force_transition: true,
                            },
                            position: 1,
                            displayGroup: 2,
                            trueCase: true,
                            parentId: conditionId,
                        },
                    ],
                };
                const flowResponse = await AdminApiContext.post('flow', {
                    data: testFlow,
                });
                ShopAdmin.expects(flowResponse.ok()).toBeTruthy();
            };
        };
        await use(task);

        await AdminApiContext.delete(`flow/${flowId}`);
    },
});
