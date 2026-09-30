import { test as base } from '@shopware-ag/acceptance-test-suite';
import type { FixtureTypes, Task } from '@shopware-ag/acceptance-test-suite';

export const WaitForFlowValidationSideEffects = base.extend<{ WaitForFlowValidationSideEffects: Task }, FixtureTypes>({
    WaitForFlowValidationSideEffects: async ({ ShopAdmin, AdminApiContext }, use) => {
        const task = (config) => {
            return async function WaitForFlowValidationSideEffects() {
                // Buffered flows run on Kernel TERMINATE after the process response — wait for persisted side-effects.
                await ShopAdmin.expects(async () => {
                    const orderResponse = await AdminApiContext.post('search/order', {
                        data: {
                            ids: [config.orderId],
                            associations: {
                                stateMachineState: {},
                                transactions: {
                                    associations: {
                                        stateMachineState: {},
                                    },
                                },
                                deliveries: {
                                    associations: {
                                        stateMachineState: {},
                                    },
                                },
                            },
                        },
                    });
                    ShopAdmin.expects(orderResponse.ok()).toBeTruthy();

                    const { data: orders } = await orderResponse.json();
                    ShopAdmin.expects(orders).toHaveLength(1);

                    const expectTechnicalName = (technicalName: string) =>
                        ShopAdmin.expects.objectContaining({ technicalName });
                    const expectEntityInState = (technicalName: string) =>
                        ShopAdmin.expects.objectContaining({
                            stateMachineState: expectTechnicalName(technicalName),
                        });

                    ShopAdmin.expects(orders[0]).toEqual(
                        ShopAdmin.expects.objectContaining({
                            stateMachineState: expectTechnicalName('in_progress'),
                            transactions: ShopAdmin.expects.arrayContaining([expectEntityInState('paid')]),
                            deliveries: ShopAdmin.expects.arrayContaining([
                                expectEntityInState('shipped_partially'),
                            ]),
                        }),
                    );
                }).toPass();
            };
        };

        await use(task);
    },
});
