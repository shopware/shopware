import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

export const CreateRule = base.extend<{ CreateRule: Task }, FixtureTypes>({
    CreateRule: async ({ ShopAdmin, AdminApiContext }, use ) => {

<<<<<<< HEAD
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
=======
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
>>>>>>> 60e330ba69 (test: add CreateRule task)
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
<<<<<<< HEAD
                                                count: testConfig.quantity,
=======
                                                count: ruleData.quantity,
>>>>>>> 60e330ba69 (test: add CreateRule task)
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
<<<<<<< HEAD
                                                                        stock: testConfig.stock,
=======
                                                                        stock: ruleData.inStock,
>>>>>>> 60e330ba69 (test: add CreateRule task)
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
<<<<<<< HEAD
                                                toDate: testConfig.toDate,
                                                useTime: false,
                                                fromDate: testConfig.fromDate,
=======
                                                toDate: ruleData.toDate,
                                                useTime: false,
                                                fromDate: ruleData.fromDate,
>>>>>>> 60e330ba69 (test: add CreateRule task)
                                            },
                                        },
                                        {
                                            type: 'orContainer',
                                            children: [
                                                {
                                                    type: 'customerLastName',
                                                    value: {
<<<<<<< HEAD
                                                        lastName: testConfig.customerSurname,
=======
                                                        lastName: ruleData.customerSurname,
>>>>>>> 60e330ba69 (test: add CreateRule task)
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
<<<<<<< HEAD
                                                    testConfig.taxId,
=======
                                                    ruleData.taxId,
>>>>>>> 60e330ba69 (test: add CreateRule task)
                                                ],
                                                operator: '=',
                                            },
                                        },
                                        {
                                            type: 'timeRange',
                                            value: {
<<<<<<< HEAD
                                                toTime: testConfig.toDate.split('T')[1].substring(0, 5),
                                                fromTime: testConfig.fromDate.split('T')[1].substring(0, 5),
=======
                                                toTime: ruleData.toDate.split('T')[1].substring(0, 5),
                                                fromTime: ruleData.fromDate.split('T')[1].substring(0, 5),
>>>>>>> 60e330ba69 (test: add CreateRule task)
                                            },
                                        },
                                        {
                                            type: 'orContainer',
                                            children: [
                                                {
                                                    type: 'orderCreatedByAdmin',
                                                    value: {
<<<<<<< HEAD
                                                        shouldOrderBeCreatedByAdmin: testConfig.isAdminOrder,
=======
                                                        shouldOrderBeCreatedByAdmin: ruleData.adminOrder,
>>>>>>> 60e330ba69 (test: add CreateRule task)
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
