export default function initializeEntities(container) {
    const loginService = Shopware.Service('loginService');
    const factoryContainer = this.getContainer('factory');
    const httpClient = container.httpClient;
    const entityFactory = factoryContainer.entity;

    /**
     * Registers the provided entity definition to the associated entity factory.
     * @param {String} entityName
     * @param {Object} entityDefinition
     */
    function registerEntityDefinition(entityName, entityDefinition) {
        entityFactory.addEntityDefinition(entityName, entityDefinition);
    }

    return httpClient.get('_info/open-api-schema.json', {
        headers: {
            Authorization: `Bearer ${loginService.getToken()}`,
        },
    }).then(({ data }) => {
        Object.keys(data).forEach((entityName) => {
            const entityDefinition = data[entityName];

            registerEntityDefinition(entityName, entityDefinition);
        });
    });
}
