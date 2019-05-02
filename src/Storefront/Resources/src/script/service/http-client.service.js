export default class HttpClient {

    /**
     * Constructor.
     * @param {string} accessKey
     * @param {string} contextToken
     */
    constructor(accessKey, contextToken) {
        this._request = null;
        this._accessKey = accessKey;
        this._contextToken = contextToken;
    }

    /**
     * @returns {string}
     */
    get accessKey() {
        return this._accessKey;
    }

    /**
     * @returns {string}
     */
    get contextToken() {
        return this._contextToken;
    }

    /**
     * Request GET
     *
     * @param {string} url
     * @param {function} callback
     *
     * @returns {XMLHttpRequest}
     */
    get(url, callback) {
        const request = this._createPreparedRequest('GET', url);
        this._registerOnLoaded(request, callback);
        request.send();
        return request;
    }

    /**
     * Request POST
     *
     * @param {string} url
     * @param {object} data
     * @param {function} callback
     *
     * @returns {XMLHttpRequest}
     */
    post(url, data, callback) {
        const request = this._createPreparedRequest('POST', url);
        this._registerOnLoaded(request, callback);
        request.send(data);
        return request;
    }

    /**
     * Request DELETE
     *
     * @param {string} url
     * @param {function} callback
     *
     * @returns {XMLHttpRequest}
     */
    delete(url, callback) {
        const request = this._createPreparedRequest('DELETE', url);
        this._registerOnLoaded(request, callback);
        request.send();
        return request;
    }

    /**
     * Request PATCH
     * @param {string} url
     * @param {function} callback
     *
     * @returns {XMLHttpRequest}
     */
    patch(url, callback) {
        const request = this._createPreparedRequest('PATCH', url);
        this._registerOnLoaded(request, callback);
        request.send();
        return request;
    }

    /**
     * Abort running Request
     *
     * @returns {*}
     */
    abort() {
        if (this._request) {
            return this._request.abort();
        }
    }

    /**
     * register event listener
     * which executes the given callback
     * when the request has finished
     *
     * @param request
     * @param callback
     * @private
     */
    _registerOnLoaded(request, callback) {
        request.addEventListener('loadend', event => {
            if (event.loaded > 0) callback(request.responseText);
        });
    }

    /**
     * Returns a new and configured XMLHttpRequest object which
     * is prepared to being used
     * @param {'GET'|'POST'|'DELETE'|'PATCH'} type
     * @param {string} url
     * @returns {XMLHttpRequest}
     * @private
     */
    _createPreparedRequest(type, url) {
        this._request = new XMLHttpRequest();

        this._request.open(type, url);
        this._request.setRequestHeader('Content-type', 'application/json');
        this._request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        this._request.setRequestHeader('sw-access-key', this.accessKey);
        this._request.setRequestHeader('sw-context-token', this.contextToken);

        return this._request;
    }
}
