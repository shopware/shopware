import { request, test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

export const SubcribeToNewsletter = base.extend<{ SubcribeToNewsletter: Task }, FixtureTypes>({
    SubcribeToNewsletter: async ({ TestDataService, AdminApiContext, ShopAdmin, DefaultSalesChannel }, use) => {
        const task = (recipientConfig) => {
            return async function subscribe() {
                const recipientPayload = {
                    email: recipientConfig.email,
                    salesChannelId: DefaultSalesChannel.salesChannel.id,
                    firstName: DefaultSalesChannel.customer.firstName ?? 'Test',
                    lastName: DefaultSalesChannel.customer.lastName ?? 'User',
                    hash: recipientConfig.hash, 
                    status: 'direct', 
                    languageId: DefaultSalesChannel.salesChannel.languageId,
                    // salutationId: DefaultSalesChannel.salesChannel.salutationId,
                    confirmedAt: new Date().toISOString()
                };

                const resp = await AdminApiContext.post('newsletter-recipient?_response=detail', {
                    data: recipientPayload,
                });
                await ShopAdmin.expects(resp.ok()).toBeTruthy();
                const created = await resp.json();
                if (created?.data?.id) {
                    TestDataService.addCreatedRecord('newsletter_recipient', created.data.id);
                } else {
                    TestDataService.addCreatedRecord('newsletter_recipient', recipientPayload.email);
                }

            };
        };
        await use(task);
    },
});
