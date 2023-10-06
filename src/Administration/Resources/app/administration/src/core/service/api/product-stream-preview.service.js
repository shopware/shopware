import ApiService from '../api.service';

/**
 * @private
 * @package business-ops
 */
export default class ProductStreamPreviewService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, null, 'application/json');
        this.name = 'productStreamPreviewService';
    }

    /**
     * @param salesChannelId: String
     * @param criteria: Criteria
     * @param filter: Array
     * @param additionalHeaders: Object
     * @returns {*} - ApiService.handleResponse(response)
     */
    preview(salesChannelId, criteria, filter, additionalHeaders = {}) {
        return this.httpClient.post(
            `_admin/product-stream-preview/${salesChannelId}`,
            { ...criteria, ...{ filter } },
            {
                headers: this.getBasicHeaders(additionalHeaders),
            },
        ).then(response => ApiService.handleResponse(response));
    }
}
