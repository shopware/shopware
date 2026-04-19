import type { Locator } from '@playwright/test';
import { test, expect, PropertyGroup, getCurrencyCodeFromLocale, formatPrice } from '@fixtures/AcceptanceTest';

type StorefrontListingLocators = {
    productName: Locator;
    productPrice: Locator;
    productListingPrice: Locator;
    productListingPricePercentage: Locator;
    productListingPriceBadge: Locator;
};

type StorefrontHomeLike = {
    page: {
        getByRole: (role: string, options: { name: string }) => Locator;
    };
    productListItems: Locator;
    getListingItemByProductName: (productListingName: string) => Promise<StorefrontListingLocators>;
};

const createPriceCollection = (currencyId: string, defaultCurrencyId: string, gross: number, listPriceGross?: number) => {
    const createPrice = (currentCurrencyId: string) => {
        const price: {
            currencyId: string;
            gross: number;
            linked: boolean;
            net: number;
            listPrice?: {
                currencyId: string;
                gross: number;
                linked: boolean;
                net: number;
            };
            percentage?: {
                gross: number;
                net: number;
            };
        } = {
            currencyId: currentCurrencyId,
            gross,
            linked: false,
            net: Number((gross * 0.84).toFixed(2)),
        };

        if (listPriceGross !== undefined) {
            const percentage = Number((100 - gross / listPriceGross * 100).toFixed(2));

            price.listPrice = {
                currencyId: currentCurrencyId,
                gross: listPriceGross,
                linked: false,
                net: Number((listPriceGross * 0.84).toFixed(2)),
            };
            price.percentage = {
                gross: percentage,
                net: percentage,
            };
        }

        return price;
    };

    return [currencyId, defaultCurrencyId].map(createPrice);
};

const refreshProductIndex = async (
    adminApiContext: {
        post: (url: string, options: { data: Record<string, unknown> }) => Promise<{ ok: () => boolean }>;
    },
    productIds: string[],
) => {
    const indexProductsResponse = await adminApiContext.post('./_action/index-products', {
        data: {
            ids: productIds,
        },
    });

    expect(indexProductsResponse.ok()).toBeTruthy();

    const productIndexerResponse = await adminApiContext.post('./_action/indexing/product.indexer?_response=detail', {
        data: {
            offset: 0,
        },
    });

    expect(productIndexerResponse.ok()).toBeTruthy();
};

const getListingCardLocators = async (storefrontHome: StorefrontHomeLike, productName: string) => {
    const productItemLocators = await storefrontHome.getListingItemByProductName(productName);
    const listingItem = storefrontHome.productListItems.filter({
        has: storefrontHome.page.getByRole('link', { name: productName }),
    });

    return {
        ...productItemLocators,
        productPriceWrapper: listingItem.locator('.product-price-wrapper'),
    };
};

const createTwoOptionPropertyGroup = async (
    testDataService: {
        createTextPropertyGroup: (overrides?: { options: Array<{ name: string }> }) => Promise<PropertyGroup>;
    },
) => testDataService.createTextPropertyGroup({
    options: [
        { name: 'One' },
        { name: 'Two' },
    ],
});

test(
    'should show the same discounted price and badge on the parent listing and variant detail pages when all variants share the same discounted price',
    {
        tag: [
            '@Product, @Variant',
            '@Storefront',
        ],
    },
    async ({
        ShopCustomer,
        TestDataService,
        AdminApiContext,
        StorefrontHome,
        StorefrontProductDetail,
        SalesChannelBaseConfig,
        InstanceMeta,
    }) => {
        await test.skip(InstanceMeta.isSaaS, 'Skipping on SaaS instances due to instability in variant creation.');
        // TODO: https://github.com/shopware/shopware/issues/14608
        const currency = await TestDataService.getCurrency(getCurrencyCodeFromLocale());
        const prices = createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 10, 20);

        const parentProduct = await TestDataService.createBasicProduct({
            price: prices,
            variantListingConfig: { displayParent: true },
        });
        const propertyGroup = await createTwoOptionPropertyGroup(TestDataService);
        const variantProducts = await TestDataService.createVariantProducts(parentProduct, [propertyGroup], {
            price: prices,
        });

        await refreshProductIndex(AdminApiContext, [parentProduct.id, ...variantProducts.map(({ id }) => id)]);

        await TestDataService.clearCaches();

        await test.step('Product is visible on storefront.', async () => {
            await ShopCustomer.goesTo(StorefrontHome.url());
            const productItemLocators = await getListingCardLocators(StorefrontHome, parentProduct.name);
            await ShopCustomer.expects(productItemLocators.productName).toBeVisible();
        });

        await test.step('Validating listing price is available on product listing page for base variant product.', async () => {
            await ShopCustomer.goesTo(StorefrontHome.url());
            const productItemLocators = await getListingCardLocators(StorefrontHome, parentProduct.name);
            await ShopCustomer.expects(productItemLocators.productPrice).toContainText(formatPrice(10.0));
            await ShopCustomer.expects(productItemLocators.productListingPrice).toContainText(formatPrice(20.0));
            await ShopCustomer.expects(productItemLocators.productListingPricePercentage).toContainText('(50% saved)');
            await ShopCustomer.expects(productItemLocators.productListingPriceBadge).toContainText('%');
        });

        await test.step('Validating listing price is available for each variant product.', async () => {
            for (const variantProduct of variantProducts) {
                await ShopCustomer.goesTo(StorefrontProductDetail.url(variantProduct));
                await ShopCustomer.expects(StorefrontProductDetail.productSinglePrice).toContainText(formatPrice(10.0));
                await ShopCustomer.expects(StorefrontProductDetail.productListingPriceBadge).toContainText('%');
                await ShopCustomer.expects(StorefrontProductDetail.productListingPrice).toContainText(formatPrice(20.0));
                await ShopCustomer.expects(StorefrontProductDetail.productListingPricePercentage).toContainText(
                    '(50% saved)',
                );
            }
        });
    }
);

test(
    'should show from price, concrete cheapest list price, and discount badge when equally priced variants have different valid list prices',
    {
        tag: ['@Product, @Variant', '@Storefront'],
    },
    async ({ ShopCustomer, TestDataService, StorefrontHome, SalesChannelBaseConfig, AdminApiContext }) => {
        const currency = await TestDataService.getCurrency(getCurrencyCodeFromLocale());
        const prices = createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 10, 20);

        const parentProduct = await TestDataService.createBasicProduct({
            price: prices,
            variantListingConfig: { displayParent: true },
        });
        const propertyGroup = await createTwoOptionPropertyGroup(TestDataService);
        const variantProducts = await TestDataService.createVariantProducts(parentProduct, [propertyGroup], {
            price: prices,
        });

        await refreshProductIndex(AdminApiContext, [parentProduct.id, ...variantProducts.map(({ id }) => id)]);

        await test.step('Set one equally priced variant to a different valid list price.', async () => {
            const variantToUpdate = variantProducts[1]!;
            const patchResponse = await AdminApiContext.patch(`product/${variantToUpdate.id}`, {
                data: {
                    price: createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 10, 30),
                },
            });

            expect(patchResponse.ok()).toBeTruthy();
        });

        await refreshProductIndex(AdminApiContext, [parentProduct.id, ...variantProducts.map(({ id }) => id)]);

        await ShopCustomer.expects(async () => {
            await test.step('Wait for the parent listing with differing valid list prices to be visible.', async () => {
                await TestDataService.clearCaches();
                await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
                const productItemLocators = await getListingCardLocators(StorefrontHome, parentProduct.name);
                await ShopCustomer.expects(productItemLocators.productName).toBeVisible();
            });
        }).toPass({
            intervals: [1_000, 2_500],
        });

        await test.step('Validating equally priced variants with different valid list prices still keep the cheapest concrete list price.', async () => {
            await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
            const productItemLocators = await getListingCardLocators(StorefrontHome, parentProduct.name);
            await ShopCustomer.expects(productItemLocators.productPriceWrapper).toContainText('From');
            await ShopCustomer.expects(productItemLocators.productPrice).toContainText(formatPrice(10.0));
            await ShopCustomer.expects(productItemLocators.productListingPrice).toContainText(formatPrice(20.0));
            await ShopCustomer.expects(productItemLocators.productListingPricePercentage).toContainText('(50% saved)');
            await ShopCustomer.expects(productItemLocators.productListingPriceBadge).toContainText('%');
        });
    }
);

test(
    'should show plain cheapest price without from price or discount UI when equally priced variants have no displayable list prices',
    {
        tag: ['@Product, @Variant', '@Storefront'],
    },
    async ({ ShopCustomer, TestDataService, StorefrontHome, SalesChannelBaseConfig, AdminApiContext }) => {
        const currency = await TestDataService.getCurrency(getCurrencyCodeFromLocale());
        const prices = createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 12);

        const parentProduct = await TestDataService.createBasicProduct({
            price: prices,
            variantListingConfig: { displayParent: true },
        });
        const propertyGroup = await createTwoOptionPropertyGroup(TestDataService);
        const variantProducts = await TestDataService.createVariantProducts(parentProduct, [propertyGroup], {
            price: prices,
        });

        await refreshProductIndex(AdminApiContext, [parentProduct.id, ...variantProducts.map(({ id }) => id)]);

        await ShopCustomer.expects(async () => {
            await test.step('Wait for equally priced undiscounted child variants to be visible on storefront.', async () => {
                await TestDataService.clearCaches();
                await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
                const productItemLocators = await getListingCardLocators(StorefrontHome, parentProduct.name);
                await ShopCustomer.expects(productItemLocators.productName).toBeVisible();
            });
        }).toPass({
            intervals: [1_000, 2_500],
        });

        await test.step('Validating equally priced undiscounted variants show a plain price without discount UI.', async () => {
            await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
            const productItemLocators = await getListingCardLocators(StorefrontHome, parentProduct.name);
            await ShopCustomer.expects(productItemLocators.productPriceWrapper).not.toContainText('From');
            await ShopCustomer.expects(productItemLocators.productPrice).toContainText(formatPrice(12.0));
            await ShopCustomer.expects(productItemLocators.productListingPrice).not.toBeVisible();
            await ShopCustomer.expects(productItemLocators.productListingPricePercentage).not.toBeVisible();
            await ShopCustomer.expects(productItemLocators.productListingPriceBadge).not.toBeVisible();
        });
    }
);

test(
    'should show from price, concrete cheapest list price, and discount badge when differently priced variants have the same valid list price',
    {
        tag: ['@Product, @Variant', '@Storefront'],
    },
    async ({ ShopCustomer, TestDataService, StorefrontHome, SalesChannelBaseConfig, AdminApiContext }) => {
        const currency = await TestDataService.getCurrency(getCurrencyCodeFromLocale());
        const parentPrices = createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 10, 20);

        const parentProduct = await TestDataService.createBasicProduct({
            price: parentPrices,
            variantListingConfig: { displayParent: true },
        });
        const propertyGroup = await createTwoOptionPropertyGroup(TestDataService);
        const variantProducts = await TestDataService.createVariantProducts(parentProduct, [propertyGroup], {
            price: parentPrices,
        });

        await refreshProductIndex(AdminApiContext, [parentProduct.id, ...variantProducts.map(({ id }) => id)]);

        await test.step('Set one variant to a higher discounted price so the range comes from child variants.', async () => {
            const variantToUpdate = variantProducts[0]!;
            const patchResponse = await AdminApiContext.patch(`product/${variantToUpdate.id}`, {
                data: {
                    price: createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 12, 20),
                },
            });

            expect(patchResponse.ok()).toBeTruthy();
        });

        await refreshProductIndex(AdminApiContext, [parentProduct.id, ...variantProducts.map(({ id }) => id)]);

        await ShopCustomer.expects(async () => {
            await test.step('Wait for discounted variant parent to be visible on storefront.', async () => {
                await TestDataService.clearCaches();
                await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
                const productItemLocators = await getListingCardLocators(StorefrontHome, parentProduct.name);
                await ShopCustomer.expects(productItemLocators.productName).toBeVisible();
            });
        }).toPass({
            intervals: [1_000, 2_500],
        });

        await test.step('Validating from price, list price and badge are rendered together on the product card.', async () => {
            await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
            const productItemLocators = await getListingCardLocators(StorefrontHome, parentProduct.name);
            await ShopCustomer.expects(productItemLocators.productPriceWrapper).toContainText('From');
            await ShopCustomer.expects(productItemLocators.productPrice).toContainText(formatPrice(10.0));
            await ShopCustomer.expects(productItemLocators.productListingPrice).toContainText(formatPrice(20.0));
            await ShopCustomer.expects(productItemLocators.productListingPricePercentage).toContainText('(50% saved)');
            await ShopCustomer.expects(productItemLocators.productListingPriceBadge).toContainText('%');
        });
    }
);

test(
    'should show from price, concrete cheapest list price, and discount badge when differently priced variants have different valid list prices',
    {
        tag: ['@Product, @Variant', '@Storefront'],
    },
    async ({ ShopCustomer, TestDataService, StorefrontHome, SalesChannelBaseConfig, AdminApiContext }) => {
        const currency = await TestDataService.getCurrency(getCurrencyCodeFromLocale());
        const parentPrices = createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 10, 20);

        const parentProduct = await TestDataService.createBasicProduct({
            price: parentPrices,
            variantListingConfig: { displayParent: true },
        });
        const propertyGroup = await createTwoOptionPropertyGroup(TestDataService);
        const variantProducts = await TestDataService.createVariantProducts(parentProduct, [propertyGroup], {
            price: parentPrices,
        });

        await refreshProductIndex(AdminApiContext, [parentProduct.id, ...variantProducts.map(({ id }) => id)]);

        await test.step('Set one variant to a higher discounted price with a different list price.', async () => {
            const variantToUpdate = variantProducts[0]!;
            const patchResponse = await AdminApiContext.patch(`product/${variantToUpdate.id}`, {
                data: {
                    price: createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 12, 24),
                },
            });

            expect(patchResponse.ok()).toBeTruthy();
        });

        await refreshProductIndex(AdminApiContext, [parentProduct.id, ...variantProducts.map(({ id }) => id)]);

        await ShopCustomer.expects(async () => {
            await test.step('Wait for the parent listing with differing list prices to be visible.', async () => {
                await TestDataService.clearCaches();
                await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
                const productItemLocators = await getListingCardLocators(StorefrontHome, parentProduct.name);
                await ShopCustomer.expects(productItemLocators.productName).toBeVisible();
            });
        }).toPass({
            intervals: [1_000, 2_500],
        });

        await test.step('Validating the cheapest concrete list price still renders together with from price and badge.', async () => {
            await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
            const productItemLocators = await getListingCardLocators(StorefrontHome, parentProduct.name);
            await ShopCustomer.expects(productItemLocators.productPriceWrapper).toContainText('From');
            await ShopCustomer.expects(productItemLocators.productPrice).toContainText(formatPrice(10.0));
            await ShopCustomer.expects(productItemLocators.productListingPrice).toContainText(formatPrice(20.0));
            await ShopCustomer.expects(productItemLocators.productListingPricePercentage).toContainText('(50% saved)');
            await ShopCustomer.expects(productItemLocators.productListingPriceBadge).toContainText('%');
        });
    }
);

test(
    'should show plain cheapest price without from price when only the parent price differs from equally priced variants',
    {
        tag: ['@Product, @Variant', '@Storefront'],
    },
    async ({ ShopCustomer, TestDataService, StorefrontHome, SalesChannelBaseConfig, AdminApiContext }) => {
        const currency = await TestDataService.getCurrency(getCurrencyCodeFromLocale());
        const parentPrices = createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 10);
        const variantPrices = createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 12);

        const parentProduct = await TestDataService.createBasicProduct({
            price: parentPrices,
            variantListingConfig: { displayParent: true },
        });
        const propertyGroup = await createTwoOptionPropertyGroup(TestDataService);
        const variantProducts = await TestDataService.createVariantProducts(parentProduct, [propertyGroup], {
            price: variantPrices,
        });

        await refreshProductIndex(AdminApiContext, [parentProduct.id, ...variantProducts.map(({ id }) => id)]);

        await ShopCustomer.expects(async () => {
            await test.step('Wait for equally priced child variants to be visible on storefront.', async () => {
                await TestDataService.clearCaches();
                await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
                const productItemLocators = await getListingCardLocators(StorefrontHome, parentProduct.name);
                await ShopCustomer.expects(productItemLocators.productName).toBeVisible();
            });
        }).toPass({
            intervals: [1_000, 2_500],
        });

        await test.step('Validating parent-only price differences do not trigger from price.', async () => {
            await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
            const productItemLocators = await getListingCardLocators(StorefrontHome, parentProduct.name);
            await ShopCustomer.expects(productItemLocators.productPriceWrapper).not.toContainText('From');
            await ShopCustomer.expects(productItemLocators.productPrice).toContainText(formatPrice(12.0));
        });
    }
);

test(
    'should show from price, concrete cheapest list price, and discount badge when equally priced variants mix discounted and non-discounted states and the displayed cheapest variant is discounted',
    {
        tag: ['@Product, @Variant', '@Storefront'],
    },
    async ({ ShopCustomer, TestDataService, StorefrontHome, SalesChannelBaseConfig, AdminApiContext }) => {
        const currency = await TestDataService.getCurrency(getCurrencyCodeFromLocale());
        const parentPrices = createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 10);
        const variantPrices = createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 12);

        const parentProduct = await TestDataService.createBasicProduct({
            price: parentPrices,
            variantListingConfig: { displayParent: true },
        });
        const propertyGroup = await createTwoOptionPropertyGroup(TestDataService);
        const variantProducts = await TestDataService.createVariantProducts(parentProduct, [propertyGroup], {
            price: variantPrices,
        });

        await refreshProductIndex(AdminApiContext, [parentProduct.id, ...variantProducts.map(({ id }) => id)]);

        await test.step('Set one equally priced child variant to a discounted list price.', async () => {
            const variantToUpdate = variantProducts[0]!;
            const patchResponse = await AdminApiContext.patch(`product/${variantToUpdate.id}`, {
                data: {
                    price: createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 12, 20),
                },
            });

            expect(patchResponse.ok()).toBeTruthy();
        });

        await refreshProductIndex(AdminApiContext, [parentProduct.id, ...variantProducts.map(({ id }) => id)]);

        await ShopCustomer.expects(async () => {
            await test.step('Wait for mixed list price variant parent to be visible on storefront.', async () => {
                await TestDataService.clearCaches();
                await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
                const productItemLocators = await getListingCardLocators(StorefrontHome, parentProduct.name);
                await ShopCustomer.expects(productItemLocators.productName).toBeVisible();
            });
        }).toPass({
            intervals: [1_000, 2_500],
        });

        await test.step('Validating mixed list price states still show the discounted cheapest list price.', async () => {
            await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
            const productItemLocators = await getListingCardLocators(StorefrontHome, parentProduct.name);
            await ShopCustomer.expects(productItemLocators.productPriceWrapper).toContainText('From');
            await ShopCustomer.expects(productItemLocators.productPrice).toContainText(formatPrice(12.0));
            await ShopCustomer.expects(productItemLocators.productListingPrice).toContainText(formatPrice(20.0));
            await ShopCustomer.expects(productItemLocators.productListingPricePercentage).toContainText('(40% saved)');
            await ShopCustomer.expects(productItemLocators.productListingPriceBadge).toContainText('%');
        });
    }
);

test(
    'should show from price, concrete cheapest list price, and discount badge when equally priced variants mix discounted and non-discounted states regardless of variant order',
    {
        tag: ['@Product, @Variant', '@Storefront'],
    },
    async ({ ShopCustomer, TestDataService, StorefrontHome, SalesChannelBaseConfig, AdminApiContext }) => {
        const currency = await TestDataService.getCurrency(getCurrencyCodeFromLocale());
        const parentPrices = createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 10);
        const variantPrices = createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 12);

        const parentProduct = await TestDataService.createBasicProduct({
            price: parentPrices,
            variantListingConfig: { displayParent: true },
        });
        const propertyGroup = await createTwoOptionPropertyGroup(TestDataService);
        const variantProducts = await TestDataService.createVariantProducts(parentProduct, [propertyGroup], {
            price: variantPrices,
        });

        await refreshProductIndex(AdminApiContext, [parentProduct.id, ...variantProducts.map(({ id }) => id)]);

        await test.step('Set the later equally priced child variant to a discounted list price.', async () => {
            const variantToUpdate = variantProducts[1]!;
            const patchResponse = await AdminApiContext.patch(`product/${variantToUpdate.id}`, {
                data: {
                    price: createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 12, 20),
                },
            });

            expect(patchResponse.ok()).toBeTruthy();
        });

        await refreshProductIndex(AdminApiContext, [parentProduct.id, ...variantProducts.map(({ id }) => id)]);

        await ShopCustomer.expects(async () => {
            await test.step('Wait for the mixed equally priced parent listing to be visible on storefront.', async () => {
                await TestDataService.clearCaches();
                await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
                const productItemLocators = await getListingCardLocators(StorefrontHome, parentProduct.name);
                await ShopCustomer.expects(productItemLocators.productName).toBeVisible();
            });
        }).toPass({
            intervals: [1_000, 2_500],
        });

        await test.step('Validating the discounted concrete list price is selected even when the discounted variant is created later.', async () => {
            await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
            const productItemLocators = await getListingCardLocators(StorefrontHome, parentProduct.name);
            await ShopCustomer.expects(productItemLocators.productPriceWrapper).toContainText('From');
            await ShopCustomer.expects(productItemLocators.productPrice).toContainText(formatPrice(12.0));
            await ShopCustomer.expects(productItemLocators.productListingPrice).toContainText(formatPrice(20.0));
            await ShopCustomer.expects(productItemLocators.productListingPricePercentage).toContainText('(40% saved)');
            await ShopCustomer.expects(productItemLocators.productListingPriceBadge).toContainText('%');
        });
    }
);

test(
    'should show plain cheapest price without from price or discount UI when equally priced variants only differ between zero-percent and missing list prices',
    {
        tag: ['@Product, @Variant', '@Storefront'],
    },
    async ({ ShopCustomer, TestDataService, StorefrontHome, SalesChannelBaseConfig, AdminApiContext }) => {
        const currency = await TestDataService.getCurrency(getCurrencyCodeFromLocale());
        const parentPrices = createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 10);
        const variantPrices = createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 12);

        const parentProduct = await TestDataService.createBasicProduct({
            price: parentPrices,
            variantListingConfig: { displayParent: true },
        });
        const propertyGroup = await createTwoOptionPropertyGroup(TestDataService);
        const variantProducts = await TestDataService.createVariantProducts(parentProduct, [propertyGroup], {
            price: variantPrices,
        });

        await refreshProductIndex(AdminApiContext, [parentProduct.id, ...variantProducts.map(({ id }) => id)]);

        await test.step('Set one equally priced child variant to a zero-percent list price.', async () => {
            const variantToUpdate = variantProducts[0]!;
            const patchResponse = await AdminApiContext.patch(`product/${variantToUpdate.id}`, {
                data: {
                    price: createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 12, 12),
                },
            });

            expect(patchResponse.ok()).toBeTruthy();
        });

        await refreshProductIndex(AdminApiContext, [parentProduct.id, ...variantProducts.map(({ id }) => id)]);

        await ShopCustomer.expects(async () => {
            await test.step('Wait for zero-percent list price parent to be visible on storefront.', async () => {
                await TestDataService.clearCaches();
                await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
                const productItemLocators = await getListingCardLocators(StorefrontHome, parentProduct.name);
                await ShopCustomer.expects(productItemLocators.productName).toBeVisible();
            });
        }).toPass({
            intervals: [1_000, 2_500],
        });

        await test.step('Validating zero-percent list prices do not trigger from price or discount UI.', async () => {
            await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
            const productItemLocators = await getListingCardLocators(StorefrontHome, parentProduct.name);
            await ShopCustomer.expects(productItemLocators.productPriceWrapper).not.toContainText('From');
            await ShopCustomer.expects(productItemLocators.productPrice).toContainText(formatPrice(12.0));
            await ShopCustomer.expects(productItemLocators.productListingPrice).not.toBeVisible();
            await ShopCustomer.expects(productItemLocators.productListingPricePercentage).not.toBeVisible();
            await ShopCustomer.expects(productItemLocators.productListingPriceBadge).not.toBeVisible();
        });
    }
);

test(
    'should show from price without discount UI when differently priced variants have no displayable list prices',
    {
        tag: ['@Product, @Variant', '@Storefront'],
    },
    async ({ ShopCustomer, TestDataService, StorefrontHome, SalesChannelBaseConfig, AdminApiContext }) => {
        const currency = await TestDataService.getCurrency(getCurrencyCodeFromLocale());
        const parentPrices = createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 10);
        const variantPrices = createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 10);

        const parentProduct = await TestDataService.createBasicProduct({
            price: parentPrices,
            variantListingConfig: { displayParent: true },
        });
        const propertyGroup = await createTwoOptionPropertyGroup(TestDataService);
        const variantProducts = await TestDataService.createVariantProducts(parentProduct, [propertyGroup], {
            price: variantPrices,
        });

        await refreshProductIndex(AdminApiContext, [parentProduct.id, ...variantProducts.map(({ id }) => id)]);

        await test.step('Set one child variant to a higher undiscounted price.', async () => {
            const variantToUpdate = variantProducts[0]!;
            const patchResponse = await AdminApiContext.patch(`product/${variantToUpdate.id}`, {
                data: {
                    price: createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 12),
                },
            });

            expect(patchResponse.ok()).toBeTruthy();
        });

        await refreshProductIndex(AdminApiContext, [parentProduct.id, ...variantProducts.map(({ id }) => id)]);

        await ShopCustomer.expects(async () => {
            await test.step('Wait for differently priced undiscounted variants to be visible on storefront.', async () => {
                await TestDataService.clearCaches();
                await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
                const productItemLocators = await getListingCardLocators(StorefrontHome, parentProduct.name);
                await ShopCustomer.expects(productItemLocators.productName).toBeVisible();
            });
        }).toPass({
            intervals: [1_000, 2_500],
        });

        await test.step('Validating differently priced undiscounted variants only show from price and the cheapest selling price.', async () => {
            await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
            const productItemLocators = await getListingCardLocators(StorefrontHome, parentProduct.name);
            await ShopCustomer.expects(productItemLocators.productPriceWrapper).toContainText('From');
            await ShopCustomer.expects(productItemLocators.productPrice).toContainText(formatPrice(10.0));
            await ShopCustomer.expects(productItemLocators.productListingPrice).not.toBeVisible();
            await ShopCustomer.expects(productItemLocators.productListingPricePercentage).not.toBeVisible();
            await ShopCustomer.expects(productItemLocators.productListingPriceBadge).not.toBeVisible();
        });
    }
);

test(
    'should show from price, concrete cheapest list price, and discount badge when differently priced variants have a discounted cheapest variant and a non-discounted higher variant',
    {
        tag: ['@Product, @Variant', '@Storefront'],
    },
    async ({ ShopCustomer, TestDataService, StorefrontHome, SalesChannelBaseConfig, AdminApiContext }) => {
        const currency = await TestDataService.getCurrency(getCurrencyCodeFromLocale());
        const parentPrices = createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 10);
        const variantPrices = createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 10);

        const parentProduct = await TestDataService.createBasicProduct({
            price: parentPrices,
            variantListingConfig: { displayParent: true },
        });
        const propertyGroup = await createTwoOptionPropertyGroup(TestDataService);
        const variantProducts = await TestDataService.createVariantProducts(parentProduct, [propertyGroup], {
            price: variantPrices,
        });

        await refreshProductIndex(AdminApiContext, [parentProduct.id, ...variantProducts.map(({ id }) => id)]);

        await test.step('Set the displayed cheapest child variant to a discounted price and the sibling to a higher plain price.', async () => {
            const cheapestVariantPatchResponse = await AdminApiContext.patch(`product/${variantProducts[0]!.id}`, {
                data: {
                    price: createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 10, 20),
                },
            });

            expect(cheapestVariantPatchResponse.ok()).toBeTruthy();

            const higherVariantPatchResponse = await AdminApiContext.patch(`product/${variantProducts[1]!.id}`, {
                data: {
                    price: createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 12),
                },
            });

            expect(higherVariantPatchResponse.ok()).toBeTruthy();
        });

        await refreshProductIndex(AdminApiContext, [parentProduct.id, ...variantProducts.map(({ id }) => id)]);

        await ShopCustomer.expects(async () => {
            await test.step('Wait for the differently priced parent listing with a discounted cheapest variant to be visible on storefront.', async () => {
                await TestDataService.clearCaches();
                await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
                const productItemLocators = await getListingCardLocators(StorefrontHome, parentProduct.name);
                await ShopCustomer.expects(productItemLocators.productName).toBeVisible();
            });
        }).toPass({
            intervals: [1_000, 2_500],
        });

        await test.step('Validating the cheapest discounted variant keeps its concrete list price while the listing stays in from mode.', async () => {
            await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
            const productItemLocators = await getListingCardLocators(StorefrontHome, parentProduct.name);
            await ShopCustomer.expects(productItemLocators.productPriceWrapper).toContainText('From');
            await ShopCustomer.expects(productItemLocators.productPrice).toContainText(formatPrice(10.0));
            await ShopCustomer.expects(productItemLocators.productListingPrice).toContainText(formatPrice(20.0));
            await ShopCustomer.expects(productItemLocators.productListingPricePercentage).toContainText('(50% saved)');
            await ShopCustomer.expects(productItemLocators.productListingPriceBadge).toContainText('%');
        });
    }
);

test(
    'should show from price and discount badge without a concrete list price when differently priced variants have a discounted higher variant and a non-discounted cheapest variant',
    {
        tag: ['@Product, @Variant', '@Storefront'],
    },
    async ({ ShopCustomer, TestDataService, StorefrontHome, SalesChannelBaseConfig, AdminApiContext }) => {
        const currency = await TestDataService.getCurrency(getCurrencyCodeFromLocale());
        const parentPrices = createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 10);
        const variantPrices = createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 10);

        const parentProduct = await TestDataService.createBasicProduct({
            price: parentPrices,
            variantListingConfig: { displayParent: true },
        });
        const propertyGroup = await createTwoOptionPropertyGroup(TestDataService);
        const variantProducts = await TestDataService.createVariantProducts(parentProduct, [propertyGroup], {
            price: variantPrices,
        });

        await refreshProductIndex(AdminApiContext, [parentProduct.id, ...variantProducts.map(({ id }) => id)]);

        await test.step('Set one child variant to a higher discounted price.', async () => {
            const variantToUpdate = variantProducts[0]!;
            const patchResponse = await AdminApiContext.patch(`product/${variantToUpdate.id}`, {
                data: {
                    price: createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 12, 20),
                },
            });

            expect(patchResponse.ok()).toBeTruthy();
        });

        await refreshProductIndex(AdminApiContext, [parentProduct.id, ...variantProducts.map(({ id }) => id)]);

        await ShopCustomer.expects(async () => {
            await test.step('Wait for the differently priced mixed discount parent listing to be visible on storefront.', async () => {
                await TestDataService.clearCaches();
                await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
                const productItemLocators = await getListingCardLocators(StorefrontHome, parentProduct.name);
                await ShopCustomer.expects(productItemLocators.productName).toBeVisible();
            });
        }).toPass({
            intervals: [1_000, 2_500],
        });

        await test.step('Validating only the badge remains when the cheapest displayed variant is not discounted.', async () => {
            await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
            const productItemLocators = await getListingCardLocators(StorefrontHome, parentProduct.name);
            await ShopCustomer.expects(productItemLocators.productPriceWrapper).toContainText('From');
            await ShopCustomer.expects(productItemLocators.productPrice).toContainText(formatPrice(10.0));
            await ShopCustomer.expects(productItemLocators.productListingPrice).not.toBeVisible();
            await ShopCustomer.expects(productItemLocators.productListingPricePercentage).not.toBeVisible();
            await ShopCustomer.expects(productItemLocators.productListingPriceBadge).toContainText('%');
        });
    }
);

test(
    'should show from price without discount UI when differently priced variants only differ between zero-percent and missing list prices',
    {
        tag: ['@Product, @Variant', '@Storefront'],
    },
    async ({ ShopCustomer, TestDataService, StorefrontHome, SalesChannelBaseConfig, AdminApiContext }) => {
        const currency = await TestDataService.getCurrency(getCurrencyCodeFromLocale());
        const parentPrices = createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 10);
        const variantPrices = createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 10);

        const parentProduct = await TestDataService.createBasicProduct({
            price: parentPrices,
            variantListingConfig: { displayParent: true },
        });
        const propertyGroup = await createTwoOptionPropertyGroup(TestDataService);
        const variantProducts = await TestDataService.createVariantProducts(parentProduct, [propertyGroup], {
            price: variantPrices,
        });

        await refreshProductIndex(AdminApiContext, [parentProduct.id, ...variantProducts.map(({ id }) => id)]);

        await test.step('Set one child variant to a higher zero-percent list price.', async () => {
            const variantToUpdate = variantProducts[0]!;
            const patchResponse = await AdminApiContext.patch(`product/${variantToUpdate.id}`, {
                data: {
                    price: createPriceCollection(currency.id, SalesChannelBaseConfig.defaultCurrencyId, 12, 12),
                },
            });

            expect(patchResponse.ok()).toBeTruthy();
        });

        await refreshProductIndex(AdminApiContext, [parentProduct.id, ...variantProducts.map(({ id }) => id)]);

        await ShopCustomer.expects(async () => {
            await test.step('Wait for the differently priced zero-percent parent listing to be visible on storefront.', async () => {
                await TestDataService.clearCaches();
                await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
                const productItemLocators = await getListingCardLocators(StorefrontHome, parentProduct.name);
                await ShopCustomer.expects(productItemLocators.productName).toBeVisible();
            });
        }).toPass({
            intervals: [1_000, 2_500],
        });

        await test.step('Validating zero-percent list prices stay invisible while the from state still comes from the real child price range.', async () => {
            await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
            const productItemLocators = await getListingCardLocators(StorefrontHome, parentProduct.name);
            await ShopCustomer.expects(productItemLocators.productPriceWrapper).toContainText('From');
            await ShopCustomer.expects(productItemLocators.productPrice).toContainText(formatPrice(10.0));
            await ShopCustomer.expects(productItemLocators.productListingPrice).not.toBeVisible();
            await ShopCustomer.expects(productItemLocators.productListingPricePercentage).not.toBeVisible();
            await ShopCustomer.expects(productItemLocators.productListingPriceBadge).not.toBeVisible();
        });
    },
);
