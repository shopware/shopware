import { test, expect } from '@fixtures/AcceptanceTest';

test(
    'Shop administrator should be able to duplicate a customer address.',
    { tag: '@Customer' },
    async ({
        ShopAdmin,
        IdProvider,
        TestDataService,
        AdminApiContext,
        AdminCustomerDetail,
        DefaultSalesChannel,
        DuplicateCustomerAddress,
    }) => {
        const street = `Clone street ${IdProvider.getIdPair().uuid}`;
        const address = {
            firstName: 'Clone',
            lastName: 'Address',
            city: 'Berlin',
            street,
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

        await test.step('Duplicate the address from the customer address list.', async () => {
            await ShopAdmin.goesTo(AdminCustomerDetail.addressesUrl(customer.id));
            await ShopAdmin.expects(AdminCustomerDetail.addressRows(street).first()).toBeVisible();
            const addressCount = await AdminCustomerDetail.addressRows(street).count();

            await ShopAdmin.attemptsTo(DuplicateCustomerAddress(street));

            await ShopAdmin.expects(AdminCustomerDetail.addressRows(street)).toHaveCount(addressCount + 1);
        });

        await test.step('Verify the copy keeps the street and the default addresses stay unchanged.', async () => {
            const addressResponse = await AdminApiContext.post('search/customer-address', {
                data: {
                    filter: [
                        { type: 'equals', field: 'customerId', value: customer.id },
                        { type: 'equals', field: 'street', value: street },
                    ],
                },
            });
            expect(addressResponse.ok()).toBeTruthy();
            const addresses = (await addressResponse.json()).data;
            const originalIds = new Set([customer.defaultBillingAddressId, customer.defaultShippingAddressId]);
            const clone = addresses.find(({ id }) => !originalIds.has(id));

            const customerResponse = await AdminApiContext.get(`customer/${customer.id}`);
            expect(customerResponse.ok()).toBeTruthy();
            const { data: storedCustomer } = await customerResponse.json();

            expect(clone.customerId).toBe(customer.id);
            expect(clone.street).toBe(street);
            expect(clone.city).toBe(address.city);
            expect(storedCustomer.defaultBillingAddressId).toBe(customer.defaultBillingAddressId);
            expect(storedCustomer.defaultShippingAddressId).toBe(customer.defaultShippingAddressId);
        });
    },
);
