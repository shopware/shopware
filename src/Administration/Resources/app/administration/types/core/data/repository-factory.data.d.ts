import { AxiosInstance } from 'axios';
import { EntityHydrator } from './entity-hydrator.data';
import { ChangesetGenerator } from './changeset-generator.data';
import { EntityFactory } from './entity-factory.data';
import { ErrorResolver } from './error-resolver.data';
import { Entity } from './entity.data';
import { Repository, RepositoryOptionsDefinition } from './repository.data';

export class RepositoryFactory {
    constructor(
        hydrator: EntityHydrator,
        changesetGenerator: ChangesetGenerator,
        entityFactory: EntityFactory,
        httpClient: AxiosInstance,
        errorResolver: ErrorResolver
    );

    create(
        entityName: string,
        route: string | null,
        options?: RepositoryOptionsDefinition
    ): Repository;
}
