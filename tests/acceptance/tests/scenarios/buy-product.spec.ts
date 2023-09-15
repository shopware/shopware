import { test, expect } from "../../fixtures/acceptance-test";

test.only(`Buy product from storefront`, async ({
    salesChannelProduct,
    storefrontPage,
}) => {
    //add a product to cart
    await storefrontPage
        .getByRole("button", { name: "Add to shopping cart" })
        .click();

    await expect(storefrontPage.getByText("1 item")).toBeVisible();
    await expect(
        storefrontPage.getByRole("dialog").getByText(salesChannelProduct.name)
    ).toBeVisible();

    await storefrontPage.getByRole("link", { name: "Go to checkout" }).click();

    await storefrontPage
        .getByLabel(
            "I have read and accepted the general terms and conditions."
        )
        .check();

    await expect(
        storefrontPage.getByLabel(
            "I have read and accepted the general terms and conditions."
        )
    ).toBeChecked();
    await expect(
        storefrontPage.getByText(salesChannelProduct.name)
    ).toBeVisible();

    // todo: check correct field for price
    await expect(storefrontPage.getByText("€10.00*").nth(2)).toBeVisible();

    await storefrontPage.getByRole("button", { name: "Submit order" }).click();

    await expect(
        storefrontPage.getByText(
            `Product number: ${salesChannelProduct.productNumber}`
        )
    ).toBeVisible();
    await expect(
        storefrontPage.getByRole("heading", {
            name: "Thank you for your order with Demostore!",
        })
    ).toBeVisible();

    await storefrontPage.pause();
    // WIP: Order verification in admin

    /* Problematic areas:
    - Search in admin with grids, we need a helper like in cypress
    - Alternative: order number in storefornt can be used in admin/order listing
    - Fixed: Missing salutation in storefront & Caching of theme
    - ToDo: Deletion of order in admin after tests
    - 
    */

    // await adminPage.goto(`#/admin#/sw/order/index`);

    // await adminPage.getByPlaceholder("Search all orders...").click();
    // await expect(adminPage.locator(".sw-search-bar__footer")).toBeVisible();
    // await adminPage
    //     .getByPlaceholder("Search all orders...")
    //     .fill(defaultStorefront.customer.email);
    // await expect(adminPage.locator(".sw-search-bar__footer")).toBeHidden();
    // await adminPage.getByRole("link", { name: "10001" }).click();
    // await adminPage
    //     .getByText("10001 - 0 admin 0 admin (customer_0@example.com)")
    //     .click();
    // await adminPage
    //     .getByRole("link", { name: salesChannelProduct.name })
    //     .click();
    // await adminPage.locator(".smart-bar__back-btn").click();
    // await adminPage.getByRole("link", { name: "Overview" }).click();
    // await adminPage.getByRole("link", { name: "10001" }).click();
    // await adminPage.getByRole("strong").nth(3).click();
});
