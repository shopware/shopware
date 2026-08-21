import { expect, test } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';

test(
    'Long default payment method names are displayed without overlapping the field.',
    {
        tag: '@SalesChannel',
    },
    async ({
        ShopAdmin,
        TestDataService,
        AdminSalesChannelDetail,
        DefaultSalesChannel,
        IdProvider,
        InstanceMeta,
        AdminApiContext,
    }) => {
        test.skip(satisfies(InstanceMeta.version, '<6.7.14.0'), 'Feature not available until version 6.7.14.0');

        const uuid = IdProvider.getIdPair().uuid;
        const salesChannelId = TestDataService.defaultSalesChannel.id;
        const paymentMethodName = `Test Payment | ${uuid.substring(0, 9)}+' '+${uuid.substring(10, 20)}`;
        const newPaymentMethod = await TestDataService.createBasicPaymentMethod({
            name: paymentMethodName,
        });

        await TestDataService.assignSalesChannelPaymentMethod(salesChannelId, newPaymentMethod.id);

        await ShopAdmin.goesTo(AdminSalesChannelDetail.url(salesChannelId));
        await AdminSalesChannelDetail.page.waitForURL(`**sales/channel/detail/${salesChannelId}**`);

        const defaultPaymentMethodField = AdminSalesChannelDetail.page.locator(
            '.sw-sales-channel-detail__assign-payment-methods',
        );
        const selectedText = defaultPaymentMethodField.locator('.sw-entity-single-select__selection-text');
        const paymentMethodOptions = AdminSalesChannelDetail.page.locator('.sw-select-result-list__content');
        const loadingIndicator = defaultPaymentMethodField.locator('.sw-select__selection-indicators').locator('sw-loader');

        const defaultPaymentMethodId = DefaultSalesChannel.salesChannel.paymentMethodId;
        const paymentMethodResponse = await AdminApiContext.get(
            `./payment-method/${defaultPaymentMethodId}?_response=detail`,
        );
        expect(paymentMethodResponse.ok()).toBeTruthy();
        const { data: defaultPaymentMethod } = await paymentMethodResponse.json();
        const defaultPaymentMethodName = defaultPaymentMethod.name;

        const defaultPaymentMethodInput = defaultPaymentMethodField.getByLabel('Default payment method');
        await ShopAdmin.expects(selectedText).toContainText(defaultPaymentMethodName);
        await ShopAdmin.expects(loadingIndicator).toBeHidden();

        await defaultPaymentMethodField.locator('.sw-select__selection').click();
        await loadingIndicator.waitFor({ state: 'hidden' });
        await ShopAdmin.expects(paymentMethodOptions).toBeVisible();
        await defaultPaymentMethodInput.fill(paymentMethodName);
        await loadingIndicator.waitFor({ state: 'hidden' });
        await ShopAdmin.expects(paymentMethodOptions).toBeVisible();
        await paymentMethodOptions
            .locator('.sw-select-result__result-item-text')
            .filter({ hasText: paymentMethodName })
            .click();

        await ShopAdmin.expects(selectedText).toContainText(paymentMethodName);

        const layout = await defaultPaymentMethodField.evaluate((element) => {
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
