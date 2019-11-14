import CriteriaFactory from 'src/core/factory/criteria.factory';
import ApiService from '../api.service';

/**
 * Gateway for the API end point "custom-field"
 * @class
 * @extends ApiService
 */
class CustomFieldApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'custom-field') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'customFieldService';
    }

    getList(options, onlyActive = true) {
        if (onlyActive) {
            const activeCriteria = CriteriaFactory.equals('active', true);
            options.criteria = options.criteria
                ? CriteriaFactory.multi('AND', options.criteria, activeCriteria)
                : activeCriteria;
        }

        return super.getList(options);
    }
}

export default CustomFieldApiService;
