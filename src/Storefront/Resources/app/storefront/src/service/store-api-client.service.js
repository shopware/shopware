import HttpClient from './http-client.service';

export default class StoreApiClient extends HttpClient {

    constructor() {
        super();
        this._proxyUrl = window.router['frontend.store-api.proxy'];
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

        if (url === this._generateUrl) {
            this._request.open(type, url);
        } else {
            this._request.open(type, this._proxyUrl + '?path=' + encodeURIComponent(url));
        }
        this._request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        if (contentType) {
            this._request.setRequestHeader('Content-type', contentType);
        }

        return this._request;
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
        if (csrfProtected && this._csrfEnabled && this._csrfMode !== 'ajax') {
            if (data instanceof FormData) {
                data.append('_csrf_token', window.storeApiProxyToken);
            } else {
                data = JSON.parse(data);
                data['_csrf_token'] = window.storeApiProxyToken;
                data = JSON.stringify(data);
            }
        }

        return super.post(url, data, callback, contentType, csrfProtected);
    }
}
