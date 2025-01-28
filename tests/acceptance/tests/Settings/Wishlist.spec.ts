import { test } from '@fixtures/AcceptanceTest';

test(
    'Customer is able to add and remove products to the wishlist',
    { tag: '@Wishlist' },
    async ({ TestDataService, ShopCustomer, StorefrontHome, StoreApiContext }) => {
        await TestDataService.setSystemConfig({ 'core.cart.wishlistEnabled': true });

        const product1 = await TestDataService.createBasicProduct();
        const product2 = await TestDataService.createBasicProduct();

        await ShopCustomer.goesTo(StorefrontHome.url());
        const product11 = await StorefrontHome.getListingItemByProductId(product1.id);
        await product11.addToWishlistButton.waitFor({ state: 'visible' });
        await product11.addToWishlistButton.click();
        await ShopCustomer.expects(StoreApiContext.post('/wishlist/add/${product1.id}')).toBeTruthy();
        //ShopCustomer.expects(addProduct1ToWishlist.ok()).toBeTruthy();
        //expect(addProduct1ToWishlist.ok()).toBeTruthy();

        //console.log(addProduct1ToWishlist);
        console.log(product2.id);

        const product22 = await StorefrontHome.getListingItemByProductId(product2.id);
        await product22.addToWishlistButton.click();
        await ShopCustomer.expects(StoreApiContext.post('/wishlist/add/${product2.id}')).toBeTruthy();

        //console.log(addProduct1ToWishlist);
    }
);
