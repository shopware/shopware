import { test } from "@fixtures/AcceptanceTest";

test(
    "Shop administrator should be able to delete a product in the administration.",
    { tag: "@Product" },
    async ({
        ShopAdmin,
        TestDataService,
        AdminProductListing,
        DeleteProduct,
    }) => {
        const product = await TestDataService.createBasicProduct();

        await ShopAdmin.goesTo(
            AdminProductListing.url([product.productNumber]),
        );
        const productRow = await AdminProductListing.getProductRow(
            product.productNumber,
        );
        await ShopAdmin.expects(productRow.productName).toHaveText(
            product.name,
        );

        await ShopAdmin.attemptsTo(DeleteProduct(product.productNumber));

        await ShopAdmin.expects(productRow.productName).toBeHidden();
    },
);

test(
    "Shop administrator should be able to delete a digital product in the administration.",
    { tag: "@Product" },
    async ({
        ShopAdmin,
        TestDataService,
        AdminProductListing,
        DeleteProduct,
    }) => {
        const product = await TestDataService.createDigitalProduct();

        await ShopAdmin.goesTo(
            AdminProductListing.url([product.productNumber]),
        );
        const productRow = await AdminProductListing.getProductRow(
            product.productNumber,
        );
        await ShopAdmin.expects(
            productRow.productDigitalIndicator,
        ).toBeVisible();

        await ShopAdmin.attemptsTo(DeleteProduct(product.productNumber));

        await ShopAdmin.expects(productRow.productName).toBeHidden();
    },
);
