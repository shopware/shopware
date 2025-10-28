import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

export const CreateRule = base.extend<{ CreateRule: Task }, FixtureTypes>({
    CreateRule: async ({ ShopAdmin, AdminApiContext }, use ) => {

        const task = (testConfig) => {
            return async function CreateRule() {

                const testRule = {
                    id: testConfig.ruleId,
                    name: testConfig.ruleName,
                    priority: testConfig.rulePriority,
                    description: testConfig.ruleDescription,
                    moduleTypes: {
                        types:
                            testConfig.ruleTypes.map(type => type.toLowerCase().split(' ')[0]),
                    },
                    tags: [
                        {
                            name: testConfig.ruleTag,
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
                                                count: testConfig.quantity,
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
                                                                        stock: testConfig.stock,
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
                                                toDate: testConfig.toDate,
                                                useTime: false,
                                                fromDate: testConfig.fromDate,
                                            },
                                        },
                                        {
                                            type: 'orContainer',
                                            children: [
                                                {
                                                    type: 'customerLastName',
                                                    value: {
                                                        lastName: testConfig.customerSurname,
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
                                                    testConfig.taxId,
                                                ],
                                                operator: '=',
                                            },
                                        },
                                        {
                                            type: 'timeRange',
                                            value: {
                                                toTime: testConfig.toDate.split('T')[1].substring(0, 5),
                                                fromTime: testConfig.fromDate.split('T')[1].substring(0, 5),
                                            },
                                        },
                                        {
                                            type: 'orContainer',
                                            children: [
                                                {
                                                    type: 'orderCreatedByAdmin',
                                                    value: {
                                                        shouldOrderBeCreatedByAdmin: testConfig.isAdminOrder,
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
