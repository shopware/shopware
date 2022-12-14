/**
 * @package admin
 */

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
        if (currentStore?.[next]) {
            return currentStore[next];
        }

        return null;
    }, state.api);

    if (store === null) {
        return;
    }

    if (typeof deleteReactive === 'function') {
        deleteReactive(store, field);
    } else {
        delete store.field;
    }

    if (path.length <= 0) {
        return;
    }

    if (types.isEmpty(store)) {
        removeApiError(path.join('.'), state, deleteReactive);
    }
}

/**
 * removes all api errors
 * @param state
 */
function resetApiErrors(state) {
    state.api = {};
}

/**
 * stores an system error
 * @param {ShopwareError} error
 * @param id
 * @param state
 * @param setReactive
 */
function addSystemError(error, id, state, setReactive = Object.defineProperty) {
    if (typeof setReactive !== 'function') {
        throw new Error('[ErrorStore] createApiError: setReactive is not a function');
    }

    setReactive(state.system, id, error);
}

/**
 * removes a system error by a given id
 * @param id
 * @param state
 * @param deleteReactive
 */
function removeSystemError(id, state, deleteReactive = null) {
    if (typeof deleteReactive === 'function') {
        deleteReactive(state.system, id);
    } else {
        delete state.system[id];
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    addApiError,
    removeApiError,
    resetApiErrors,
    addSystemError,
    removeSystemError,
};
