import HttpClient from './http-client.service';

/** 
 * @deprecated tag:v6.4.0 use storefront controller instead
 */
export default class StoreApiClient extends HttpClient {

    constructor() {
        super();
        // init internal cache
        this.keys = null;
    }

    /**
     * @param callback
     */
    _fetchAccessKey(callback) {
        // keys already fetched? prevent unnecessary ajax request
        if (this.keys !== null) {
            callback(this.keys);
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
            request.setRequestHeader('sw-access-key', keys.accessKey);
            request.setRequestHeader('sw-context-token', keys.token);
            request.send(data);
        });

        return request;
    }
}
