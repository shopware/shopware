const EntityStore = Shopware.DataDeprecated.EntityStore;
const LanguageStore = Shopware.DataDeprecated.LanguageStore;
const EntityProxy = Shopware.DataDeprecated.EntityProxy;

export default function initializeEntities(container) {
    const factoryContainer = this.getContainer('factory');
    const httpClient = container.httpClient;
    const loginService = Shopware.Service('loginService');
    const entityFactory = factoryContainer.entity;
    const stateFactoryDeprecated = factoryContainer.stateDeprecated;
    const apiServiceFactory = factoryContainer.apiService;

    const languageId = localStorage.getItem('sw-admin-current-language');

    /**
     * Instantiate entity store and registers an entity store to the associated state factory
     * @param {String} entityName
     * @param {ApiService} apiService
     * @returns {boolean}
     */
    function registerEntityStore(entityName, apiService) {
        const store = new EntityStore(entityName, apiService, EntityProxy);
        stateFactoryDeprecated.registerStore(entityName, store);
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
        const serviceName = `${Shopware.Utils.string.camelCase(entityName)}Service`;
        const kebabServiceName = entityName.replace(/_/g, '-');

        if (apiServiceFactory.has(serviceName)) {
            return apiServiceFactory.getByName(serviceName);
        }

        const apiService = new Shopware.Classes.ApiService(httpClient, loginService, kebabServiceName);
        apiServiceFactory.register(serviceName, apiService);
        Shopware.Service().register(serviceName, () => {
            return apiService;
        });

        return apiService;
    }

    return httpClient.get('_info/open-api-schema.json', {
        headers: {
            Authorization: `Bearer ${loginService.getToken()}`
        }
    }).then(({ data }) => {
        Object.keys(data).forEach((entityName) => {
            const entityDefinition = data[entityName];

            const apiService = registerApiService(entityName);
            registerEntityDefinition(entityName, entityDefinition);

            // Register custom language entity store
            if (entityName === 'language') {
                const languageStore = new LanguageStore(
                    'languageService',
                    EntityProxy,
                    languageId
                );
                stateFactoryDeprecated.registerStore(entityName, languageStore);
                return;
            }

            registerEntityStore(entityName, apiService);
        });

        if (loginService.isLoggedIn()) {
            // init the language store if logged in
            stateFactoryDeprecated.getStore('language').init();
        }

        return entityFactory;
    });
}
