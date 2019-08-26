const EntityStore = Shopware.DataDeprecated.EntityStore;
const LanguageStore = Shopware.DataDeprecated.LanguageStore;
const EntityProxy = Shopware.DataDeprecated.EntityProxy;

export default function initializeEntities(container) {
    const factoryContainer = this.getContainer('factory');
    const serviceContainer = this.getContainer('service');
    const application = this;
    const httpClient = container.httpClient;
    const loginService = serviceContainer.loginService;
    const entityFactory = factoryContainer.entity;
    const stateFactory = factoryContainer.state;
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
        const serviceName = `${Shopware.Utils.string.camelCase(entityName)}Service`;
        const kebabServiceName = entityName.replace(/_/g, '-');

        if (apiServiceFactory.has(serviceName)) {
            return apiServiceFactory.getByName(serviceName);
        }

        const apiService = new Shopware.Classes.ApiService(httpClient, loginService, kebabServiceName);
        apiServiceFactory.register(serviceName, apiService);
        application.addServiceProvider(serviceName, () => {
            return apiService;
        });

        return apiService;
    }

    return httpClient.get('_info/open-api-schema.json').then((response) => {
        Object.keys(response.data).forEach((entityName) => {
            const entityDefinition = response.data[entityName];

            const apiService = registerApiService(entityName);
            registerEntityDefinition(entityName, entityDefinition);

            // Register custom language entity store
            if (entityName === 'language') {
                const languageStore = new LanguageStore(
                    'languageService',
                    EntityProxy,
                    languageId
                );
                stateFactory.registerStore(entityName, languageStore);
                return;
            }

            registerEntityStore(entityName, apiService);
        });

        if (loginService.isLoggedIn()) {
            // init the language store if logged in
            stateFactory.getStore('language').init();
        }

        return entityFactory;
    });
}
