import EntityHydrator from 'src/core/data-new/entity-hydrator.data';
import ChangesetGenerator from 'src/core/data-new/changeset-generator.data';
import EntityFactory from 'src/core/data-new/entity-factory.data';
import RepositoryFactory from 'src/core/data-new/repository-factory.data';

export default function initializeRepositoryFactory(container) {
    const httpClient = container.httpClient;

    return httpClient.get('_info/entity-schema.json').then((schema) => {
        const hydrator = new EntityHydrator(schema.data);
        const changesetGenerator = new ChangesetGenerator(schema.data);
        const entityFactory = new EntityFactory(schema.data);

        this.addServiceProvider('repositoryFactory', () => {
            return new RepositoryFactory(hydrator, changesetGenerator, entityFactory, schema.data, httpClient);
        });
        this.addServiceProvider('entityHydrator', () => {
            return hydrator;
        });
        this.addServiceProvider('entityFactory', () => {
            return entityFactory;
        });
    });
}
