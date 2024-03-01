import { test as base } from 'playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import { expect } from '@fixtures/AcceptanceTest';
import { components } from '@shopware/api-client/admin-api-types';
import { getCurrencyFactor, getSalutationId, getStateMachineId, getStateMachineStateId } from '@fixtures/Helper';

export const OrderData = base.extend<FixtureTypes>({
    orderData: async ({ idProvider, adminApiContext, storeBaseConfig, defaultStorefront, productData }, use) => {

        //Create Requests
        const requests = {
            currencyFactorEUR: getCurrencyFactor('EUR', adminApiContext),
            mrSalutationId: getSalutationId('mr', adminApiContext),
            orderStateId: getStateMachineId('order.state', adminApiContext),
            orderTransactionStateId: getStateMachineId('order_transaction.state', adminApiContext),
            orderDeliveryStateId: getStateMachineId('order_delivery.state', adminApiContext),
        };
        await Promise.all(Object.values(requests));

        // Generate unique IDs
        const { id: orderId } = idProvider.getIdPair();
        const addressId = idProvider.getIdPair().uuid;
        const mrSalutationId = await requests.mrSalutationId;
        const orderStateId = await requests.orderStateId;
        const currencyFactorEUR = await requests.currencyFactorEUR;
        const orderTransactionStateId = await requests.orderTransactionStateId;
        const orderDeliveryStateId = await requests.orderDeliveryStateId;
        const orderStateStateMachineStateId = await getStateMachineStateId(orderStateId, adminApiContext);
        const orderTransactionStateIdMachineStateId = await getStateMachineStateId(orderTransactionStateId, adminApiContext);
        const orderDeliveryStateIdMachineStateId = await getStateMachineStateId(orderDeliveryStateId, adminApiContext);

        // Create order
        const orderResponse = await adminApiContext.post('./order?_response=detail', {
            data: {
                billingAddressId: addressId,
                currencyId: storeBaseConfig.eurCurrencyId,
                languageId: storeBaseConfig.enGBLanguageId,
                salesChannelId: defaultStorefront.salesChannel.id,
                stateId: orderStateStateMachineStateId,
                orderDateTime: '2024-02-01 07:00:00',
                orderNumber: orderId,
                currencyFactor: currencyFactorEUR,
                itemRounding: {
                    decimals: 2,
                    interval: 0.01,
                    roundForNet: true,
                },
                totalRounding: {
                    decimals: 2,
                    interval: 0.01,
                    roundForNet: true,
                },
                orderCustomer: {
                    customerId: `${defaultStorefront.customer.id}`,
                    email: `${defaultStorefront.customer.email}`,
                    firstName: `${defaultStorefront.customer.firstName}`,
                    lastName: `${defaultStorefront.customer.lastName}`,
                    salutationId: `${defaultStorefront.customer.salutationId}`,
                },
                addresses: [
                    {
                        id: addressId,
                        salutationId: `${defaultStorefront.customer.salutationId}`,
                        firstName: `${defaultStorefront.customer.firstName}`,
                        lastName: `${defaultStorefront.customer.lastName}`,
                        street: `${orderId} street`,
                        zipcode: `${orderId} zipcode`,
                        city: `${orderId} city`,
                        countryId: storeBaseConfig.deCountryId,
                        company: `${orderId} company`,
                        vatId: null,
                        phoneNumber: `${orderId}`,
                    },
                ],
                price: {
                    totalPrice: 13.98,
                    positionPrice: 13.98,
                    rawTotal: 13.98,
                    netPrice: 13.98,
                    taxStatus: 'gross',
                    calculatedTaxes: [
                        {
                            tax: 0,
                            taxRate: 0,
                            price: 13.98,
                        },
                    ],
                    taxRules: [
                        {
                            taxRate: 0,
                            percentage: 100,
                        },
                    ],
                },
                shippingCosts: {
                    unitPrice: 2.99,
                    totalPrice: 2.99,
                    quantity: 1,
                    calculatedTaxes: [
                        {
                            tax: 0,
                            taxRate: 0,
                            price: 2.99,
                        },
                    ],
                    taxRules: [
                        {
                            taxRate: 0,
                            percentage: 100,
                        },
                    ],
                },
                lineItems: [
                    {
                        productId: productData.id,
                        referencedId: productData.id,
                        payload: {
                            productNumber: productData.productNumber,
                        },
                        identifier: productData.id,
                        type: 'product',
                        label: 'Shopware Blue T-shirt',
                        quantity: 1,
                        position: 1,
                        price: {
                            unitPrice: 10.99,
                            totalPrice: 10.99,
                            quantity: 1,
                            calculatedTaxes: [
                                {
                                    tax: 0,
                                    taxRate: 0,
                                    price: 10.99,
                                },
                            ],
                            taxRules: [
                                {
                                    taxRate: 0,
                                    percentage: 100,
                                },
                            ],
                        },
                        priceDefinition: {
                            type: 'quantity',
                            price: 10.99,
                            quantity: 1,
                            taxRules: [
                                {
                                    taxRate: 0,
                                    percentage: 100,
                                },
                            ],
                            listPrice: 8.00,
                            isCalculated: true,
                            referencePriceDefinition: null,
                        },
                    },
                ],
                deliveries: [
                    {
                        stateId: orderDeliveryStateIdMachineStateId,
                        shippingMethodId: storeBaseConfig.defaultShippingMethod,
                        shippingOrderAddress: {
                            id: idProvider.getIdPair().uuid,
                            salutationId: mrSalutationId,
                            firstName: 'John',
                            lastName: 'Doe',
                            street: 'Shortstreet 5',
                            zipcode: '12345',
                            city: 'Doe City',
                            countryId: storeBaseConfig.deCountryId,
                            phoneNumber: '123 456 789',
                        },
                        shippingDateEarliest: '2024-03-01 07:00:00',
                        shippingDateLatest: '2024-03-03 07:00:00',
                        shippingCosts: {
                            unitPrice: 8.99,
                            totalPrice: 8.99,
                            quantity: 1,
                            calculatedTaxes: [
                                {
                                    tax: 0,
                                    taxRate: 0,
                                    price: 8.99,
                                },
                            ],
                            taxRules: [
                                {
                                    taxRate: 0,
                                    percentage: 100,
                                },
                            ],
                        },
                    },
                ],
                transactions: [
                    {
                        paymentMethodId: storeBaseConfig.invoicePaymentMethodId,
                        amount: {
                            unitPrice: 13.98,
                            totalPrice: 13.98,
                            quantity: 1,
                            calculatedTaxes: [
                                {
                                    tax: 0,
                                    taxRate: 0,
                                    price: 0,
                                },
                            ],
                            taxRules: [
                                {
                                    taxRate: 0,
                                    percentage: 100,
                                },
                            ],
                        },
                        stateId: orderTransactionStateIdMachineStateId,
                    },
                ],
            },
        });

        await expect(orderResponse.ok()).toBeTruthy();

        const { data: order } = (await orderResponse.json()) as { data: components['schemas']['Order'] };

        // Use order data in the test
        await use(order);

        // Delete order after the test is done
        const cleanupResponse = await adminApiContext.delete(`./order/${order.id}`);
        await expect(cleanupResponse.ok()).toBeTruthy();
    },
});
