export default class HttpClient {

    constructor() {
        this._request = null;
        this._accessKey = '';
        this._contextToken = '';
        this._csrfEnabled = window.csrf.enabled;
        this._csrfMode = window.csrf.mode;
        this._generateUrl = window.router['frontend.csrf.generateToken'];
    }

    /**
     * @deprecated tag:v6.4.0 - Sales channel access key is no longer available in storefront templates
     * @returns {string}
     */
    get accessKey() {
        return this._accessKey;
    }

    /**
     * @deprecated tag:v6.4.0 - Context token is no longer available in storefront templates
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

        return this._sendRequest(request, null, callback);
    }

    /**
     * Request POST
     *
     * @param {string} url
     * @param {object|null} data
     * @param {function} callback
     * @param {string} contentType
     * @param {boolean} csrfProtected
     *
     * @returns {XMLHttpRequest}
     */
    post(
        url,
        data,
        callback,
        contentType = 'application/json',
        csrfProtected = true
    ) {
        contentType = this._getContentType(data, contentType);
        const request = this._createPreparedRequest('POST', url, contentType);

        if (csrfProtected && this._csrfEnabled && this._csrfMode === 'ajax') {
            this.fetchCsrfToken((csrfToken) => {
                if (data instanceof FormData) {
                    data.append('_csrf_token', csrfToken);
                } else {
                    data = JSON.parse(data);
                    data['_csrf_token'] = csrfToken;
                    data = JSON.stringify(data);
                }

                return this._sendRequest(request, data, callback);
            });
            return request;
        }

        return this._sendRequest(request, data, callback);
    }


    /**
     * Request DELETE
     *
     * @param {string} url
     * @param {object|null} data
     * @param {function} callback
     * @param {string} contentType
     *
     * @returns {XMLHttpRequest}
     */
    delete(
        url,
        data,
        callback,
        contentType = 'application/json'
    ) {
        contentType = this._getContentType(data, contentType);
        const request = this._createPreparedRequest('DELETE', url, contentType);

        return this._sendRequest(request, data, callback);
    }

    /**
     * Request PATCH
     *
     * @param {string} url
     * @param {object|null} data
     * @param {function} callback
     * @param {string} contentType
     *
     * @returns {XMLHttpRequest}
     */
    patch(
        url,
        data,
        callback,
        contentType = 'application/json'
    ) {
        contentType = this._getContentType(data, contentType);
        const request = this._createPreparedRequest('PATCH', url, contentType);

        return this._sendRequest(request, data, callback);
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
     * @private
     * Register event listener, which executes the given callback, when the request has finished
     *
     * @param {XMLHttpRequest} request
     * @param {function} callback
     */
    _registerOnLoaded(request, callback) {
        if (!callback) {
            return;
        }

        request.addEventListener('loadend', () => {
            callback(request.responseText, request);
        });
    }

    _sendRequest(request, data, callback) {
        this._registerOnLoaded(request, callback);
        request.send(data);
        return request;
    }

    fetchCsrfToken(callback) {
        return this.post(
            this._generateUrl,
            null,
            response => callback(JSON.parse(response)['token']),
            'application/json',
            false
        );
    }

    /**
     * @private
     * Returns the appropriate content type for the request
     *
     * @param {*} data
     * @param {string} contentType
     *
     * @returns {string|boolean}
     */
    _getContentType(data, contentType) {

        // when sending form data,
        // the content-type has to be automatically set,
        // to use the correct content-disposition
        // https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Disposition
        if (data instanceof FormData) {
            contentType = false;
        }

        return contentType;
    }

    /**
     * @private
     * Returns a new and configured XMLHttpRequest object
     *
     * @param {'GET'|'POST'|'DELETE'|'PATCH'} type
     * @param {string} url
     * @param {string} contentType
     *
     * @returns {XMLHttpRequest}
     */
    _createPreparedRequest(type, url, contentType) {
        this._request = new XMLHttpRequest();

        this._request.open(type, url);
        this._request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        if (contentType) {
            this._request.setRequestHeader('Content-type', contentType);
        }

        return this._request;
    }
}
