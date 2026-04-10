import Plugin from 'src/plugin-system/plugin.class';
import Storage from 'src/helper/storage/storage.helper';

/**
 * Persists checkout customer comments per customer so drafts do not leak
 * across account switches on the same device.
 *
 * @sw-package checkout
 */
export default class CheckoutCustomerCommentStoragePlugin extends Plugin {
    static options = {
        customerId: null,
        storageKey: 'checkoutCustomerComment',
    };

    init() {
        if (!this.options.customerId) {
            return;
        }

        this._form = this.el.form ?? document.getElementById(this.el.getAttribute('form'));

        if (!this._form) {
            return;
        }

        this._onFormElementChange = this._onFormElementChange.bind(this);
        this._clearCurrentCustomerComment = this._clearCurrentCustomerComment.bind(this);

        this._restoreCurrentCustomerComment();
        this._registerEvents();
    }

    _registerEvents() {
        this.el.addEventListener('input', this._onFormElementChange);
        this.el.addEventListener('change', this._onFormElementChange);
        this._form.addEventListener('submit', this._clearCurrentCustomerComment);
        this._form.addEventListener('reset', this._clearCurrentCustomerComment);
    }

    _restoreCurrentCustomerComment() {
        const storedComments = this._getStoredComments();
        const storedComment = storedComments[this.options.customerId];

        if (typeof storedComment === 'string') {
            this.el.value = storedComment;
        }
    }

    _onFormElementChange(event) {
        const comment = event.target.value;
        const storedComments = this._getStoredComments();

        if (comment === '') {
            delete storedComments[this.options.customerId];
        } else {
            storedComments[this.options.customerId] = comment;
        }

        this._setStoredComments(storedComments);
    }

    _clearCurrentCustomerComment() {
        const storedComments = this._getStoredComments();

        if (!(this.options.customerId in storedComments)) {
            return;
        }

        delete storedComments[this.options.customerId];
        this._setStoredComments(storedComments);
    }

    _getStoredComments() {
        const storedValue = Storage.getItem(this.options.storageKey);

        if (typeof storedValue !== 'string' || storedValue === '') {
            return {};
        }

        try {
            const parsedValue = JSON.parse(storedValue);

            if (parsedValue && typeof parsedValue === 'object' && !Array.isArray(parsedValue)) {
                return parsedValue;
            }
        } catch (error) {
            return {};
        }

        return {};
    }

    _setStoredComments(storedComments) {
        if (Object.keys(storedComments).length === 0) {
            Storage.removeItem(this.options.storageKey);

            return;
        }

        Storage.setItem(this.options.storageKey, JSON.stringify(storedComments));
    }
}
