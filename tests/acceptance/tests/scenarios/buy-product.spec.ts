import { test, expect } from "../../fixtures/acceptance-test";

for (let i = 0; i < 48; ++i) {
    test(`Buy product from storefront ${i}`, async ({
        salesChannelProduct,
        storefrontPage,
        defaultStorefront,
        adminPage,
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
        await expect(storefrontPage.locator('dt:has-text("Grand total") + dd')).toHaveText('€10.00*');

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

        await expect(storefrontPage.locator('dt:has-text("Grand total") + dd'))
            .toHaveText('€10.00');
        const orderNumberRegex = /Your order number: #(\d+)/;
        const locator = await storefrontPage.getByText(orderNumberRegex);

        const orderNumberText = await locator.textContent();
        const [_, orderNumber] = orderNumberText.match(orderNumberRegex);

        // WIP: Order verification in admin

        /* Problematic areas:
        - Search in admin with grids, we need a helper like in cypress
        - Alternative: order number in storefornt can be used in admin/order listing
        - Fixed: Missing salutation in storefront & Caching of theme
        */

        await adminPage.goto(`#/sw/order/index`);

        await adminPage
            .getByPlaceholder("Search all orders...")
            .click();
        await expect(adminPage.locator(".sw-search-bar__footer")).toBeVisible();

        await adminPage
            .getByPlaceholder("Search all orders...")
            .fill(orderNumber);

        await expect(adminPage.locator(".sw-search-bar__footer")).toBeHidden();
        await adminPage.getByRole("link", { name: orderNumber }).click();

        await expect(adminPage.getByRole("heading"))
            .toHaveText(`Order ${orderNumber}`);

        await storefrontPage.pause();
        await expect(adminPage.getByText(defaultStorefront.customer.email))
            .toBeVisible();

        await expect(adminPage.locator('dt:has-text("Total including VAT") + dd'))
            .toHaveText('€10.00');
    });

}
