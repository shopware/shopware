import CriteriaFactory from 'src/core/factory/criteria.factory';
import ApiService from '../api.service';

/**
 * @deprecated tag:v6.4.0 - use src/core/data-new/repository.data.js for handling entity data
 *
 * Gateway for the API end point "custom-field"
 * @class
 * @extends ApiService
 */
class CustomFieldApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'custom-field') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'customFieldService';
    }

    /**
     * @deprecated tag:v6.4.0 - use src/core/data-new/repository.data.js search() function instead
     */
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
