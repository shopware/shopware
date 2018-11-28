import EntityStore from 'src/core/data/EntityStore';
import EntityProxy from 'src/core/data/EntityProxy';
import stringUtil from 'src/core/service/utils/string.utils';

export default function initializeEntities(container) {
    const httpClient = container.httpClient;
    const factoryContainer = this.getContainer('factory');
    const entityFactory = factoryContainer.entity;
    const stateFactory = factoryContainer.state;

    return httpClient.get('_info/entity-schema.json').then((response) => {
        Object.keys(response.data).forEach((entityName) => {
            entityFactory.addEntityDefinition(entityName, response.data[entityName]);

            const store = new EntityStore(entityName, `${stringUtil.camelCase(entityName)}Service`, EntityProxy);
            stateFactory.registerStore(entityName, store);
        });

        return entityFactory;
    });
}
