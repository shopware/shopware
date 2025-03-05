import { test } from '@fixtures/AcceptanceTest';
import { SimpleLineItem } from '@shopware-ag/acceptance-test-suite';

test('As an admin user, I want that certain actions get executed based on the flow, so that I can automate the processes.', { tag: '@Flow' }, async ({
    IdProvider,
    AdminApiContext,
    ShopAdmin,
    ShopCustomer,
    TestDataService,
    AdminOrderDetail,
    StorefrontAccountRecover,
    AdminCustomerDetail,
    Login,

}) => {
    // Id
    const {uuid: flowId, id: uniqueId} = IdProvider.getIdPair();
    const ruleId = IdProvider.getIdPair().uuid;
    const parentId = IdProvider.getIdPair().uuid;
    const ruleName = 'Test-Rule' + ' - ' + uniqueId;
    const flowName = 'Test-Flow' + ' - ' + uniqueId;
    // Test Data Service
    const tagTrue = await TestDataService.createTag('Santa?');
    const tagTrueId = tagTrue.id.toString();
    const tagFalse = await TestDataService.createTag('Probably Not Santa');
    const tagFalseId = tagFalse.id.toString();
    const product = await TestDataService.createBasicProduct();
    const customerOverrides = {
        defaultBillingAddress: {
            firstName: 'Santa',
            lastName: 'Claus',
            city: 'Flying Fish Cove, Silver City',
            street: 'Seaview Drive 1',
            zipcode: '6798',
            countryId: '0195619098117080a729c75cf7809d50',
            salutationId: '019561908fae712f8f406bd031d51cd6',
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
                                    countryIds: ['0195619098117080a729c75cf7809d50'],
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

    const testFlow = {
        'id': flowId,
        'name': flowName,
        'eventName': 'customer.recovery.request',
        'priority': 1,
        'active': true,
        'description': 'The testiest flow there is.',
        'sequences': [
        {
            'id': parentId,
            'flowId': flowId,
            'ruleId': '01956190907170bebbdcf7afd06a2776',
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
                    tagFalseId: tagFalse,
                },
            },
            'position': 1,
            'displayGroup': 1,
            'trueCase': false,
            'parentId': parentId,
        },
        {
            'flowId': flowId,
            'ruleId': null,
            'actionName': 'action.add.order.tag',
            'config': {
                'entity': 'order',
                'tagIds': {
                    tagTrueId: tagTrue,
                },
            },
            'position': 1,
            'displayGroup': 1,
            'trueCase': true,
            'parentId': parentId,
        },
        {
            'flowId': flowId,
            'ruleId': null,
            'actionName': 'action.set.order.state',
            'config': {
                'order': 'completed',
                'order_delivery': 'shipped',
                'force_transition': true,
                'order_transaction': 'paid',
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

    // Trigger
    // const order = await TestDataService.createOrder(lineItems, customer, {});

    await ShopCustomer.attemptsTo(Login({password: customer.password, email: customer.email}));
    // await ShopCustomer.goesTo(StorefrontAccountRecover.url());
    // await StorefrontAccountRecover.emailInput.fill(customer.email);
    // await StorefrontAccountRecover.requestEmailButton.click();

    // Validate
    await ShopAdmin.goesTo(AdminCustomerDetail.url(customer.id));
    await ShopAdmin.expects(AdminCustomerDetail.tagList).toContainText(tagTrue.name);

    // await ShopAdmin.expects(AdminOrderDetail.page.getByRole('textbox', {name: 'Order status'})).toHaveText('Done');
    // await ShopAdmin.expects(AdminOrderDetail.page.getByRole('textbox', {name: 'Delivery status'})).toHaveText('Shipped');
    // await ShopAdmin.expects(AdminOrderDetail.page.getByRole('textbox', {name: 'Payment status'})).toHaveText('Paid');
    // await ShopAdmin.expects(AdminOrderDetail.page.getByRole('combobox', {name: 'Add tags'})).toHaveText(tagTrue.name);
});
