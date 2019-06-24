import Repository from './repository.data';

export default class RepositoryFactory {
    constructor(hydrator, changesetGenerator, entityFactory, entityDefinitionRegistry, httpClient, errorResolver) {
        this.hydrator = hydrator;
        this.changesetGenerator = changesetGenerator;
        this.entityFactory = entityFactory;
        this.httpClient = httpClient;
        this.definitionRegistry = entityDefinitionRegistry;
        this.errorResolver = errorResolver;
    }

    /**
     * Creates a repository for the provided entity.
     * The route parameter allows to configure a custom route for the entity - used for association loading.
     *
     * @param {String} entity
     * @param {String|null} route
     * @returns {Repository}
     */
    create(entity, route) {
        if (!route) {
            route = `/${entity.replace(/_/g, '-')}`;
        }

        const definition = this.definitionRegistry.get(entity);
        if (!definition) {
            throw new Error(`[RepositoryFactory] No EntityDefinition found for entity with name "${entity}"`);
        }

        return new Repository(
            route,
            definition,
            this.httpClient,
            this.hydrator,
            this.changesetGenerator,
            this.entityFactory,
            this.errorResolver
        );
    }
}
