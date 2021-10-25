import Repository from './repository.data';

export default class RepositoryFactory {
    private hydrator: $TSFixMe;

    private changesetGenerator: $TSFixMe;

    private entityFactory: $TSFixMe;

    private httpClient: $TSFixMe;

    private errorResolver: $TSFixMe;

    constructor(
        hydrator: $TSFixMe,
        changesetGenerator: $TSFixMe,
        entityFactory: $TSFixMe,
        httpClient: $TSFixMe,
        errorResolver: $TSFixMe,
    ) {
        /* eslint-disable @typescript-eslint/no-unsafe-assignment */
        this.hydrator = hydrator;
        this.changesetGenerator = changesetGenerator;
        this.entityFactory = entityFactory;
        this.httpClient = httpClient;
        this.errorResolver = errorResolver;
        /* eslint-enable @typescript-eslint/no-unsafe-assignment */
    }

    /**
     * Creates a repository for the provided entity.
     * The route parameter allows to configure a custom route for the entity - used for association loading.
     */
    create(entityName: string, route = '', options = {}): Repository {
        if (!route) {
            route = `/${entityName.replace(/_/g, '-')}`;
        }

        const definition = Shopware.EntityDefinition.get(entityName);

        return new Repository(
            route,
            // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
            definition.entity,
            // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
            this.httpClient,
            this.hydrator,
            this.changesetGenerator,
            this.entityFactory,
            this.errorResolver,
            options,
        );
    }
}
