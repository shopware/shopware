import { test, getLanguageCode, getLocale, type Product } from '@fixtures/AcceptanceTest';

const testLegalGuaranteeNotice = (showTosCheckbox: boolean) =>
    test(
        `Customer sees GARAN label with showTosCheckbox=${showTosCheckbox}.`,
        {
            tag: [
                '@Checkout',
                '@Storefront',
            ],
        },
        async ({
            ShopCustomer,
            TestDataService,
            InstanceMeta,
            StorefrontProductDetail,
            StorefrontCheckoutConfirm,
            StorefrontCheckoutFinish,
            StorefrontAccountOrder,
            Login,
            AddProductToCart,
            ProceedFromProductToCheckout,
            ConfirmTermsAndConditions,
            SelectPaymentMethod,
            SelectShippingMethod,
            SubmitOrder,
        }) => {
            await TestDataService.setSystemConfig({
                'core.cart.showLegalGuaranteeNotice': true,
                'core.cart.showTosCheckbox': showTosCheckbox,
            });
            const manufacturer = await TestDataService.createBasicManufacturer({ name: 'GARAN-ACME' });
            const guaranteeMonths = 36;
            const expectedGuaranteeDuration = '3';

            const expectedLegalGuaranteeNoticeUrl = (locale = getLocale()): string => {
                const languagePrefix = getLanguageCode(locale).split('-')[0]?.toLowerCase() ?? 'en';
                const slug = languagePrefix === 'de' ? 'garantien' : 'guarantees';

                return `https://europa.eu/youreurope/${slug}`;
            };
            const garanLabelInfoLanguage = getLanguageCode(getLocale()).split('-')[0]?.toLowerCase() === 'de' ? 'de' : 'en';
            const garanLabelInfoUrl = `https://europa.eu/youreurope/citizens/consumers/shopping/commercial-guarantee-durability/index_${garanLabelInfoLanguage}.htm`;
            const garanLabelModal = StorefrontProductDetail.page.locator('.js-pseudo-modal .modal.show');
            const product = await TestDataService.createBasicProduct({
                name: 'GARAN-Label-Checkout-Product',
                manufacturerId: manufacturer.id,
                manufacturerNumber: 'ACME-36',
                ...({
                    guaranteeMonths,
                    guaranteeConfirmed: true,
                } as Partial<Product>),
            });

            await ShopCustomer.attemptsTo(Login());
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product));

            await test.step('Product detail page shows nested GARAN label and expands to full label.', async () => {
                await ShopCustomer.expects(StorefrontProductDetail.garanNestedLabel).toBeVisible();
                await ShopCustomer.expects(StorefrontProductDetail.garanNestedLabel).toContainText(
                    expectedGuaranteeDuration,
                );
                await ShopCustomer.expects(StorefrontProductDetail.showGaranLabelButton).toBeVisible();
                await ShopCustomer.expects(StorefrontProductDetail.hideGaranLabelButton).not.toBeVisible();

                await ShopCustomer.presses(StorefrontProductDetail.showGaranLabelButton);

                await ShopCustomer.expects(StorefrontProductDetail.garanFullLabel).toContainText('GARAN-ACME');
                await ShopCustomer.expects(StorefrontProductDetail.garanFullLabel).toContainText('ACME-36');
                await ShopCustomer.expects(StorefrontProductDetail.garanFullLabel).toContainText(expectedGuaranteeDuration);
                await ShopCustomer.expects(StorefrontProductDetail.showGaranLabelButton).not.toBeVisible();
                await ShopCustomer.expects(StorefrontProductDetail.hideGaranLabelButton).toBeVisible();
            });

            await test.step('Nested GARAN label on the product detail page expands the full label itself.', async () => {
                await ShopCustomer.presses(StorefrontProductDetail.hideGaranLabelButton);
                await ShopCustomer.expects(StorefrontProductDetail.garanFullLabel).not.toBeVisible();

                await ShopCustomer.presses(StorefrontProductDetail.garanNestedLabel.getByRole('button'));

                await ShopCustomer.expects(StorefrontProductDetail.garanFullLabel).toBeVisible();
                await ShopCustomer.expects(StorefrontProductDetail.hideGaranLabelButton).toBeFocused();
                await ShopCustomer.expects(StorefrontProductDetail.garanFullLabel.getByRole('link')).toHaveAttribute(
                    'href',
                    garanLabelInfoUrl,
                );
            });

            await test.step('Nested GARAN label on cart and checkout line items opens the full label.', async () => {
                await ShopCustomer.attemptsTo(AddProductToCart(product));
                await ShopCustomer.expects(StorefrontProductDetail.offCanvasLineItemGaranLabel).toBeVisible();

                await ShopCustomer.presses(StorefrontProductDetail.offCanvasLineItemGaranLabel.getByRole('button'));
                await ShopCustomer.expects(garanLabelModal).toBeFocused();
                await ShopCustomer.expects(garanLabelModal).toContainText('GARAN-ACME');
                await ShopCustomer.expects(garanLabelModal).toContainText('ACME-36');
                await ShopCustomer.expects(garanLabelModal.getByRole('link')).toHaveAttribute('href', garanLabelInfoUrl);
                await ShopCustomer.presses(garanLabelModal.locator('.btn-close'));
                await ShopCustomer.expects(garanLabelModal).not.toBeVisible();

                await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
                await ShopCustomer.expects(StorefrontCheckoutConfirm.lineItemGaranLabel).toBeVisible();
            });

            await test.step('Legal guarantee notice modal displays with expected Europe language URL.', async () => {
                await ShopCustomer.expects(StorefrontCheckoutConfirm.legalGuaranteeNoticeLink).toHaveAttribute(
                    'href',
                    expectedLegalGuaranteeNoticeUrl(),
                );
            });

            await test.step('Legal guarantee notice follows the active terms layout.', async () => {
                if (InstanceMeta.features['V6_8_0_0'] && !showTosCheckbox) {
                    await ShopCustomer.expects(StorefrontCheckoutConfirm.termsAndConditionsCheckbox).not.toBeVisible();
                    await ShopCustomer.expects(
                        StorefrontCheckoutConfirm.termsAutoConfirmedText.locator(
                            '[data-bs-target="#legalGuaranteeNoticeModal"]',
                        ),
                    ).toBeVisible();
                    await ShopCustomer.expects(
                        StorefrontCheckoutConfirm.page.locator('.legal-guarantee-notice'),
                    ).not.toBeVisible();
                } else {
                    await ShopCustomer.expects(StorefrontCheckoutConfirm.termsAndConditionsCheckbox).toBeVisible();
                    await ShopCustomer.expects(
                        StorefrontCheckoutConfirm.page.locator('.legal-guarantee-notice'),
                    ).toBeVisible();
                    await ShopCustomer.expects(StorefrontCheckoutConfirm.termsAutoConfirmedText).not.toBeVisible();
                }
                await ShopCustomer.expects(
                    StorefrontCheckoutConfirm.termsAndConditionsWithLegalGuaranteeRightsLabel,
                ).not.toBeVisible();
            });

            let orderNumber: string;

            await test.step('Customer can complete checkout.', async () => {
                await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
                await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));
                await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
                await ShopCustomer.attemptsTo(SubmitOrder());

                const orderId = StorefrontCheckoutFinish.getOrderId();
                TestDataService.addCreatedRecord('order', orderId);
                orderNumber = await StorefrontCheckoutFinish.getOrderNumber();
            });

            await test.step('Nested GARAN label on account order detail opens the full label.', async () => {
                await ShopCustomer.goesTo(StorefrontAccountOrder.url());
                const orderLocators = await StorefrontAccountOrder.getOrderByOrderNumber(orderNumber, product.productNumber);
                await ShopCustomer.presses(orderLocators.orderDetailButton);
                await ShopCustomer.expects(orderLocators.lineItemGaranLabel).toBeVisible();

                await ShopCustomer.presses(orderLocators.lineItemGaranLabel.getByRole('button'));
                await ShopCustomer.expects(garanLabelModal).toContainText('ACME-36');
            });
        },
    );

testLegalGuaranteeNotice(true);
testLegalGuaranteeNotice(false);

test(
    'Customer sees standard terms and no GARAN label when legal guarantee notice is disabled.',
    {
        tag: [
            '@Checkout',
            '@Storefront',
        ],
    },
    async ({
        ShopCustomer,
        TestDataService,
        InstanceMeta,
        StorefrontProductDetail,
        StorefrontCheckoutConfirm,
        StorefrontCheckoutFinish,
        StorefrontAccountOrder,
        Login,
        AddProductToCart,
        ProceedFromProductToCheckout,
        ConfirmTermsAndConditions,
        SelectPaymentMethod,
        SelectShippingMethod,
        SubmitOrder,
    }) => {
        await TestDataService.setSystemConfig({ 'core.cart.showLegalGuaranteeNotice': false });
        const manufacturer = await TestDataService.createBasicManufacturer({ name: 'GARAN-ACME-Off' });
        const product = await TestDataService.createBasicProduct({
            name: 'GARAN-Label-Checkout-Product-Notice-Off',
            manufacturerId: manufacturer.id,
            manufacturerNumber: 'ACME-36-OFF',
            ...({
                guaranteeMonths: 36,
                guaranteeConfirmed: false,
            } as Partial<Product>),
        });

        await ShopCustomer.attemptsTo(Login());
        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));

        await test.step('Product detail page does not show GARAN label.', async () => {
            await ShopCustomer.expects(StorefrontProductDetail.garanNestedLabel).not.toBeVisible();
            await ShopCustomer.expects(StorefrontProductDetail.showGaranLabelButton).not.toBeVisible();
        });

        await test.step('Cart and checkout show standard terms without GARAN or legal guarantee notice.', async () => {
            await ShopCustomer.attemptsTo(AddProductToCart(product));
            await ShopCustomer.expects(StorefrontProductDetail.offCanvasLineItemGaranLabel).not.toBeVisible();
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());

            await ShopCustomer.expects(StorefrontCheckoutConfirm.lineItemGaranLabel).not.toBeVisible();
            await ShopCustomer.expects(StorefrontCheckoutConfirm.legalGuaranteeNoticeLink).not.toBeVisible();
            await ShopCustomer.expects(StorefrontCheckoutConfirm.page.locator('.legal-guarantee-notice')).not.toBeVisible();
            if (InstanceMeta.features['V6_8_0_0']) {
                await ShopCustomer.expects(StorefrontCheckoutConfirm.termsAndConditionsCheckbox).not.toBeVisible();
                await ShopCustomer.expects(StorefrontCheckoutConfirm.termsAutoConfirmedText).toBeVisible();
            } else {
                await ShopCustomer.expects(StorefrontCheckoutConfirm.termsAndConditionsCheckbox).toBeVisible();
            }
        });

        let orderNumber: string;

        await test.step('Customer can complete checkout.', async () => {
            await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
            await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));
            await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
            await ShopCustomer.attemptsTo(SubmitOrder());

            const orderId = StorefrontCheckoutFinish.getOrderId();
            TestDataService.addCreatedRecord('order', orderId);
            orderNumber = await StorefrontCheckoutFinish.getOrderNumber();
        });

        await test.step('Nested GARAN label is not visible on account order detail.', async () => {
            await ShopCustomer.goesTo(StorefrontAccountOrder.url());
            const orderLocators = await StorefrontAccountOrder.getOrderByOrderNumber(orderNumber, product.productNumber);
            await ShopCustomer.presses(orderLocators.orderDetailButton);
            await ShopCustomer.expects(orderLocators.lineItemGaranLabel).not.toBeVisible();
        });
    },
);
