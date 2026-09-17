import Plugin from 'src/plugin-system/plugin.class';
import SessionStorage from 'src/helper/storage/session-storage.helper';

/**
 * Drops the checkout form data persisted by the CheckoutCustomerStoragePlugin,
 * so a following checkout starts from an empty form.
 *
 * Bind it to something that ends a checkout. By default it resets as soon as the
 * element is rendered, which suits a page such as the order confirmation. With
 * `resetOnClick` it waits for a click instead, which is what a control on the
 * checkout itself needs, such as the button back to the shop.
 *
 * @sw-package checkout
 */
export default class CheckoutCustomerStorageResetPlugin extends Plugin {
    static options = {
        storageKey: 'checkoutCustomerStorage',
        resetOnClick: false,
    };

    init() {
        if (this.options.resetOnClick) {
            this.el.addEventListener('click', () => this._reset());

            return;
        }

        this._reset();
    }

    _reset() {
        SessionStorage.removeItem(this.options.storageKey);
    }
}
