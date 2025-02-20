import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

export const CreateRule = base.extend<{ CreateRule: Task }, FixtureTypes>({
    CreateRule: async ({ ShopAdmin, AdminApiContext }, use ) => {

<<<<<<< HEAD
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
=======
        const task = (testConfig    ) => {
>>>>>>> 8bf92384f1 (test: code style fixes)
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
<<<<<<< HEAD
                            name: ruleData.ruleTag,
>>>>>>> 60e330ba69 (test: add CreateRule task)
=======
                            name: testConfig.ruleTag,
>>>>>>> 8bf92384f1 (test: code style fixes)
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
<<<<<<< HEAD
                                                count: testConfig.quantity,
=======
                                                count: ruleData.quantity,
>>>>>>> 60e330ba69 (test: add CreateRule task)
=======
                                                count: testConfig.quantity,
>>>>>>> 8bf92384f1 (test: code style fixes)
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
<<<<<<< HEAD
                                                                        stock: testConfig.stock,
=======
                                                                        stock: ruleData.inStock,
>>>>>>> 60e330ba69 (test: add CreateRule task)
=======
                                                                        stock: testConfig.stock,
>>>>>>> 8bf92384f1 (test: code style fixes)
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
<<<<<<< HEAD
                                                toDate: testConfig.toDate,
                                                useTime: false,
                                                fromDate: testConfig.fromDate,
=======
                                                toDate: ruleData.toDate,
                                                useTime: false,
                                                fromDate: ruleData.fromDate,
>>>>>>> 60e330ba69 (test: add CreateRule task)
=======
                                                toDate: testConfig.toDate,
                                                useTime: false,
                                                fromDate: testConfig.fromDate,
>>>>>>> 8bf92384f1 (test: code style fixes)
                                            },
                                        },
                                        {
                                            type: 'orContainer',
                                            children: [
                                                {
                                                    type: 'customerLastName',
                                                    value: {
<<<<<<< HEAD
<<<<<<< HEAD
                                                        lastName: testConfig.customerSurname,
=======
                                                        lastName: ruleData.customerSurname,
>>>>>>> 60e330ba69 (test: add CreateRule task)
=======
                                                        lastName: testConfig.customerSurname,
>>>>>>> 8bf92384f1 (test: code style fixes)
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
<<<<<<< HEAD
                                                    testConfig.taxId,
=======
                                                    ruleData.taxId,
>>>>>>> 60e330ba69 (test: add CreateRule task)
=======
                                                    testConfig.taxId,
>>>>>>> 8bf92384f1 (test: code style fixes)
                                                ],
                                                operator: '=',
                                            },
                                        },
                                        {
                                            type: 'timeRange',
                                            value: {
<<<<<<< HEAD
<<<<<<< HEAD
                                                toTime: testConfig.toDate.split('T')[1].substring(0, 5),
                                                fromTime: testConfig.fromDate.split('T')[1].substring(0, 5),
=======
                                                toTime: ruleData.toDate.split('T')[1].substring(0, 5),
                                                fromTime: ruleData.fromDate.split('T')[1].substring(0, 5),
>>>>>>> 60e330ba69 (test: add CreateRule task)
=======
                                                toTime: testConfig.toDate.split('T')[1].substring(0, 5),
                                                fromTime: testConfig.fromDate.split('T')[1].substring(0, 5),
>>>>>>> 8bf92384f1 (test: code style fixes)
                                            },
                                        },
                                        {
                                            type: 'orContainer',
                                            children: [
                                                {
                                                    type: 'orderCreatedByAdmin',
                                                    value: {
<<<<<<< HEAD
<<<<<<< HEAD
                                                        shouldOrderBeCreatedByAdmin: testConfig.isAdminOrder,
=======
                                                        shouldOrderBeCreatedByAdmin: ruleData.adminOrder,
>>>>>>> 60e330ba69 (test: add CreateRule task)
=======
                                                        shouldOrderBeCreatedByAdmin: testConfig.isAdminOrder,
>>>>>>> 8bf92384f1 (test: code style fixes)
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
