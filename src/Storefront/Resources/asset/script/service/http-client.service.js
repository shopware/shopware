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
     * @param {string} url
     * @param {function} callback
     */
    get(url, callback) {
        const request = this._createPreparedRequest('GET', url);

        request.addEventListener('loadend', function() {
            callback(request.responseText);
        });

        request.send();
    }

    /**
     * Request POST
     * @param {string} url
     * @param {object} data
     * @param {function} callback
     */
    post(url, data, callback) {
        const request = this._createPreparedRequest('POST', url);

        request.addEventListener('loadend', function() {
            callback(request.responseText);
        });

        request.send(data);
    }

    /**
     * Request DELETE
     * @param {string} url
     * @param {function} callback
     */
    delete(url, callback) {
        const request = this._createPreparedRequest('DELETE', url);

        request.addEventListener('loadend', function() {
            callback(request.responseText);
        });

        request.send();
    }

    /**
     * Request PATCH
     * @param {string} url
     * @param {function} callback
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
        request.setRequestHeader('x-sw-access-key', this.accessKey);
        request.setRequestHeader('x-sw-context-token', this.contextToken);

        return request;
    }
}
