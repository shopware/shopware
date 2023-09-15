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
    await storefrontPage.pause();
});
