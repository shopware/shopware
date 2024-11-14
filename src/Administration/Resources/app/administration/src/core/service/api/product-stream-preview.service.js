import { deepMergeObject } from 'src/core/service/utils/object.utils';
import ApiService from '../api.service';

/**
 * @private
 * @package services-settings
 */
export default class ProductStreamPreviewService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, null, 'application/json');
        this.name = 'productStreamPreviewService';
    }

    /**
     * @param {string} salesChannelId
     * @param {Criteria} criteria
     * @param {Array} filter
     * @param {Object} additionalHeaders
     *
     * @returns Object
     */
    preview(salesChannelId, criteria, filter, additionalHeaders = {}) {
        const body = deepMergeObject(criteria.parse(), {
            filter,
        });

        return this.httpClient
            .post(`_admin/product-stream-preview/${salesChannelId}`, body, {
                headers: this.getBasicHeaders(additionalHeaders),
            })
            .then((response) => ApiService.handleResponse(response));
    }
}
