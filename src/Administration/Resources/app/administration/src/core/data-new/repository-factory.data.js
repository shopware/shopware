import Repository from './repository.data';

export default class RepositoryFactory {
    constructor(hydrator, changesetGenerator, entityFactory, httpClient, errorResolver) {
        this.hydrator = hydrator;
        this.changesetGenerator = changesetGenerator;
        this.entityFactory = entityFactory;
        this.httpClient = httpClient;
        this.errorResolver = errorResolver;
    }

    /**
     * Creates a repository for the provided entity.
     * The route parameter allows to configure a custom route for the entity - used for association loading.
     *
     * @param {String} entityName
     * @param {String|null} route
     * @returns {Repository}
     */
    create(entityName, route) {
        if (!route) {
            route = `/${entityName.replace(/_/g, '-')}`;
        }

        const definition = Shopware.EntityDefinition.get(entityName);
        return new Repository(
            route,
            definition.entity,
            this.httpClient,
            this.hydrator,
            this.changesetGenerator,
            this.entityFactory,
            this.errorResolver
        );
    }
}
