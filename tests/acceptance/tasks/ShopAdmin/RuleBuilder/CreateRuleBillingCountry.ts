import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

export const CreateRuleBillingCountry = base.extend<{ CreateRuleBillingCountry: Task }, FixtureTypes>({
    CreateRuleBillingCountry: async ({ ShopAdmin, AdminApiContext, TestDataService }, use) => {
        const task = (ruleConfig) => {
            return async function CreateRule() {
                const testRule = {
                    id: ruleConfig.ruleId,
                    name: 'Test-Rule' + ' - ' + ruleConfig.ruleId,
                    priority: 1,
                    description: 'The testiest rule there is.',
                    conditions: [
                        {
                            type: 'orContainer',
                            children: [
                                {
                                    type: 'andContainer',
                                    children: [
                                        {
                                            type: 'customerBillingCountry',
                                            value: {
                                                operator: '=',
                                                countryIds: [ruleConfig.countryId],
                                            },
                                        },
                                    ],
                                },
                            ],
                        },
                    ],
                };
                const ruleResponse = await AdminApiContext.post('rule', {
                    data: testRule,
                });
                ShopAdmin.expects(ruleResponse.ok()).toBeTruthy();
                TestDataService.addCreatedRecord('rule', ruleConfig.ruleId);
            };
        };
        await use(task);
    },
});
