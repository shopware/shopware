import CriteriaFactory from 'src/core/factory/criteria.factory';
import ApiService from '../api.service';

/**
 * Gateway for the API end point "attribute"
 * @class
 * @extends ApiService
 */
class AttributeApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'attribute') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'attributeService';
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

export default AttributeApiService;
