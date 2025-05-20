import { test as base } from '@playwright/test';
import type {FixtureTypes, Task} from '@fixtures/AcceptanceTest';

export const AddCreditItem = base.extend<{ AddCreditItem: Task }, FixtureTypes>({
    AddCreditItem: async ({ AdminApiContext, ShopAdmin }, use)=> {
        const task = (orderId: string)   => {
            return async function AddCreditItem() {

                const creditItem = {
                    'identifier': orderId,
                    'orderId': orderId,
                    'quantity': 1,
                    'label': 'CreditItem',
                    'payload': [],
                    'good': true,
                    'removable': true,
                    'stackable': true,
                    'position': 2,
                    'states': [],
                    'price': {
                        'unitPrice': -1.0,
                        'totalPrice': -1.0,
                        'calculatedTaxes': [
                            {
                                'extensions': [],
                                'tax': -0.16,
                                'taxRate': 19.0,
                                'price': -1.0,
                            },
                        ],
                        'taxRules': [
                            {
                                'extensions': [],
                                'taxRate': 19.0,
                                'percentage': 100.0,
                            },
                        ],
                        'quantity': 1,
                    },
                }
                const productResponse = await AdminApiContext.post('order-line-item', {
                    data: creditItem,
                });
                ShopAdmin.expects(productResponse.ok()).toBeTruthy();
            }
        };
        await use(task);
    },
});
