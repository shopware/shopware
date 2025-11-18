import { test as base } from '@playwright/test';
import type {FixtureTypes, Task} from '@fixtures/AcceptanceTest';

export const CreateInvoice = base.extend<{ CreateInvoice: Task }, FixtureTypes>({
    CreateInvoice: async ({ AdminApiContext, ShopAdmin }, use)=> {
        const task = (orderId: string)   => {
            return async function CreateInvoice() {

                const orderInvoice = {
                    'data':
                        {
                            'orderId': orderId,
                            'config': {
                                'name': 'invoice',
                            },
                        },
                }
                const orderResponse = await AdminApiContext.post('_action/order/document/invoice/create', {
                    data: orderInvoice,
                });
                ShopAdmin.expects(orderResponse.ok()).toBeTruthy();
            }
        };
        await use(task);
    },
});
