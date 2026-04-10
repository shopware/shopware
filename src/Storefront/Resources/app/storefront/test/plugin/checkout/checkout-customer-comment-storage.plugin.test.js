import CheckoutCustomerCommentStoragePlugin from 'src/plugin/checkout/checkout-customer-comment-storage.plugin';
import Storage from 'src/helper/storage/storage.helper';
import template from './checkout-customer-comment-storage.plugin.template.html';

describe('CheckoutCustomerCommentStoragePlugin tests', () => {
    const storageKey = 'checkoutCustomerComment';

    beforeEach(() => {
        document.body.innerHTML = template;

        window.PluginManager = {
            getPluginInstancesFromElement: jest.fn().mockReturnValue(new Map()),
            getPlugin: jest.fn().mockReturnValue({
                get: jest.fn().mockReturnValue([]),
            }),
        };

        Storage.clear();
    });

    afterEach(() => {
        Storage.clear();
    });

    function createPlugin(customerId) {
        const element = document.querySelector('#customerComment');

        element.setAttribute('data-checkout-customer-comment-storage-options', JSON.stringify({ customerId }));

        new CheckoutCustomerCommentStoragePlugin(element, {}, 'CheckoutCustomerCommentStorage');

        return element;
    }

    function storedComments() {
        return JSON.parse(Storage.getItem(storageKey));
    }

    test('restores the comment for the active customer only', () => {
        Storage.setItem(storageKey, JSON.stringify({
            customerA: 'comment from customer A',
            customerB: 'comment from customer B',
        }));

        const element = createPlugin('customerB');

        expect(element.value).toBe('comment from customer B');
    });

    test('updates only the active customer entry', () => {
        Storage.setItem(storageKey, JSON.stringify({
            customerA: 'comment from customer A',
            customerB: 'comment from customer B',
        }));

        const element = createPlugin('customerA');

        element.value = 'updated comment';
        element.dispatchEvent(new Event('input'));

        expect(storedComments()).toEqual({
            customerA: 'updated comment',
            customerB: 'comment from customer B',
        });
    });

    test('removes only the active customer entry when the comment is emptied', () => {
        Storage.setItem(storageKey, JSON.stringify({
            customerA: 'comment from customer A',
            customerB: 'comment from customer B',
        }));

        const element = createPlugin('customerA');

        element.value = '';
        element.dispatchEvent(new Event('change'));

        expect(storedComments()).toEqual({
            customerB: 'comment from customer B',
        });
    });

    test('removes the whole storage key when the last customer comment is emptied', () => {
        Storage.setItem(storageKey, JSON.stringify({
            customerA: 'comment from customer A',
        }));

        const element = createPlugin('customerA');

        element.value = '';
        element.dispatchEvent(new Event('change'));

        expect(Storage.getItem(storageKey)).toBeNull();
    });

    test.each(['submit', 'reset'])('clears only the active customer entry on %s', (eventType) => {
        Storage.setItem(storageKey, JSON.stringify({
            customerA: 'comment from customer A',
            customerB: 'comment from customer B',
        }));

        createPlugin('customerA');

        const form = document.querySelector('#confirmOrderForm');
        form.dispatchEvent(new Event(eventType));

        expect(storedComments()).toEqual({
            customerB: 'comment from customer B',
        });
    });

    test('does not restore a shared comment if the customer id is missing', () => {
        Storage.setItem(storageKey, JSON.stringify({
            customerA: 'comment from customer A',
        }));

        const element = document.querySelector('#customerComment');
        new CheckoutCustomerCommentStoragePlugin(element, {}, 'CheckoutCustomerCommentStorage');

        expect(element.value).toBe('');
    });
});
