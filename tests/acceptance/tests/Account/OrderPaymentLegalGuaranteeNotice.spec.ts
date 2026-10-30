import { test } from '@fixtures/AcceptanceTest';

test(
    'Customers can view the legal guarantee notice when changing payment for an existing order.',
    {
        tag: [
            '@Order',
            '@Account',
            '@Storefront',
        ],
    },
    async ({ ShopCustomer, StorefrontAccountOrder, StorefrontCheckoutOrderEdit, TestDataService, InstanceMeta, Login }) => {
        test.skip(
            InstanceMeta.features['V6_8_0_0'],
            'The 6.8 terms layout does not display the legal guarantee notice on the order payment page.',
        );

        const product = await TestDataService.createBasicProduct();
        const customer = await TestDataService.createCustomer();
        const order = await TestDataService.createOrder([{ product: product, quantity: 1 }], customer);

        const page = StorefrontCheckoutOrderEdit.page;
        const legalGuaranteeNoticeLink = page.locator('[data-bs-target="#legalGuaranteeNoticeModal"]');
        const legalGuaranteeNoticeModal = page.locator('#legalGuaranteeNoticeModal');
        const legalGuaranteeNoticeInline = page.locator('#legalGuaranteeNoticeInline');

        await TestDataService.setSystemConfig({
            'core.cart.showLegalGuaranteeNotice': true,
            'core.cart.showLegalGuaranteeNoticeInline': false,
        });

        await ShopCustomer.attemptsTo(Login(customer));
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());
        const orderLocators = await StorefrontAccountOrder.getOrderByOrderNumber(order.orderNumber);
        await ShopCustomer.presses(orderLocators.orderActionsButton);
        await ShopCustomer.presses(orderLocators.orderChangePaymentMethodButton);

        await test.step('The guarantee button opens the notice modal.', async () => {
            await ShopCustomer.expects(legalGuaranteeNoticeLink).toBeVisible();
            await ShopCustomer.expects(legalGuaranteeNoticeModal).toHaveCount(1);
            await ShopCustomer.expects(legalGuaranteeNoticeModal).not.toBeVisible();

            await ShopCustomer.presses(legalGuaranteeNoticeLink);

            await ShopCustomer.expects(legalGuaranteeNoticeModal).toBeVisible();
            await ShopCustomer.expects(legalGuaranteeNoticeModal.locator('svg')).toBeVisible();
            await ShopCustomer.expects(legalGuaranteeNoticeModal.getByRole('link')).toHaveAttribute(
                'href',
                /^https:\/\/europa\.eu\/youreurope\//,
            );
        });

        await test.step('The inline setting displays the notice without a modal.', async () => {
            await TestDataService.setSystemConfig({ 'core.cart.showLegalGuaranteeNoticeInline': true });
            await ShopCustomer.goesTo(StorefrontCheckoutOrderEdit.url(order.id));

            await ShopCustomer.expects(page.locator('a[href="#legalGuaranteeNoticeInline"]')).toBeVisible();
            await ShopCustomer.expects(legalGuaranteeNoticeInline).toBeVisible();
            await ShopCustomer.expects(legalGuaranteeNoticeInline.locator('svg')).toBeVisible();
            await ShopCustomer.expects(legalGuaranteeNoticeModal).toHaveCount(0);
        });

        await test.step('Disabling the notice removes the link and notice content.', async () => {
            await TestDataService.setSystemConfig({ 'core.cart.showLegalGuaranteeNotice': false });
            await ShopCustomer.goesTo(StorefrontCheckoutOrderEdit.url(order.id));

            await ShopCustomer.expects(page.locator('.legal-guarantee-notice')).toHaveCount(0);
            await ShopCustomer.expects(legalGuaranteeNoticeLink).toHaveCount(0);
            await ShopCustomer.expects(legalGuaranteeNoticeModal).toHaveCount(0);
            await ShopCustomer.expects(legalGuaranteeNoticeInline).toHaveCount(0);
        });
    },
);
