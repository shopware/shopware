import { test as base } from '@playwright/test';
import { expect } from '@fixtures/AcceptanceTest';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import { components } from '@shopware/api-client/admin-api-types';

export const PromotionWithCodeData = base.extend<FixtureTypes>({
    promotionWithCodeData: async ({ adminApiContext, defaultStorefront, idProvider }, use) => {

        // Generate promotion code
        const promotionCode = `${idProvider.getIdPair().id}`;
        const promotionName = `Test Promotion ${promotionCode}`;

        // Create a new promotion with code via admin API context
        const promotionResponse = await adminApiContext.post('promotion?_response=1', {
            data: {
                name: promotionName,
                active: true,
                maxRedemptionsGlobal: 100,
                maxRedemptionsPerCustomer: 10,
                priority: 1,
                exclusive: false,
                useCodes: true,
                useIndividualCodes: false,
                useSetGroups: false,
                preventCombination: true,
                customerRestriction: false,
                code: promotionCode,
                discounts: [
                    {
                        scope: 'cart',
                        type: 'percentage',
                        value: 10,
                        considerAdvancedRules: false,
                    },
                ],
                salesChannels: [
                    {
                        salesChannelId: defaultStorefront.salesChannel.id,
                        priority: 1,
                    },
                ],
            },
        });

        await expect(promotionResponse.ok()).toBeTruthy();

        const { data: promotion } = (await promotionResponse.json()) as { data: components['schemas']['Promotion'] };

        // User promotion data in the test
        await use(promotion);

        // Delete promotion after test is done
        const cleanupResponse = await adminApiContext.delete(`promotion/${promotion.id}`);
        await expect(cleanupResponse.ok()).toBeTruthy();
    },
});
