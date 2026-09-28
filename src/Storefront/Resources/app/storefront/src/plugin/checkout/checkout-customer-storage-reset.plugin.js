import Plugin from 'src/plugin-system/plugin.class';
import SessionStorage from 'src/helper/storage/session-storage.helper';

/**
 * Drops the checkout form data persisted by the CheckoutCustomerStoragePlugin,
 * so a following checkout starts from an empty form.
 *
 * Bind it to a page that ends a checkout: an emptied cart, the order
 * confirmation, and the page a logout lands on.
 *
 * @sw-package checkout
 */
export default class CheckoutCustomerStorageResetPlugin extends Plugin {
    static options = {
        storageKey: 'checkoutCustomerStorage',
    };

    init() {
        SessionStorage.removeItem(this.options.storageKey);
    }
}
