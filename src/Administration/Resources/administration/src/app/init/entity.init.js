export default function initializeEntities(container) {
    const httpClient = container.httpClient;
    const factoryContainer = this.getContainer('factory');
    const entityFactory = factoryContainer.entity;

    return httpClient.get('entity-schema.json').then((response) => {
        Object.keys(response.data).forEach((entityName) => {
            entityFactory.addEntityDefinition(entityName, response.data[entityName]);
        });

        return entityFactory;
    });
}
