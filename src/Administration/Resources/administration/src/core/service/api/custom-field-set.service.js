import CriteriaFactory from 'src/core/factory/criteria.factory';
import ApiService from '../api.service';

/**
 * Gateway for the API end point "custom-field-set"
 * @class
 * @extends ApiService
 */
class CustomFieldSetApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'custom-field-set') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'customFieldSetService';
    }

    getList(options, onlyActive = true) {
        if (onlyActive) {
            const activeCriteria = CriteriaFactory.equals('active', true);
            options.criteria = options.criteria
                ? CriteriaFactory.multi('AND', options.criteria, activeCriteria)
                : activeCriteria;

            if (options.associations && options.associations.customFields) {
                const { associations: { customFields } } = options;
                if (!customFields.filter) {
                    customFields.filter = [activeCriteria.getQuery()];
                }
            }
        }

        return super.getList(options);
    }
}

export default CustomFieldSetApiService;
