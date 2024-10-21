/**
 * @package buyers-experience
 */
import SyncApiService from './sync.api.service';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class PromotionSyncApiService extends SyncApiService {
    constructor(httpClient, loginService, apiEndpoint = 'sync') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'promotionSyncService';
    }

    async loadPackagers() {
        return this.httpClient
            .get('/_action/promotion/setgroup/packager', {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return response.data;
            });
    }

    async loadSorters() {
        return this.httpClient
            .get('/_action/promotion/setgroup/sorter', {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return response.data;
            });
    }
}
