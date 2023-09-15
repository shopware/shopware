import { test, expect } from "../../fixtures/acceptance-test";

test.only(`Buy product from storefront`, async ({
    salesChannelProduct,
    storefrontPage,
    adminPage,
}) => {
    //add a product to cart
    await storefrontPage
        .getByRole("button", { name: "Add to shopping cart" })
        .click();

    await expect(storefrontPage.getByText('1 item')).toBeVisible();
    await expect(storefrontPage.getByRole('dialog').getByText(salesChannelProduct.name)).toBeVisible();


    await storefrontPage.getByRole('link', { name: 'Go to checkout' }).click();

    await storefrontPage.getByLabel('I have read and accepted the general terms and conditions.').check();

    await expect(storefrontPage.getByLabel('I have read and accepted the general terms and conditions.')).toBeChecked();
    await expect(storefrontPage.getByText(salesChannelProduct.name)).toBeVisible();

    // todo: check correct field for price
    await expect(storefrontPage.getByText('€10.00*').nth(2)).toBeVisible();

    await storefrontPage.getByRole('button', { name: 'Submit order' }).click();
    
    await expect(storefrontPage.getByText(`Product number: ${salesChannelProduct.productNumber}`)).toBeVisible();
    await expect(storefrontPage.getByRole('heading', { name: 'Thank you for your order with Demostore!' })).toBeVisible();
    
    await storefrontPage.pause();
});
