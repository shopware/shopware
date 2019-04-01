import Repository from './repository.data';

export default class RepositoryFactory {
    constructor(hydrator, changesetGenerator, entityFactory, entitySchema, httpClient) {
        this.hydrator = hydrator;
        this.changesetGenerator = changesetGenerator;
        this.entityFactory = entityFactory;
        this.httpClient = httpClient;
        this.entitySchema = entitySchema;
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

        return new Repository(
            route,
            this.entitySchema[entity],
            this.httpClient,
            this.hydrator,
            this.changesetGenerator,
            this.entityFactory
        );
    }
}
