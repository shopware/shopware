import Plugin from 'src/plugin-system/plugin.class';
import Storage from 'src/helper/storage/storage.helper';

/**
 * Clears checkout form preserver entries from storage on the login page so that
 * a customer's comment is never pre-filled for a subsequent user on the same device.
 *
 * @sw-package checkout
 */
export default class CustomerImitationResetPlugin extends Plugin {
    static options = {
        /**
         * Storage keys to remove on plugin initialization.
         *
         * @type {string[]}
         */
        storageKeys: ['confirmOrderForm.customerComment'],
    };

    init() {
        this.options.storageKeys.forEach((key) => {
            Storage.removeItem(key);
        });
    }
}
