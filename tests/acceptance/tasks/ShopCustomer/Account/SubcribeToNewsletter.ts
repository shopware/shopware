import { request, test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

export const SubcribeToNewsletter = base.extend<{ SubcribeToNewsletter: Task }, FixtureTypes>({
    SubcribeToNewsletter: async ({ ShopCustomer, DefaultSalesChannel }, use) => {
        const task = (recipientConfig) => {
            return async function subscribe() {
                const subscribeData = {
                    email: recipientConfig.email,
                    option: 'direct',
                    storefrontUrl: DefaultSalesChannel.url.replace(/\/+$/, ''),
                };

                const createStoreApiContext = async (contextToken?: string) => {
                    return request.newContext({
                        baseURL: new URL('store-api/', process.env.APP_URL as string).toString(),
                        ignoreHTTPSErrors: true,
                        extraHTTPHeaders: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'sw-access-key': DefaultSalesChannel.salesChannel.accessKey,
                            ...(contextToken ? { 'sw-context-token': contextToken } : {}),
                        },
                    });
                };

                let storeApiContext = await createStoreApiContext();

                try {
                    const customerPassword = recipientConfig.password
                        ?? (recipientConfig.email === DefaultSalesChannel.customer.email
                            ? DefaultSalesChannel.customer.password
                            : undefined);

                    if (customerPassword) {
                        const loginResponse = await storeApiContext.post('account/login', {
                            data: {
                                username: recipientConfig.email,
                                password: customerPassword,
                            },
                        });
                        ShopCustomer.expects(loginResponse.ok()).toBeTruthy();

                        const contextToken = loginResponse.headers()['sw-context-token'];
                        if (!contextToken) {
                            throw new Error(`Failed to create Store API customer context for ${recipientConfig.email}`);
                        }

                        await storeApiContext.dispose();
                        storeApiContext = await createStoreApiContext(contextToken);
                    }

                    const subscribeResponse = await storeApiContext.post('newsletter/subscribe', {
                        data: subscribeData,
                    });

                    ShopCustomer.expects(subscribeResponse.ok()).toBeTruthy();
                } finally {
                    await storeApiContext.dispose();
                }
            };
        };
        await use(task);
    },
});
