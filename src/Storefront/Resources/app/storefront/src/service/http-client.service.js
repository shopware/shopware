export default class HttpClient {

    /**
     * Constructor.
     */
    constructor() {
        this._request = null;
        this._accessKey = '';
        this._contextToken = '';
        this.keys = null;
        this._csrfEnabled = window.csrf.enabled;
        this._csrfMode = window.csrf.mode;
        this._generateUrl = window.router['frontend.csrf.generateToken'];
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
    post(url, data, callback, contentType = 'application/json', csrfProtected = true) {
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
    delete(url, data, callback, contentType = 'application/json') {
        contentType = this._getContentType(data, contentType);
        const request = this._createPreparedRequest('DELETE', url, contentType);

        return this._sendRequest(request, data, callback);
    }

    /**
     * Request PATCH
     * @param {string} url
     * @param {object|null} data
     * @param {function} callback
     * @param {string} contentType
     *
     * @returns {XMLHttpRequest}
     */
    patch(url, data, callback, contentType = 'application/json') {
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
     * register event listener
     * which executes the given callback
     * when the request has finished
     *
     * @param request
     * @param callback
     * @private
     */
    _registerOnLoaded(request, callback) {
        request.addEventListener('loadend', () => {
            callback(request.responseText);
        });
    }

    _fetchAccessKey(callback) {
        // keys already fetched? prevent unnecessary ajax request
        if (this.keys !== null) {
            callback(this.keys);
            return;
        }
        if (!window.apiAccessUrl) {
            callback(null);
            return;
        }

        const request = new XMLHttpRequest();

        request.open('GET', window.apiAccessUrl);
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.setRequestHeader('Content-type', 'application/json');
        request.send();

        // fetch api access (accessKey and context token) to add possibility to send store-api request
        request.addEventListener('loadend', () => {
            this.keys = JSON.parse(request.responseText);
            callback(this.keys);
        });
    }

    _sendRequest(request, data, callback) {
        this._registerOnLoaded(request, callback);

        this._fetchAccessKey((keys) => {
            if (keys !== null) {
                request.setRequestHeader('sw-access-key', keys.accessKey);
                request.setRequestHeader('sw-context-token', keys.token);
            }
            request.send(data);
        });

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
     * returns the appropriate content type for the request
     *
     * @param {*} data
     * @param {string} contentType
     *
     * @returns {string|boolean}
     * @private
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

        if (contentType) {
            this._request.setRequestHeader('Content-type', contentType);
        }

        return this._request;
    }
}
