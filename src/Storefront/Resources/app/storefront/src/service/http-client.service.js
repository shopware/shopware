/**
 * @package storefront
 */
export default class HttpClient {
    constructor() {
        this._request = null;
        this._errorHandlingInternal = false;
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
     *
     * @returns {XMLHttpRequest}
     */
    post(
        url,
        data,
        callback,
        contentType = 'application/json'
    ) {
        contentType = this._getContentType(data, contentType);
        const request = this._createPreparedRequest('POST', url, contentType);

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
     * Set the error handling
     *
     * @param {boolean} errorHandlingInternal
     */
    setErrorHandlingInternal(errorHandlingInternal) {
        this._errorHandlingInternal = errorHandlingInternal;
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

        if (this._errorHandlingInternal === true) {
            request.addEventListener('load', () => {
                callback(request.responseText, request);
            });
            request.addEventListener('abort', () => {
                console.warn(`the request to ${request.responseURL} was aborted`);
            });
            request.addEventListener('error', () => {
                console.warn(`the request to ${request.responseURL} failed with status ${request.status}`);
            });
            request.addEventListener('timeout', () => {
                console.warn(`the request to ${request.responseURL} timed out`);
            });
        } else {
            request.addEventListener('loadend', () => {
                callback(request.responseText, request);
            });
        }
    }

    _sendRequest(request, data, callback) {
        this._registerOnLoaded(request, callback);
        request.send(data);
        return request;
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
