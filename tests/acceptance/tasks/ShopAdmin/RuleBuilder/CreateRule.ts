import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

export const CreateRule = base.extend<{ CreateRule: Task }, FixtureTypes>({
    CreateRule: async ({ ShopAdmin, AdminApiContext }, use ) => {

        const task = (ruleData    ) => {
            return async function CreateRule() {

                const testRule = {
                    id: ruleData.ruleId,
                    name: ruleData.ruleName,
                    priority: ruleData.rulePriority,
                    description: ruleData.ruleDescription,
                    moduleTypes: {
                        types:
                            ruleData.ruleTypes.map(type => type.toLowerCase().split(' ')[0]),
                    },
                    tags: [
                        {
                            name: ruleData.ruleTag,
                        },
                    ],
                    conditions: [
                        {
                            type: 'orContainer',
                            children: [
                                {
                                    type: 'andContainer',
                                    children: [
                                        {
                                            type: 'cartLineItemGoodsTotal',
                                            value: {
                                                count: ruleData.quantity,
                                                operator: '>=',
                                            },
                                            children: [
                                                {
                                                    type: 'orContainer',
                                                    children: [
                                                        {
                                                            type: 'andContainer',
                                                            children: [
                                                                {
                                                                    type: 'cartLineItemStock',
                                                                    value: {
                                                                        stock: ruleData.inStock,
                                                                        operator: '>=',
                                                                    },
                                                                },
                                                            ],
                                                        },
                                                    ],
                                                },
                                            ],
                                        },
                                        {
                                            type: 'dateRange',
                                            value: {
                                                toDate: ruleData.toDate,
                                                useTime: false,
                                                fromDate: ruleData.fromDate,
                                            },
                                        },
                                        {
                                            type: 'orContainer',
                                            children: [
                                                {
                                                    type: 'customerLastName',
                                                    value: {
                                                        lastName: ruleData.customerSurname,
                                                        operator: '=',
                                                    },
                                                },
                                            ],
                                        },
                                    ],
                                },
                                {
                                    type: 'andContainer',
                                    children: [
                                        {
                                            type: 'cartLineItemTaxation',
                                            value: {
                                                taxIds: [
                                                    ruleData.taxId,
                                                ],
                                                operator: '=',
                                            },
                                        },
                                        {
                                            type: 'timeRange',
                                            value: {
                                                toTime: ruleData.toDate.split('T')[1].substring(0, 5),
                                                fromTime: ruleData.fromDate.split('T')[1].substring(0, 5),
                                            },
                                        },
                                        {
                                            type: 'orContainer',
                                            children: [
                                                {
                                                    type: 'orderCreatedByAdmin',
                                                    value: {
                                                        shouldOrderBeCreatedByAdmin: ruleData.adminOrder,
                                                    },
                                                },
                                            ],
                                        },
                                    ],
                                },
                            ],
                        },
                    ],
                };
            const ruleResponse = await AdminApiContext.post('rule?_response=detail', {
                data: testRule,
            });
            ShopAdmin.expects(ruleResponse.ok()).toBeTruthy();
            };
        }
        await use(task);
    },
});
