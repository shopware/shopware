export default class ProductLinkLoader {
    constructor() {
        // set dependencies
        this.syncService = Shopware.Service('syncService');
        this.httpClient = this.syncService.httpClient;
    }

    loadLinks(id) {
        // Return all existing variations from the server
        return this.httpClient.get(
            `/_action/product/${id}/links`,
            { headers: this.syncService.getBasicHeaders() }
        ).then((response) => {
            return response.data;
        });
    }
}
