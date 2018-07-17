/**
 * @module core/data/ErrorStore
 */
import { State } from 'src/core/shopware';
import utils from 'src/core/service/util.service';

class ErrorStore {
    constructor() {
        this.errors = {
            system: []
        };

        this.formErrors = {};

        this.errorTemplate = {
            code: 0,
            type: '',
            title: '',
            detail: '',
            id: null,
            propertyDepth: [],
            propertyPath: '',
            status: ''
        };
    }

    /**
     * Registers the data binding of a field component to automatically match new errors.
     *
     * @param {String} expression
     * @return {*}
     */
    registerFormField(expression) {
        if (!this.formErrors[expression]) {
            this.formErrors[expression] = Object.assign({}, this.errorTemplate);
        }

        return this.formErrors[expression];
    }

    /**
     * Add a new error to the store.
     *
     * @param {Object} payload
     * @return {boolean}
     */
    addError(payload) {
        if (!payload.error) {
            return false;
        }

        const error = payload.error;
        const type = payload.type || 'system';

        error.id = utils.createId();
        error.type = type;

        if (!this.errors[type]) {
            this.errors[type] = {};
        }

        if (type !== 'system' && error.source && error.source.pointer) {
            error.propertyDepth = error.source.pointer.split('/');
            error.propertyPath = `${type}${error.propertyDepth.join('.')}`;

            if (typeof this.formErrors[error.propertyPath] !== 'undefined') {
                Object.assign(this.formErrors[error.propertyPath], error);
            }

            error.propertyDepth.reduce((obj, key, i) => {
                if (!key.length || key.length <= 0) {
                    return obj;
                }

                obj[key] = (i === error.propertyDepth.length - 1) ? error : {};

                return obj[key];
            }, this.errors[type]);
        } else {
            this.errors.system.push(error);

            /**
             * System errors will trigger a notification to display the error.
             */
            State.getStore('notification').createNotification({
                variant: 'error',
                title: error.title,
                message: error.detail
            });
        }

        return error;
    }

    /**
     * Remove an error from the store.
     *
     * @param {Object} error
     * @return {boolean}
     */
    deleteError(error) {
        if (!error || !error.type) {
            return false;
        }

        if (typeof this.formErrors[error.propertyPath] !== 'undefined') {
            Object.assign(this.formErrors[error.propertyPath], this.errorTemplate);
        }

        if (error.type === 'system') {
            this.errors.system = this.errors.system.filter((item) => {
                return item.id !== error.id;
            });
        } else {
            error.propertyDepth.reduce((obj, key, index) => {
                if (!key.length || key.length <= 0) {
                    return obj;
                }

                if (index === error.propertyDepth.length - 1 && obj[key]) {
                    delete obj[key];
                }

                return (obj !== null && obj[key]) ? obj[key] : null;
            }, this.errors[error.type]);
        }

        return true;
    }
}

export default ErrorStore;
