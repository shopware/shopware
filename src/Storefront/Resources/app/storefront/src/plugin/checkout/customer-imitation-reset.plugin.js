import Plugin from 'src/plugin-system/plugin.class';
import Storage from 'src/helper/storage/storage.helper';

/**
 * Removes persisted checkout form entries during customer imitation
 * to prevent customer-specific data (e.g. comments) from leaking between sessions.
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
