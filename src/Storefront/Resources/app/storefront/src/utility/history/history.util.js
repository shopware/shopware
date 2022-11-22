import { createBrowserHistory } from 'history';
import querystring from 'query-string';
import deepmerge from 'deepmerge';

/**
 * @package storefront
 */
class HistoryUtilSingleton {
    constructor() {
        this._history = createBrowserHistory();
    }

    /**
     * returns the current location
     */
    getLocation() {
        return this._history.location;
    }

    /**
     * listens to the history state
     * returns the unlisten function
     *
     * @param {function} cb
     * @returns {*|void|History}
     */
    listen(cb) {
        return this._history.listen(cb);
    }

    /**
     * simply executes an unlistener
     * returned from this.listen()
     *
     * @param {function} listener
     */
    unlisten(listener) {
        listener();
    }

    /**
     * update the history
     *
     * @param {string} pathname
     * @param {*} search
     * @param {*} state
     */
    push(pathname, search, state) {
        this._history.push({ pathname, search, state });
    }

    /**
     * replace the history
     *
     * @param {string} pathname
     * @param {*} search
     * @param {*} state
     */
    replace(pathname, search, state) {
        this._history.replace({ pathname, search, state });
    }

    /**
     * updates the history with the passed params
     *
     * @param {*} params
     * @param {*} state
     */
    pushParams(params, state) {
        const pathname = this.getLocation().pathname;
        const parsed = querystring.parse(location.search);
        const search = querystring.stringify(deepmerge(parsed, params));

        this.push(pathname, search, state);
    }

    /**
     * replaces the history with the passed params
     *
     * @param {*} params
     * @param {*} state
     */
    replaceParams(params, state) {
        const pathname = this.getLocation().pathname;
        const parsed = querystring.parse(location.search);
        const search = querystring.stringify(deepmerge(parsed, params));

        this.replace(pathname, search, state);
    }

    getSearch() {
        return this._history.location.search;
    }
}

/**
 * Create the HistoryUtil instance.
 * @type {Readonly<HistoryUtilSingleton>}
 */
export const HistoryUtilInstance = Object.freeze(new HistoryUtilSingleton());

export default class HistoryUtil {

    /**
     * returns the current location
     */
    static getLocation() {
        return HistoryUtilInstance.getLocation();
    }

    /**
     * listens to the history state
     * returns the unlisten function
     *
     * @param {function} cb
     * @returns {*|void|History}
     */
    static listen(cb) {
        HistoryUtilInstance.listen(cb);
    }

    /**
     * simply executes an unlistener
     * returned from this.listen()
     *
     * @param listener
     */
    static unlisten(listener) {
        HistoryUtilInstance.unlisten(listener);
    }

    /**
     * update the history
     *
     * @param {string} path
     * @param {*} params
     * @param {*} state
     */
    static push(path, params, state) {
        HistoryUtilInstance.push(path, params, state);
    }

    /**
     * replace the history
     *
     * @param {string} path
     * @param {*} state
     */
    static replace(path, state) {
        HistoryUtilInstance.replace(path, state);
    }

    /**
     * push the history with the passed params
     *
     * @param {*} params
     * @param {*} state
     */
    static pushParams(params, state) {
        HistoryUtilInstance.pushParams(params, state);
    }

    /**
     * replaces the history with the passed params
     *
     * @param {*} params
     * @param {*} state
     */
    static replaceParams(params, state) {
        HistoryUtilInstance.replaceParams(params, state);
    }

    /**
     * Returns the history search parameter
     *
     * @returns {*}
     */
    static getSearch() {
        return HistoryUtilInstance.getSearch();
    }
}
