export default class HttpClient {

    /**
     * Constructor.
     * @param {string} accessKey
     * @param {string} contextToken
     */
    constructor(accessKey, contextToken) {
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

        request.addEventListener('loadend', function() {
            callback(request.responseText);
        });

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

        request.addEventListener('loadend', function() {
            callback(request.responseText);
        });

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

        request.addEventListener('loadend', function() {
            callback(request.responseText);
        });

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

        request.addEventListener('loadend', function() {
            callback(request.responseText);
        });

        request.send();
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
        const request = new XMLHttpRequest();

        request.open(type, url);
        request.setRequestHeader('Content-type', 'application/json');
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.setRequestHeader('sw-access-key', this.accessKey);
        request.setRequestHeader('sw-context-token', this.contextToken);

        return request;
    }
}
