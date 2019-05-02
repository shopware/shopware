import { createBrowserHistory } from 'history';
import querystring from 'query-string';
import deepmerge from 'deepmerge';

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
}

const instance = new HistoryUtilSingleton();
Object.freeze(instance);

export default class HistoryUtil {

    /**
     * returns the current location
     */
    static getLocation() {
        instance.getLocation();
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
    static listen(cb) {
        instance.listen(cb);
    }

    /**
     * simply executes an unlistener
     * returned from this.listen()
     *
     * @param listener
     */
    static unlisten(listener) {
        instance.unlisten(listener);
    }

    /**
     * update the history
     *
     * @param {string} path
     * @param {*} state
     */
    static push(path, state) {
        instance.push(path, state);
    }

    /**
     * replace the history
     *
     * @param {string} path
     * @param {*} state
     */
    static replace(path, state) {
        instance.replace(path, state);
    }

    /**
     * push the history with the passed params
     *
     * @param {*} params
     * @param {*} state
     */
    static pushParams(params, state) {
        instance.pushParams(params, state);
    }

    /**
     * replaces the history with the passed params
     *
     * @param {*} params
     * @param {*} state
     */
    static replaceParams(params, state) {
        instance.replaceParams(params, state);
    }
}
