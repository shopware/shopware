import { test, expect } from '@fixtures/AcceptanceTest';
import { getCountryId, getSalutationId, RuleConditions, SimpleLineItem } from '@shopware-ag/acceptance-test-suite';

test(
    'As an admin user, I want that certain actions get executed based on the flow, so that I can automate the processes.',
    { tag: '@Flow' },
    async ({ IdProvider, AdminApiContext, ShopAdmin, TestDataService, AdminOrderDetail, AdminCustomerDetail }) => {
        // Id
        const { uuid: flowId, id: uniqueId } = IdProvider.getIdPair();
        const ruleId = IdProvider.getIdPair().uuid;
        const conditionId = IdProvider.getIdPair().uuid;
        const sequenceId1 = IdProvider.getIdPair().uuid;
        const sequenceId2 = IdProvider.getIdPair().uuid;
        const sequenceId3 = IdProvider.getIdPair().uuid;
        const sequenceId4 = IdProvider.getIdPair().uuid;
        const ruleName = 'Test-Rule' + ' - ' + uniqueId;
        const flowName = 'Test-Flow' + ' - ' + uniqueId;
        // Test Data Service
        const tagTrue = await TestDataService.createTag('Santa?');
        const tagFalse = await TestDataService.createTag('Probably Not Santa');
        const product = await TestDataService.createBasicProduct();
        const countryId = await getCountryId('CX', AdminApiContext);
        const salutationId = await getSalutationId('mr', AdminApiContext);
        const customerOverrides = {
            defaultBillingAddress: {
                firstName: 'Santa',
                lastName: 'Claus',
                city: 'Flying Fish Cove, Silver City',
                street: 'Seaview Drive 1',
                zipcode: '6798',
                countryId: countryId,
                salutationId: salutationId,
            },
        };
        const customer = await TestDataService.createCustomer(customerOverrides);
        const lineItems: SimpleLineItem[] = [{ product, quantity: 1 }];
        const testRule = {
            id: ruleId,
            name: ruleName,
            priority: 1,
            description: 'The testiest rule there is.',
            conditions: [
                {
                    type: 'orContainer',
                    children: [
                        {
                            type: 'andContainer',
                            children: [
                                {
                                    type: 'customerBillingCountry',
                                    value: {
                                        operator: '=',
                                        countryIds: [countryId],
                                    },
                                },
                            ],
                        },
                    ],
                },
            ],
        };
        const ruleResponse = await AdminApiContext.post('rule', {
            data: testRule,
        });
        ShopAdmin.expects(ruleResponse.ok()).toBeTruthy();

        const order = await TestDataService.createOrder(lineItems, customer, {});
        TestDataService.addCreatedRecord('order', order.id);

        const testFlow = {
            id: flowId,
            name: flowName,
            eventName: 'state_enter.order.state.in_progress',
            priority: 1,
            active: true,
            description:
                'We wish you a Merry Christmas, we wish you a Merryyyy Chriiiiistmaaaaaaaas! Andahappynewyear!',
            sequences: [
                {
                    id: conditionId,
                    flowId: flowId,
                    ruleId: testRule.id,
                    actionName: null,
                    config: [],
                    position: 1,
                    displayGroup: 1,
                    trueCase: false,
                    parentId: null,
                },
                {
                    id: sequenceId2,
                    flowId: flowId,
                    ruleId: null,
                    actionName: 'action.add.customer.tag',
                    config: {
                        entity: 'customer',
                        tagIds: {
                            [tagFalse.id]: tagFalse,
                        },
                    },
                    position: 1,
                    displayGroup: 1,
                    trueCase: false,
                    parentId: conditionId,
                },
                {
                    id: sequenceId3,
                    flowId: flowId,
                    ruleId: null,
                    actionName: 'action.add.customer.tag',
                    config: {
                        entity: 'customer',
                        tagIds: {
                            [tagTrue.id]: tagTrue,
                        },
                    },
                    position: 1,
                    displayGroup: 1,
                    trueCase: true,
                    parentId: conditionId,
                },
                {
                    id: sequenceId4,
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

        // Trigger the flow
        await test.step('Set the order status to "in progress".', async () => {
            const orderState = await AdminApiContext.post(`./_action/order/${order.id}/state/process`);
            expect(orderState.ok()).toBeTruthy();
        });

        // Validate order state
        await ShopAdmin.goesTo(AdminOrderDetail.url(order.id));
        await ShopAdmin.expects(
            AdminOrderDetail.page.locator('.sw-order-state-select-v2__order_transaction')
        ).toContainText('Paid');
        await ShopAdmin.expects(
            AdminOrderDetail.page.locator('.sw-order-state-select-v2__order_delivery')
        ).toContainText('Shipped (partially)');
        // Validate customer tag
        await ShopAdmin.goesTo(AdminCustomerDetail.url(customer.id));
        await ShopAdmin.expects(AdminCustomerDetail.tagList).toContainText(tagTrue.name);

        // Data cleanup
        TestDataService.addCreatedRecord('flow_sequence', sequenceId1);
        TestDataService.addCreatedRecord('flow_sequence', sequenceId2);
        TestDataService.addCreatedRecord('flow_sequence', sequenceId3);
        TestDataService.addCreatedRecord('flow_sequence', sequenceId4);
        TestDataService.addCreatedRecord('flow', flowId);
    }
);
