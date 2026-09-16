import CheckoutCustomerStoragePlugin from 'src/plugin/checkout/checkout-customer-storage.plugin';
import SessionStorage from 'src/helper/storage/session-storage.helper';
import template from './checkout-customer-storage.plugin.template.html';

describe('CheckoutCustomerStoragePlugin tests', () => {
    const storageKey = 'checkoutCustomerStorage';

    beforeEach(() => {
        document.body.innerHTML = template;

        window.PluginManager = {
            getPluginInstancesFromElement: jest.fn().mockReturnValue(new Map()),
            getPlugin: jest.fn().mockReturnValue({
                get: jest.fn().mockReturnValue([]),
            }),
        };

        SessionStorage.clear();
        window.localStorage.clear();
    });

    afterEach(() => {
        SessionStorage.clear();
        window.localStorage.clear();
    });

    function createPlugin(customerId) {
        const form = document.querySelector('#confirmOrderForm');

        form.setAttribute('data-checkout-customer-storage-options', JSON.stringify({ customerId }));

        new CheckoutCustomerStoragePlugin(form, {}, 'CheckoutCustomerStorage');

        return {
            customerComment: document.querySelector('#customerComment'),
            tos: document.querySelector('#tos'),
        };
    }

    function storedCustomers() {
        return JSON.parse(SessionStorage.getItem(storageKey));
    }

    test('restores the comment for the active customer only', () => {
        SessionStorage.setItem(storageKey, JSON.stringify({
            customerA: {
                customerComment: 'comment from customer A',
            },
            customerB: {
                customerComment: 'comment from customer B',
            },
        }));

        const { customerComment } = createPlugin('customerB');

        expect(customerComment.value).toBe('comment from customer B');
    });

    test('restores tos for the active customer only', () => {
        SessionStorage.setItem(storageKey, JSON.stringify({
            customerA: {
                customerComment: 'comment from customer A',
            },
            customerB: {
                tos: true,
            },
        }));

        const { tos } = createPlugin('customerB');

        expect(tos.checked).toBe(true);
    });

    test('restores the tos acceptance when the checkout page is loaded again', () => {
        const { tos } = createPlugin('customerA');

        tos.checked = true;
        tos.dispatchEvent(new Event('change'));

        document.body.innerHTML = template;

        expect(createPlugin('customerA').tos.checked).toBe(true);
    });

    test('persists into the session storage and never into the local storage', () => {
        const { tos } = createPlugin('customerA');

        tos.checked = true;
        tos.dispatchEvent(new Event('change'));

        expect(window.sessionStorage.getItem(storageKey)).not.toBeNull();
        expect(window.localStorage.getItem(storageKey)).toBeNull();
    });

    test('updates only the active customer comment entry', () => {
        SessionStorage.setItem(storageKey, JSON.stringify({
            customerA: {
                customerComment: 'comment from customer A',
            },
            customerB: {
                customerComment: 'comment from customer B',
                tos: true,
            },
        }));

        const { customerComment } = createPlugin('customerA');

        customerComment.value = 'updated comment';
        customerComment.dispatchEvent(new Event('input'));

        expect(storedCustomers()).toEqual({
            customerA: {
                customerComment: 'updated comment',
            },
            customerB: {
                customerComment: 'comment from customer B',
                tos: true,
            },
        });
    });

    test('updates only the active customer tos entry', () => {
        SessionStorage.setItem(storageKey, JSON.stringify({
            customerA: {
                customerComment: 'comment from customer A',
            },
            customerB: {
                customerComment: 'comment from customer B',
            },
        }));

        const { tos } = createPlugin('customerA');

        tos.checked = true;
        tos.dispatchEvent(new Event('change'));

        expect(storedCustomers()).toEqual({
            customerA: {
                customerComment: 'comment from customer A',
                tos: true,
            },
            customerB: {
                customerComment: 'comment from customer B',
            },
        });
    });

    test('removes tos when it is unchecked', () => {
        SessionStorage.setItem(storageKey, JSON.stringify({
            customerA: {
                customerComment: 'comment from customer A',
                tos: true,
            },
        }));

        const { tos } = createPlugin('customerA');

        tos.checked = false;
        tos.dispatchEvent(new Event('change'));

        expect(storedCustomers()).toEqual({
            customerA: {
                customerComment: 'comment from customer A',
            },
        });
    });

    test('removes the whole storage key when the last customer entry is cleared', () => {
        SessionStorage.setItem(storageKey, JSON.stringify({
            customerA: {
                customerComment: 'comment from customer A',
            },
        }));

        const { customerComment } = createPlugin('customerA');

        customerComment.value = '';
        customerComment.dispatchEvent(new Event('change'));

        expect(SessionStorage.getItem(storageKey)).toBeNull();
    });

    test.each(['submit', 'reset'])('clears only the active customer entry on %s', (eventType) => {
        SessionStorage.setItem(storageKey, JSON.stringify({
            customerA: {
                customerComment: 'comment from customer A',
                tos: true,
            },
            customerB: {
                customerComment: 'comment from customer B',
            },
        }));

        createPlugin('customerA');

        const form = document.querySelector('#confirmOrderForm');
        form.dispatchEvent(new Event(eventType));

        expect(storedCustomers()).toEqual({
            customerB: {
                customerComment: 'comment from customer B',
            },
        });
    });

    test('ignores malformed storage payloads safely', () => {
        SessionStorage.setItem(storageKey, 'not-json');

        const { customerComment, tos } = createPlugin('customerA');

        expect(customerComment.value).toBe('');
        expect(tos.checked).toBe(false);
        expect(SessionStorage.getItem(storageKey)).toBe('not-json');
    });

    test('does not restore shared checkout data if the customer id is missing', () => {
        SessionStorage.setItem(storageKey, JSON.stringify({
            customerA: {
                customerComment: 'comment from customer A',
                tos: true,
            },
        }));

        const form = document.querySelector('#confirmOrderForm');
        new CheckoutCustomerStoragePlugin(form, {}, 'CheckoutCustomerStorage');

        expect(document.querySelector('#customerComment').value).toBe('');
        expect(document.querySelector('#tos').checked).toBe(false);
    });
});
