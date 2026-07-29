import { deepMergeObject } from 'src/core/service/utils/object.utils';
import ApiService from '../api.service';

/**
 * @private
 * @sw-package inventory
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
     * @param {boolean} displayAsGroup
     *
     * @returns Object
     */
    preview(salesChannelId, criteria, filter, additionalHeaders = {}, displayAsGroup = true) {
        const body = deepMergeObject(criteria.parse(), {
            filter,
        });

        return this.httpClient
            .post(`_admin/product-stream-preview/${salesChannelId}`, body, {
                headers: this.getBasicHeaders(additionalHeaders),
                // controller mirrors the storefront's variant grouping when it is enabled.
                params: { displayAsGroup },
            })
            .then((response) => ApiService.handleResponse(response));
    }
}
