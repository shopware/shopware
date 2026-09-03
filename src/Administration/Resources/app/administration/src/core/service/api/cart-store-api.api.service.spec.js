import CartStoreService from 'src/core/service/api/cart-store-api.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function createCartStoreServiceService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);
    const cartStoreService = new CartStoreService(client, loginService);
    return { cartStoreService, clientMock };
}

/**
 * @sw-package checkout
 */
describe('cartStoreService', () => {
    it('is registered correctly', async () => {
        const { cartStoreService } = createCartStoreServiceService();

        expect(cartStoreService).toBeInstanceOf(CartStoreService);
    });

    it('function getPayloadForItem should return correct data when adding new product', async () => {
        const itemId = '06d34e8f6e8d4045ad76d39827541e65';
        const saleChannelId = '28abf61c7e3d4011aec0e0a7bcfa4265';
        const isNewProductItem = true;
        const item = {
            quantity: 1,
            type: 'product',
            label: 'Test product',
            description: 'description',
            price: {
                quantity: 1,
                taxRules: [
                    {
                        taxRate: 7,
                    },
                ],
            },
            priceDefinition: {
                taxRules: [
                    {
                        percentage: 100,
                        taxRate: 7,
                    },
                ],
            },
        };
        const { cartStoreService } = createCartStoreServiceService();

        const items = cartStoreService.getPayloadForItem(item, saleChannelId, isNewProductItem, itemId);

        expect(items.items[0].priceDefinition).toBeNull();
        expect(items.items[0].quantity).toBe(1);
        expect(items.items[0].label).toBe('Test product');
    });

    it('function getPayloadForItem should return correct data when adding new product with new tax value', async () => {
        const itemId = '06d34e8f6e8d4045ad76d39827541e65';
        const saleChannelId = '28abf61c7e3d4011aec0e0a7bcfa4265';
        const isNewProductItem = true;
        const item = {
            quantity: 10,
            type: 'product',
            label: 'Test product',
            description: 'description',
            price: {
                quantity: 1,
                taxRules: [
                    {
                        taxRate: 7,
                    },
                ],
            },
            priceDefinition: {
                taxRules: [
                    {
                        percentage: 100,
                        taxRate: 15,
                    },
                ],
            },
        };
        const { cartStoreService } = createCartStoreServiceService();

        const items = cartStoreService.getPayloadForItem(item, saleChannelId, isNewProductItem, itemId);

        expect(items.items[0].priceDefinition.taxRules[0].taxRate).toBe(15);
        expect(items.items[0].quantity).toBe(10);
        expect(items.items[0].label).toBe('Test product');
    });

    it('function getPayloadForItem should return correct data when adjusting price of existing product', async () => {
        const itemId = '06d34e8f6e8d4045ad76d39827541e65';
        const saleChannelId = '28abf61c7e3d4011aec0e0a7bcfa4265';
        const isNewProductItem = false;
        const item = {
            quantity: 1,
            type: 'product',
            label: 'Test product',
            description: 'description',
            price: {
                quantity: 1,
                taxRules: [
                    {
                        taxRate: 7,
                    },
                ],
                unitPrice: 100,
                totalPrice: 100,
            },
            priceDefinition: {
                price: 150,
                taxRules: [
                    {
                        percentage: 100,
                        taxRate: 7,
                    },
                ],
            },
        };
        const { cartStoreService } = createCartStoreServiceService();

        const items = cartStoreService.getPayloadForItem(item, saleChannelId, isNewProductItem, itemId);

        expect(items.items[0].priceDefinition.price).toBe(150);
        expect(items.items[0].quantity).toBe(1);
        expect(items.items[0].label).toBe('Test product');
    });

    it('function getPayloadForItem should return correct data when adding a custom product', async () => {
        const itemId = '06d34e8f6e8d4045ad76d39827541e65';
        const saleChannelId = '28abf61c7e3d4011aec0e0a7bcfa4265';
        const isNewProductItem = false;
        const item = {
            quantity: 5,
            type: 'custom',
            label: 'Test custom product',
            description: 'custom line item',
            price: {
                quantity: 1,
                taxRules: [
                    {
                        taxRate: 0,
                    },
                ],
                unitPrice: 0,
                totalPrice: 0,
            },
            priceDefinition: {
                price: 200,
                taxRules: [
                    {
                        percentage: 100,
                        taxRate: 5,
                    },
                ],
            },
        };
        const { cartStoreService } = createCartStoreServiceService();

        const items = cartStoreService.getPayloadForItem(item, saleChannelId, isNewProductItem, itemId);

        expect(items.items[0].priceDefinition.price).toBe(200);
        expect(items.items[0].quantity).toBe(5);
        expect(items.items[0].label).toBe('Test custom product');
    });

    it('function getPayloadForItem should return correct data when adjusting a custom product', async () => {
        const itemId = '06d34e8f6e8d4045ad76d39827541e65';
        const saleChannelId = '28abf61c7e3d4011aec0e0a7bcfa4265';
        const isNewProductItem = false;
        const item = {
            quantity: 15,
            type: 'custom',
            label: 'Test custom product',
            description: 'custom line item',
            price: {
                quantity: 10,
                taxRules: [
                    {
                        apiAlias: 'cart_tax_rule',
                        percentage: 100,
                        taxRate: 5,
                    },
                ],
                unitPrice: 50,
                totalPrice: 500,
            },
            priceDefinition: {
                price: 100,
                taxRules: [
                    {
                        apiAlias: 'cart_tax_rule',
                        percentage: 100,
                        taxRate: 10,
                    },
                ],
            },
        };
        const { cartStoreService } = createCartStoreServiceService();

        const items = cartStoreService.getPayloadForItem(item, saleChannelId, isNewProductItem, itemId);

        expect(items.items[0].priceDefinition.price).toBe(100);
        expect(items.items[0].quantity).toBe(15);
        expect(items.items[0].label).toBe('Test custom product');
    });

    it('function getPayloadForItem should keep the referenced id of an existing line item', async () => {
        const itemId = '06d34e8f6e8d4045ad76d39827541e65-2b30cbc7b0464b4fa8c05a2e9bd2eba1';
        const referencedId = '2b30cbc7b0464b4fa8c05a2e9bd2eba1';
        const saleChannelId = '28abf61c7e3d4011aec0e0a7bcfa4265';
        const item = {
            quantity: 2,
            type: 'product',
            label: 'Test product',
            referencedId,
            price: {
                quantity: 2,
                taxRules: [{ taxRate: 19 }],
            },
            priceDefinition: {
                taxRules: [{ percentage: 100, taxRate: 19 }],
            },
        };
        const { cartStoreService } = createCartStoreServiceService();

        const items = cartStoreService.getPayloadForItem(item, saleChannelId, false, itemId);

        expect(items.items[0].id).toBe(itemId);
        expect(items.items[0].referencedId).toBe(referencedId);
    });

    it('function getPayloadForItem should fall back to the line item id when there is no referenced id', async () => {
        const itemId = '06d34e8f6e8d4045ad76d39827541e65';
        const saleChannelId = '28abf61c7e3d4011aec0e0a7bcfa4265';
        const item = {
            quantity: 1,
            type: 'product',
            label: 'Test product',
            price: {
                quantity: 1,
                taxRules: [{ taxRate: 19 }],
            },
            priceDefinition: {
                taxRules: [{ percentage: 100, taxRate: 19 }],
            },
        };
        const { cartStoreService } = createCartStoreServiceService();

        const items = cartStoreService.getPayloadForItem(item, saleChannelId, true, itemId);

        expect(items.items[0].referencedId).toBe(itemId);
    });

    it('function addMultipleLineItems should keep the referenced id of each line item', async () => {
        const saleChannelId = '28abf61c7e3d4011aec0e0a7bcfa4265';
        const referencedId = '2b30cbc7b0464b4fa8c05a2e9bd2eba1';
        const { cartStoreService, clientMock } = createCartStoreServiceService();

        let payload = null;
        clientMock.onPost(`_proxy/store-api/${saleChannelId}/checkout/cart/line-item`).reply((config) => {
            payload = JSON.parse(config.data);

            return [
                200,
                {},
            ];
        });

        await cartStoreService.addMultipleLineItems(saleChannelId, 'context-token', [
            {
                id: `wrapper-${referencedId}`,
                referencedId,
                type: 'product',
                label: 'Test product',
                quantity: 1,
            },
        ]);

        expect(payload.items[0].id).toBe(`wrapper-${referencedId}`);
        expect(payload.items[0].referencedId).toBe(referencedId);
    });
});
