import { test, expect } from '@fixtures/AcceptanceTest';
import { getCountryId, getSalutationId, setOrderStatus } from '@shopware-ag/acceptance-test-suite';

test(
    'As an admin user, I want that certain actions get executed based on the flow, so that I can automate the processes.',
    { tag: '@Flow' },
    async ({
        IdProvider,
        AdminApiContext,
        ShopAdmin,
        TestDataService,
        AdminOrderDetail,
        AdminCustomerDetail,
        CreateRuleBillingCountry,
        CreateFlowForValidation,
        WaitForFlowValidationSideEffects,
    }) => {
        // Test data setup
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

        const order = await TestDataService.createOrder([{ product, quantity: 1 }], customer);
        TestDataService.addCreatedRecord('order', order.id);
        const ruleConfig = { ruleId: IdProvider.getIdPair().uuid, countryId: countryId };
        await ShopAdmin.attemptsTo(CreateRuleBillingCountry(ruleConfig));

        const flowConfig = { flowId: IdProvider.getIdPair().uuid, ruleId: ruleConfig.ruleId, tagTrue, tagFalse };
        await ShopAdmin.attemptsTo(CreateFlowForValidation(flowConfig));
        // Ensure flow-loader cache sees the newly created flow before we trigger it.
        await TestDataService.clearCaches();

        await test.step('Set the order status to "in progress" to trigger the flow.', async () => {
            const orderState = await setOrderStatus(order.id, 'process', AdminApiContext);
            expect(orderState.ok()).toBeTruthy();
        });

        await test.step('Wait until flow side-effects are persisted via Admin API.', async () => {
            await ShopAdmin.attemptsTo(WaitForFlowValidationSideEffects({ orderId: order.id }));
        });

        await test.step('Validate order state and customer tag via UI', async () => {
            await ShopAdmin.goesTo(AdminOrderDetail.url(order.id));
            await ShopAdmin.expects(AdminOrderDetail.orderStatus).toContainText('In Progress');
            await ShopAdmin.expects(AdminOrderDetail.orderPaymentStatus).toContainText('Paid');
            await ShopAdmin.expects(AdminOrderDetail.orderDeliveryStatus).toContainText('Shipped (partially)');

            await ShopAdmin.goesTo(AdminCustomerDetail.url(customer.id));
            await ShopAdmin.expects(AdminCustomerDetail.tagList).toContainText(tagTrue.name);
        });
    },
);
