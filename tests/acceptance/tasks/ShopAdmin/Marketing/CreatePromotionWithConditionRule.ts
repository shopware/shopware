import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

// ats
export const AddPromotionWithConditionRule = base.extend<{ AddPromotionWithConditionRule: Task }, FixtureTypes>({
    AddPromotionWithConditionRule: async ({ TestDataService, AdminApiContext, ShopAdmin, DefaultSalesChannel }, use) => {
        const task = (promotionConfig) => {
            return async function AddPromotionWithConditionRule() {
                const promotionWithConditionRule = {
                    id: promotionConfig.id,
                    name: promotionConfig.name,
                    active: true,
                    maxRedemptionsGlobal: 100,
                    maxRedemptionsPerCustomer: 10,
                    priority: 1,
                    exclusive: false,
                    useCodes: promotionConfig.useCode,
                    useIndividualCodes: promotionConfig.useCode,
                    useSetGroups: false,
                    preventCombination: true,
                    customerRestriction: false,
                    discounts: [
                        {
                            scope: promotionConfig.discountScope || 'cart',
                            type: promotionConfig.discountType || 'percentage',
                            value: promotionConfig.discountValue || 10,
                            considerAdvancedRules: false,
                        },
                    ],
                    salesChannels: [
                        {
                            salesChannelId: DefaultSalesChannel.salesChannel.id,
                            priority: 1,
                        },
                    ],
                    personaRules: [
                        {
                            id: promotionConfig.ruleId,
                        }
                    ]
                };
                const promotionResponse = await AdminApiContext.post('promotion?_response=detail', {
                    data: promotionWithConditionRule,
                });

                ShopAdmin.expects(promotionResponse.ok()).toBeTruthy();
                TestDataService.addCreatedRecord('promotion', promotionConfig.id);
                TestDataService.addCreatedRecord('rule', promotionConfig.ruleId);
            }
        };
        await use(task);
    },
});
