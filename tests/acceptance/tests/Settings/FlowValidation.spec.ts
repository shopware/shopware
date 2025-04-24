import { test } from '@fixtures/AcceptanceTest';
import {getCountryId, getSalutationId, RuleConditions, SimpleLineItem} from '@shopware-ag/acceptance-test-suite';

test('As an admin user, I want that certain actions get executed based on the flow, so that I can automate the processes.', { tag: '@Flow' }, async ({
    IdProvider,
    AdminApiContext,
    ShopAdmin,
    TestDataService,
    AdminOrderDetail,
    AdminCustomerDetail,

}) => {
// Id
    const {uuid: flowId, id: uniqueId} = IdProvider.getIdPair();
    const ruleId = IdProvider.getIdPair().uuid;
    const conditionId = IdProvider.getIdPair().uuid;
    const ruleName = 'Test-Rule' + ' - ' + uniqueId;
    const flowName = 'Test-Flow' + ' - ' + uniqueId;
    // Test Data Service
    const tagTrue = await TestDataService.createTag('Santa?');
    const tagFalse = await TestDataService.createTag('Probably Not Santa');
    const product = await TestDataService.createBasicProduct();
    const customerGroup = await TestDataService.createCustomerGroup({ name: 'Christmas Crew' });
    const countryId = await getCountryId('CX', AdminApiContext);
    const salutationId = await getSalutationId('mr', AdminApiContext);
    const advRule = await TestDataService.createBasicRule({}, RuleConditions.DayOfWeek, '=', 2 );
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

    const testFlow = {
        'id': flowId,
        'name': flowName,
        'eventName': 'state_enter.order.state.in_progress',
        'priority': 1,
        'active': true,
        'description': 'We wish you a Merry Christmas, we wish you a Merryyyy Chriiiiistmaaaaaaaas! Andahappynewyear!',
        'sequences': [
            {
                'id': conditionId,
                'flowId': flowId,
                'ruleId': testRule.id,
                'actionName': null,
                'config': [],
                'position': 1,
                'displayGroup': 1,
                'trueCase': false,
                'parentId': null,
            },
            {
                'flowId': flowId,
                'ruleId': null,
                'actionName': 'action.add.order.tag',
                'config': {
                    'entity': 'order',
                    'tagIds': {
                        [tagFalse.id]: tagFalse,
                    },
                },
                'position': 1,
                'displayGroup': 1,
                'trueCase': false,
                'parentId': conditionId,
            },
            {
                'flowId': flowId,
                'ruleId': null,
                'actionName': 'action.add.order.tag',
                'config': {
                    'entity': 'order',
                    'tagIds': {
                        [tagTrue.id]: tagTrue,
                    },
                },
                'position': 1,
                'displayGroup': 1,
                'trueCase': true,
                'parentId': conditionId,
            },
            {
                'flowId': flowId,
                'ruleId': null,
                'actionName': 'action.set.order.state',
                'config': {
                    'order': order.id,
                    'order_transaction': 'paid',
                    'order_delivery': 'shipped_partially',
                    'force_transition': true,
                },
                'position': 1,
                'displayGroup': 2,
                'trueCase': false,
                'parentId': null,
            }],
    }
    const flowResponse = await AdminApiContext.post('flow', {
        data: testFlow,
    });
    ShopAdmin.expects(flowResponse.ok()).toBeTruthy();
    await test.setTimeout(30000)
    console.log('tagTrue ID: ' + tagTrue.id);
    console.log('tagTrue ID: ' + tagFalse.id);
    // Trigger the flow
    await ShopAdmin.goesTo(AdminOrderDetail.url(order.id));
    await ShopAdmin.expects(AdminOrderDetail.page.locator('.sw-order-detail-base__general-info')).toBeVisible();
    await AdminOrderDetail.page.locator('.sw-field').filter({ hasText: 'Order status' }).locator('.sw-select__selection').click();
    await AdminOrderDetail.page.locator('.sw-select-result-list__item-list').waitFor({ state: 'visible' });
    await AdminOrderDetail.page.locator('.sw-select-result-list__content').getByRole('listitem').filter({ hasText: 'In Progress' }).click();
    await AdminOrderDetail.page.getByRole('checkbox', { name: 'Send email to customer' }).click();
    await AdminOrderDetail.page.getByRole('button', { name: 'Update status' }).click();
    // Validate order state
    await ShopAdmin.expects(AdminOrderDetail.page.locator('.mt-button--primary').locator('.mt-button__loader')).toBeVisible()
    await ShopAdmin.expects(AdminOrderDetail.page.locator('.mt-button__loader')).not.toBeVisible()
    await ShopAdmin.expects(AdminOrderDetail.page.locator('.sw-order-state-select-v2__order_transaction')).toContainText('Paid');
    await ShopAdmin.expects(AdminOrderDetail.page.locator('.sw-order-state-select-v2__order_delivery')).toContainText('Shipped (partially)');
    // Validate customer tag
    await ShopAdmin.goesTo(AdminCustomerDetail.url(customer.id));
    await ShopAdmin.expects(AdminCustomerDetail.tagList).toContainText(tagTrue.name);
});
