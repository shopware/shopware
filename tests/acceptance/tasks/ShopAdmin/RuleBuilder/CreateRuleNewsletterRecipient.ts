import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';
// move ats
export const CreateRuleNewsletterRecipient = base.extend<{ CreateRuleNewsletterRecipient: Task }, FixtureTypes>({
    CreateRuleNewsletterRecipient: async ({ ShopAdmin, AdminApiContext, TestDataService }, use) => {
        const task = (ruleConfig) => {
            return async function CreateRule() {
                const testRule = {
                    id: ruleConfig.ruleId,
                    name: 'Test-Rule' + ' - ' + ruleConfig.ruleId,
                    priority: 1,
                    description: 'This rule applied for newsletter recipients',
                    conditions: [
                        {
                            type: 'orContainer',
                            children: [
                                {
                                    type: 'andContainer',
                                    children: [
                                        {
                                            type: 'customerIsNewsletterRecipient',
                                            value: {
                                                isNewsletterRecipient: true,
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
