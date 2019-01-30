export default class HttpClient {
    constructor(accessKey, contextToken) {
        this._accessKey = accessKey;
        this._contextToken = contextToken;
    }

    get accessKey() {
        return this._accessKey;
    }

    get contextToken() {
        return this._contextToken;
    }

    get(url, callback) {
        const requestUrl = `/storefront-api/v1/${url}`;
        const request = new window.XMLHttpRequest();

        request.open('GET', requestUrl);
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.setRequestHeader('x-sw-access-key', this.accessKey);
        request.setRequestHeader('x-sw-context-token', this.contextToken);

        request.addEventListener('loadend', function() {
            callback(JSON.parse(request.responseText));
        });

        request.send();
    }
}