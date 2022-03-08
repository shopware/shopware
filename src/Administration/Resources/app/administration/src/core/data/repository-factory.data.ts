import { AxiosInstance } from 'axios';
import Repository from './repository.data';
import EntityHydrator from './entity-hydrator.data';
import ChangesetGenerator from './changeset-generator.data';
import EntityFactory from './entity-factory.data';
import ErrorResolver from './error-resolver.data';

export default class RepositoryFactory {
    private hydrator: EntityHydrator;

    private changesetGenerator: ChangesetGenerator;

    private entityFactory: EntityFactory;

    private httpClient: AxiosInstance;

    private errorResolver: ErrorResolver;

    constructor(
        hydrator: EntityHydrator,
        changesetGenerator: ChangesetGenerator,
        entityFactory: EntityFactory,
        httpClient: AxiosInstance,
        errorResolver: ErrorResolver,
    ) {
        this.hydrator = hydrator;
        this.changesetGenerator = changesetGenerator;
        this.entityFactory = entityFactory;
        this.httpClient = httpClient;
        this.errorResolver = errorResolver;
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
