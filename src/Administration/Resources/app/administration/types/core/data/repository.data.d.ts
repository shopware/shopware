import { AxiosInstance, AxiosResponse } from 'axios';
import { EntityHydrator } from './entity-hydrator.data';
import { ChangesetGenerator } from './changeset-generator.data';
import { EntityFactory } from './entity-factory.data';
import { ErrorResolver } from './error-resolver.data';
import { Criteria } from './criteria.data.';
import { Context } from '../service/login.service';
import { EntityCollection } from './entity-collection.data';
import { Entity } from './entity.data';

export interface RepositoryOptionsDefinition {
    compatibility?: boolean;
    version: string;
}

export class Repository {
    constructor(
        route: string,
        entityName: string,
        httpClient: AxiosInstance,
        hydrator: EntityHydrator,
        changesetGenerator: ChangesetGenerator,
        entityFactory: EntityFactory,
        errorResolver: ErrorResolver,
        options: RepositoryOptionsDefinition
    );

    get schema(): any;

    searchIds(criteria: Criteria, context?: Context): any;

    search(criteria: Criteria, context?: Context): EntityCollection;

    get(id: string, context: Context, criteria: Criteria): Entity;

    save(entity: Entity, context?: Context): Promise<any>;

    clone(entityId: string, context: Context, behavior: object): any;

    hasChanges(entity: Entity): boolean;

    saveAll(entities: Entity[], context?: Context): Promise<any[]>;

    sync(
        entities: Entity[],
        context?: Context,
        failOnError?: boolean
    ): Promise<any>;

    discard(entity: Entity): void;

    sendUpserts(
        changeset: any,
        failOnError: boolean,
        context?: Context
    ): Promise<any>;

    getSyncErrors(errorResponse: object): object[];

    getSyncChangeset(
        entities: Entity[]
    ): {
        changeset: object[];
        deletions: object[];
    };

    assign(id: string, context?: Context): AxiosResponse;

    delete(id: string, context?: Context): AxiosResponse;

    iterateIds(
        criteria: Criteria,
        callback: () => void,
        context?: Context
    ): any;

    syncDeleted(ids: string[], context?: Context): Promise<void>;

    create(context: Context, id: string | null): Entity;

    createVersion(
        entityId: string,
        context?: Context,
        versionId?: string | null,
        versionName?: string | null
    ): any;

    mergeVersion(versionId: string, context?: Context): AxiosResponse;

    deleteVersion(
        entityId: string,
        versionId: string,
        context?: Context
    ): AxiosResponse;

    sendChanges(entity: Entity, changes: any, context?: Context): AxiosResponse;

    sendDeletions(queue: object[], context?: Context): Promise<any[]>;

    buildHeaders(
        context?: any
    ): {
        Accept: string;
        Authorization: string;
        'Content-Type': string;
        'sw-api-compatibility': string | number;
    };
}
