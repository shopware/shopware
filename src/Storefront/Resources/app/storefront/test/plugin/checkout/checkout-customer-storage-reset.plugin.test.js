import CheckoutCustomerStorageResetPlugin from 'src/plugin/checkout/checkout-customer-storage-reset.plugin';
import SessionStorage from 'src/helper/storage/session-storage.helper';

describe('CheckoutCustomerStorageResetPlugin tests', () => {
    const storageKey = 'checkoutCustomerStorage';

    beforeEach(() => {
        document.body.innerHTML = '<div data-checkout-customer-storage-reset="true"></div>';

        window.PluginManager = {
            getPluginInstancesFromElement: jest.fn().mockReturnValue(new Map()),
            getPlugin: jest.fn().mockReturnValue({
                get: jest.fn().mockReturnValue([]),
            }),
        };

        SessionStorage.clear();
    });

    afterEach(() => {
        SessionStorage.clear();
    });

    function createPlugin(options = {}) {
        const element = document.querySelector('[data-checkout-customer-storage-reset]');

        element.setAttribute('data-checkout-customer-storage-reset-options', JSON.stringify(options));

        new CheckoutCustomerStorageResetPlugin(element, {}, 'CheckoutCustomerStorageReset');

        return element;
    }

    test('drops the persisted checkout data of every customer', () => {
        SessionStorage.setItem(storageKey, JSON.stringify({
            customerA: {
                customerComment: 'comment from customer A',
                tos: true,
            },
            customerB: {
                tos: true,
            },
        }));

        createPlugin();

        expect(SessionStorage.getItem(storageKey)).toBeNull();
    });

    test('waits for the click if resetOnClick is set', () => {
        SessionStorage.setItem(storageKey, JSON.stringify({
            customerA: {
                tos: true,
            },
        }));

        const element = createPlugin({ resetOnClick: true });

        expect(SessionStorage.getItem(storageKey)).not.toBeNull();

        element.dispatchEvent(new Event('click'));

        expect(SessionStorage.getItem(storageKey)).toBeNull();
    });

    test('keeps storage entries of other features', () => {
        SessionStorage.setItem('unrelatedKey', 'unrelated value');

        createPlugin();

        expect(SessionStorage.getItem('unrelatedKey')).toBe('unrelated value');
    });
});
