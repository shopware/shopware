import { types } from '../service/util.service';

/**
 * @module core/data/ErrorStore
 */

/**
 * Stores an Error from the api
 * @param {String} expression
 * @param {ShopwareError} error
 * @param {Object} state
 * @param {function} setReactive
 */
function addApiError(expression, error, state, setReactive = Object.defineProperty) {
    if (typeof setReactive !== 'function') {
        throw new Error('[ErrorStore] createApiError: setReactive is not a function');
    }

    const { store, field } = createPathToError(expression, state.api, setReactive);
    setReactive(store, field, error);
}

/**
 * @private
 * @param {String} expression
 * @param {Object} state
 * @param {function} setReactive
 * @returns {{store: Object, field: String}}
 */
function createPathToError(expression, state, setReactive) {
    const path = expression.split('.');
    const field = path.pop();

    const store = path.reduce((currentStore, next) => {
        if (!currentStore.hasOwnProperty(next)) {
            setReactive(currentStore, next, {});
        }
        return currentStore[next];
    }, state);

    return { store, field };
}

/**
 * Removes the error for a given
 * @param {String} expression
 * @param {Object} state
 * @param {function} deleteReactive
 */
function removeApiError(expression, state, deleteReactive = null) {
    const path = expression.split('.');
    const field = path.pop();

    const store = path.reduce((currentStore, next) => {
        if (currentStore && currentStore[next]) {
            return currentStore[next];
        }

        return null;
    }, state);

    if (typeof deleteReactive === 'function') {
        deleteReactive(store, field);
    } else {
        delete store.field;
    }

    if (types.isEmpty(store)) {
        removeApiError(path.join('.'), state, deleteReactive);
    }
}

function resetApiErrors(state) {
    state.api = {};
}

function addSystemError(error, id, setReactive = Object.defineProperty) {
    if (typeof setReactive !== 'function') {
        throw new Error('[ErrorStore] createApiError: setReactive is not a function');
    }

    setReactive(this.errors.system, id, error);
}

function removeSystemError(id, deleteReactive = null) {
    if (typeof deleteReactive === 'function') {
        deleteReactive(this.errors.system, id);
    } else {
        delete this.errors.api[id];
    }
}

export default {
    addApiError,
    removeApiError,
    resetApiErrors,
    addSystemError,
    removeSystemError
};
