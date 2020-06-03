import CriteriaFactory from 'src/core/factory/criteria.factory';
import ApiService from '../api.service';

/**
 * @deprecated tag:v6.4.0 - use src/core/data-new/repository.data.js for handling entity data
 *
 * Gateway for the API end point "custom-field-set"
 * @class
 * @extends ApiService
 */
class CustomFieldSetApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'custom-field-set') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'customFieldSetService';
    }

    /**
     * @deprecated tag:v6.4.0 - use src/core/data-new/repository.data.js search() function instead
     */
    getList(options, onlyActive = true) {
        this.showDeprecationWarning('getList');
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
