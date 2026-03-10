import Plugin from 'src/plugin-system/plugin.class';
import Storage from 'src/helper/storage/storage.helper';

/**
 * Clears checkout form preserver entries from storage when the login form is submitted,
 * so that a customer's comment is never pre-filled for a subsequent user on the same device.
 *
 * @sw-package checkout
 */
export default class CheckoutCustomerCommentResetPlugin extends Plugin {

    static options = {
        /**
         * Storage keys to remove when the login form is submitted.
         *
         * @type {string[]}
         */
        storageKeys: ['confirmOrderForm.customerComment'],
    };

    init() {
        this.el.addEventListener('submit', this._onSubmit.bind(this));
    }

    /**
     * @private
     */
    _onSubmit() {
        this.options.storageKeys.forEach((key) => {
            Storage.removeItem(key);
        });
    }
}
