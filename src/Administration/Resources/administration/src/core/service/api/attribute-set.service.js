import CriteriaFactory from 'src/core/factory/criteria.factory';
import ApiService from '../api.service';

/**
 * Gateway for the API end point "attribute-set"
 * @class
 * @extends ApiService
 */
class AttributeSetApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'attribute-set') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'attributeSetService';
    }

    getList(options, onlyActive = true) {
        if (onlyActive) {
            const activeCriteria = CriteriaFactory.equals('active', true);
            options.criteria = options.criteria
                ? CriteriaFactory.multi('AND', options.criteria, activeCriteria)
                : activeCriteria;

            const { associations: { attributes } } = options;
            if (attributes && !attributes.filter) {
                attributes.filter = [activeCriteria.getQuery()];
            }
        }

        return super.getList(options);
    }
}

export default AttributeSetApiService;
