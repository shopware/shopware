import { test, expect, waitForOrderAddressUpdate, waitForOrderVersion } from '@fixtures/AcceptanceTest';

test(
    'Shop administrator should change an order shipping address on the order version.',
    { tag: '@Order' },
    async ({
        ShopAdmin,
        IdProvider,
        TestDataService,
        AdminOrderDetail,
        DefaultSalesChannel,
        ChangeOrderShippingAddress,
    }) => {
        const billingStreet = `Billing street ${IdProvider.getIdPair().uuid}`;
        const shippingStreet = `Shipping street ${IdProvider.getIdPair().uuid}`;
        const address = {
            firstName: 'Order',
            lastName: 'Address',
            city: 'Berlin',
            street: billingStreet,
            zipcode: '10115',
            countryId: DefaultSalesChannel.salesChannel.countryId,
            salutationId: DefaultSalesChannel.salesChannel.salutationId,
        };
        const customer = await TestDataService.createCustomer({
            firstName: address.firstName,
            lastName: address.lastName,
            defaultBillingAddress: address,
            defaultShippingAddress: { ...address },
        });
        await TestDataService.createCustomerAddress(customer, {
            firstName: address.firstName,
            lastName: address.lastName,
            street: shippingStreet,
            zipcode: address.zipcode,
            city: address.city,
            countryId: address.countryId,
            salutationId: address.salutationId,
        });
        const product = await TestDataService.createBasicProduct();
        const order = await TestDataService.createOrder([{ product, quantity: 1 }], customer);
        let versionId: string;

        await test.step('Open the order so the editor works on a new version.', async () => {
            const versionResponse = waitForOrderVersion(ShopAdmin.page, order.id);
            await ShopAdmin.goesTo(AdminOrderDetail.url(order.id, 'details'));

            const response = await versionResponse;
            expect(response.ok()).toBeTruthy();
            versionId = (await response.json()).versionId;
        });

        await test.step('Select the other customer address as the shipping address and save.', async () => {
            const addressUpdate = waitForOrderAddressUpdate(ShopAdmin.page, order.id);
            await ShopAdmin.attemptsTo(ChangeOrderShippingAddress(order.id, versionId, shippingStreet));

            const updateResponse = await addressUpdate;
            expect(updateResponse.request().headers()['sw-version-id']).toBe(versionId);
        });

        await test.step('Verify the saved order uses the new shipping address and keeps the billing address.', async () => {
            const storedOrder = await TestDataService.getOrderWithAddresses(order.id);
            const [delivery] = storedOrder.deliveries;

            expect(storedOrder.billingAddress.street).toBe(billingStreet);
            expect(delivery.shippingOrderAddress.street).toBe(shippingStreet);
            expect(delivery.shippingOrderAddressId).not.toBe(storedOrder.billingAddressId);
        });
    },
);
