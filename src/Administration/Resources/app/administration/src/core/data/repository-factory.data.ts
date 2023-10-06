/**
 * @package admin
 */

import type { AxiosInstance } from 'axios';
import Repository from './repository.data';
import type EntityHydrator from './entity-hydrator.data';
import type ChangesetGenerator from './changeset-generator.data';
import type EntityFactory from './entity-factory.data';
import type ErrorResolver from './error-resolver.data';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
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
    create<EntityName extends keyof EntitySchema.Entities>(
        entityName: EntityName,
        route = '',
        options = {},
    ): Repository<EntityName> {
        if (!route) {
            route = `/${entityName.replace(/_/g, '-')}`;
        }

        const definition = Shopware.EntityDefinition.get(entityName);

        return new Repository<EntityName>(
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
