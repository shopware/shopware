import EntityStore from 'src/core/data/EntityStore';
import EntityProxy from 'src/core/data/EntityProxy';
import stringUtil from 'src/core/service/utils/string.utils';
import ApiService from 'src/core/service/api.service';

export default function initializeEntities(container) {
    const factoryContainer = this.getContainer('factory');
    const serviceContainer = this.getContainer('service');
    const application = this;

    const httpClient = container.httpClient;

    const entityFactory = factoryContainer.entity;
    const stateFactory = factoryContainer.state;
    const apiServiceFactory = factoryContainer.apiService;
    const loginService = serviceContainer.loginService;

    /**
     * Instantiate entity store and registers an entity store to the associated state factory
     * @param {String} entityName
     * @param {ApiService} apiService
     * @returns {boolean}
     */
    function registerEntityStore(entityName, apiService) {
        const store = new EntityStore(entityName, apiService, EntityProxy);
        stateFactory.registerStore(entityName, store);
        return true;
    }

    /**
     * Registers the provided entity definition to the associated entity factory.
     * @param {String} entityName
     * @param {Object} entityDefinition
     * @returns {boolean}
     */
    function registerEntityDefinition(entityName, entityDefinition) {
        entityFactory.addEntityDefinition(entityName, entityDefinition);
        return true;
    }

    /**
     * Instantiate an api service for a entity name, if it isn't a custom service which was defined before.
     * @param {String} entityName
     * @returns {ApiService}
     */
    function registerApiService(entityName) {
        const serviceName = `${stringUtil.camelCase(entityName)}Service`;
        const kebabServiceName = entityName.replace(/_/g, '-');

        if (apiServiceFactory.has(serviceName)) {
            return apiServiceFactory.getByName(serviceName);
        }

        const apiService = new ApiService(httpClient, loginService, kebabServiceName);
        apiServiceFactory.register(serviceName, apiService);
        application.addServiceProvider(serviceName, () => {
            return apiService;
        });

        return apiService;
    }

    return httpClient.get('_info/entity-schema.json').then((response) => {
        Object.keys(response.data).forEach((entityName) => {
            const entityDefinition = response.data[entityName];

            const apiService = registerApiService(entityName);
            registerEntityDefinition(entityName, entityDefinition);
            registerEntityStore(entityName, apiService);
        });

        return entityFactory;
    });
}
