import { expect, test } from '@fixtures/AcceptanceTest';

test(
    'Long default payment method names are displayed without overlapping the field.',
    {
        tag: '@SalesChannel',
    },
    async ({ ShopAdmin, TestDataService, AdminSalesChannelDetail, DefaultSalesChannel }) => {
        const salesChannelId = DefaultSalesChannel.salesChannel.id;
        const paymentMethodName = 'PayPal | PayPal Products for Shopware 6';
        await TestDataService.createBasicPaymentMethod({
            name: paymentMethodName,
        });

        await ShopAdmin.goesTo(AdminSalesChannelDetail.url(salesChannelId));

        const defaultPaymentMethod = AdminSalesChannelDetail.page.locator(
            '.sw-sales-channel-detail__assign-payment-methods',
        );
        const selectedText = defaultPaymentMethod.locator('.sw-entity-single-select__selection-text');

        await defaultPaymentMethod.locator('.sw-select__selection').click();
        const searchInput = defaultPaymentMethod.locator('.sw-entity-single-select__selection-input');
        await searchInput.fill(paymentMethodName);
        await AdminSalesChannelDetail.page
            .locator('.sw-select-result__result-item-text')
            .filter({ hasText: paymentMethodName })
            .click();

        await expect(selectedText).toContainText(paymentMethodName);

        const layout = await defaultPaymentMethod.evaluate((element) => {
            const label = element.querySelector('.sw-field__label')?.getBoundingClientRect();
            const block = element.querySelector('.sw-block-field__block')?.getBoundingClientRect();
            const text = element.querySelector('.sw-entity-single-select__selection-text')?.getBoundingClientRect();
            const indicator = element.querySelector('.sw-select__selection-indicators')?.getBoundingClientRect();

            if (!label || !block || !text || !indicator) {
                return null;
            }

            return {
                labelIsAboveField: label.bottom <= block.top,
                textIsInsideField:
                    text.left >= block.left &&
                    text.top >= block.top &&
                    text.right <= block.right &&
                    text.bottom <= block.bottom,
                indicatorIsInsideField: indicator.right <= block.right && indicator.bottom <= block.bottom,
            };
        });

        expect(layout).toEqual({
            labelIsAboveField: true,
            textIsInsideField: true,
            indicatorIsInsideField: true,
        });
    },
);
