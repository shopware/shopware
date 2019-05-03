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
     * @param {string} contentType
     *
     * @returns {XMLHttpRequest}
     */
    get(url, callback, contentType = 'application/json') {
        const request = this._createPreparedRequest('GET', url, contentType);
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
     * @param {string} contentType
     *
     * @returns {XMLHttpRequest}
     */
    post(url, data, callback, contentType = 'application/json') {

        // when sending form data,
        // the content-type has to be automatically set,
        // to use the correct content-disposition
        // https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Disposition
        if (data instanceof FormData) {
            contentType = false;
        }

        const request = this._createPreparedRequest('POST', url, contentType);
        this._registerOnLoaded(request, callback);

        request.send(data);
        return request;
    }

    /**
     * Request DELETE
     *
     * @param {string} url
     * @param {function} callback
     * @param {string} contentType
     *
     * @returns {XMLHttpRequest}
     */
    delete(url, callback, contentType = 'application/json') {
        const request = this._createPreparedRequest('DELETE', url, contentType);
        this._registerOnLoaded(request, callback);
        request.send();
        return request;
    }

    /**
     * Request PATCH
     * @param {string} url
     * @param {function} callback
     * @param {string} contentType
     *
     * @returns {XMLHttpRequest}
     */
    patch(url, callback, contentType = 'application/json') {
        const request = this._createPreparedRequest('PATCH', url, contentType);
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
     *
     * @param {'GET'|'POST'|'DELETE'|'PATCH'} type
     * @param {string} url
     * @param {string} contentType
     *
     * @returns {XMLHttpRequest}
     * @private
     */
    _createPreparedRequest(type, url, contentType) {
        this._request = new XMLHttpRequest();

        this._request.open(type, url);
        this._request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        this._request.setRequestHeader('sw-access-key', this.accessKey);
        this._request.setRequestHeader('sw-context-token', this.contextToken);

        if (contentType) {
            this._request.setRequestHeader('Content-type', contentType);
        }

        return this._request;
    }
}
