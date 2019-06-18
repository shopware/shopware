import EntityHydrator from 'src/core/data-new/entity-hydrator.data';
import ChangesetGenerator from 'src/core/data-new/changeset-generator.data';
import EntityFactory from 'src/core/data-new/entity-factory.data';
import RepositoryFactory from 'src/core/data-new/repository-factory.data';
import EntityDefinitionRegistry from 'src/core/data-new/entity-definition-registry.data';
import ErrorResolverError from 'src/core/data/error-resolver.data';

export default function initializeRepositoryFactory(container) {
    const httpClient = container.httpClient;

    return httpClient.get('_info/entity-schema.json').then(({ data }) => {
        const entityDefinitionRegistry = new EntityDefinitionRegistry(data);

        const hydrator = new EntityHydrator(entityDefinitionRegistry);
        const changesetGenerator = new ChangesetGenerator(entityDefinitionRegistry);
        const entityFactory = new EntityFactory(entityDefinitionRegistry);
        const errorResolver = new ErrorResolverError(entityDefinitionRegistry);

        this.addServiceProvider('repositoryFactory', () => {
            return new RepositoryFactory(
                hydrator,
                changesetGenerator,
                entityFactory,
                entityDefinitionRegistry,
                httpClient,
                errorResolver
            );
        });
        this.addServiceProvider('entityHydrator', () => {
            return hydrator;
        });
        this.addServiceProvider('entityFactory', () => {
            return entityFactory;
        });
    });
}
