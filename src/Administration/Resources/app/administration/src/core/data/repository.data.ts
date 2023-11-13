/**
 * @package admin
 */

import type { AxiosInstance, AxiosResponse } from 'axios';
import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
import Criteria from './criteria.data';
import type EntityHydrator from './entity-hydrator.data';
import type ChangesetGenerator from './changeset-generator.data';
import type ErrorResolver from './error-resolver.data';
import type EntityFactory from './entity-factory.data';
import type EntityDefinition from './entity-definition.data';
import type EntityCollection from './entity-collection.data';

type options = {
    [key: string]: unknown
};

type IdSearchResult = {
    total: number,
    data: string[],
};

type DeletionQueue = {
    route: string,
    key: string,
    entity: string,
    primary: unknown,
}[]

type Changeset = {
    changes: Changeset,
    deletionQueue: DeletionQueue
};

type Operation = {
    action: string,
    payload: unknown[],
    entity: string,
};

type Error = {
    source: {
        pointer: string,
    }
};

type ErrorResponse = {
    response?: {
        data?: {
            errors?: Error[],
            data: {
                [key: string]: {
                    result: {
                        errors: Error[],
                    }[],
                }
            },
        }
    }
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class Repository<EntityName extends keyof EntitySchema.Entities> {
    route: string;

    entityName: EntityName;

    httpClient: AxiosInstance;

    hydrator: EntityHydrator;

    changesetGenerator: ChangesetGenerator;

    entityFactory: EntityFactory;

    errorResolver: ErrorResolver;

    options: options;

    constructor(
        route: string,
        entityName: EntityName,
        httpClient: AxiosInstance,
        hydrator: EntityHydrator,
        changesetGenerator: ChangesetGenerator,
        entityFactory: EntityFactory,
        errorResolver: ErrorResolver,
        options: options,
    ) {
        this.route = route;
        this.entityName = entityName;
        this.httpClient = httpClient;
        this.hydrator = hydrator;
        this.changesetGenerator = changesetGenerator;
        this.entityFactory = entityFactory;
        this.errorResolver = errorResolver;
        this.options = options;
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    get schema(): EntityDefinition<any> {
        return Shopware.EntityDefinition.get(this.entityName);
    }

    /**
     * Sends a search request to the server to find entity ids for the provided criteria.
     */
    searchIds(criteria: Criteria, context = Shopware.Context.api): Promise<IdSearchResult> {
        const headers = this.buildHeaders(context);

        const url = `/search-ids${this.route}`;

        return this.httpClient
            .post(url, criteria.parse(), { headers })
            .then((response) => {
                return response.data as IdSearchResult;
            });
    }

    /**
     * Sends a search request for the repository entity.
     */
    search(criteria: Criteria, context = Shopware.Context.api): Promise<EntityCollection<EntityName>> {
        const headers = this.buildHeaders(context);

        const url = `/search${this.route}`;

        return this.httpClient
            .post(url, criteria.parse(), { headers })
            .then((response) => {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
                return this.hydrator.hydrateSearchResult(this.route, this.entityName, response, context, criteria);
            });
    }

    /**
     * Short hand to fetch a single entity from the server
     */
    get(id: string, context = Shopware.Context.api, criteria: Criteria | null = null): Promise<Entity<EntityName> | null> {
        criteria = criteria || new Criteria(1, 1);
        criteria.setIds([id]);

        return this.search(criteria, context).then((result) => {
            return result.get(id);
        });
    }

    /**
     * Detects all entity changes and send the changes to the server.
     * If the entity is marked as new, the repository will send a POST create. Updates will be send as PATCH request.
     * Deleted associations will be send as additional request
     */
    save(entity: Entity<EntityName>, context = Shopware.Context.api): Promise<void | AxiosResponse> {
        if (this.options.useSync === true) {
            return this.saveWithSync(entity, context);
        }

        return this.saveWithRest(entity, context);
    }

    /**
     * @private
     */
    async saveWithRest(entity: Entity<EntityName>, context: apiContext): Promise<void | AxiosResponse> {
        const { changes, deletionQueue } = this.changesetGenerator.generate(entity) as Changeset;

        if (!this.options.keepApiErrors) {
            await this.errorResolver.resetApiErrors();
        }

        await this.sendDeletions(deletionQueue, context);
        return this.sendChanges(entity, changes, context);
    }

    /**
     * @private
     */
    async saveWithSync(entity: Entity<EntityName>, context: apiContext): Promise<void | AxiosResponse> {
        const { changes, deletionQueue } = this.changesetGenerator.generate(entity) as Changeset;

        if (entity.isNew()) {
            Object.assign(changes || {}, { id: entity.id });
        }

        const operations = [];

        if (deletionQueue.length > 0) {
            operations.push(...this.buildDeleteOperations(deletionQueue));
        }

        if (changes !== null) {
            operations.push({
                key: 'write',
                action: 'upsert',
                entity: entity.getEntityName(),
                payload: [changes],
            });
        }

        const headers = this.buildHeaders(context);
        headers['single-operation'] = true;

        if (operations.length <= 0) {
            return Promise.resolve();
        }

        if (!this.options.keepApiErrors) {
            await this.errorResolver.resetApiErrors();
        }


        return this.httpClient
            .post('_action/sync', operations, { headers })
            .catch((errorResponse: ErrorResponse) => {
                const errors: Error[] = [];
                const result = errorResponse?.response?.data?.errors ?? [];

                result.forEach((error) => {
                    if (error?.source?.pointer?.startsWith('/write/')) {
                        error.source.pointer = error.source.pointer.substring(6);
                        errors.push(error);
                    }
                });

                this.errorResolver.handleWriteErrors({ errors }, [{ entity, changes }]);
                throw errorResponse;
            });
    }

    /**
     * @deprecated tag:v6.6.0.0 - Default param context will be last
     * Clones an existing entity
     */
    // eslint-disable-next-line default-param-last
    clone(entityId: string, context = Shopware.Context.api, behavior: $TSDangerUnknownObject): Promise<unknown> {
        if (!entityId) {
            return Promise.reject(new Error('Missing required argument: id'));
        }

        return this.httpClient
            .post(`/_action/clone${this.route}/${entityId}`, behavior, {
                headers: this.buildHeaders(context),
            })
            .then((response) => {
                return response.data as unknown;
            });
    }

    /**
     * Detects if the entity or the relations has remaining changes which are not synchronized with the server
     */
    hasChanges(entity: Entity<EntityName>): boolean {
        const { changes, deletionQueue } = this.changesetGenerator.generate(entity) as Changeset;

        return changes !== null || deletionQueue.length > 0;
    }

    /**
     * Detects changes of all provided entities and send the changes to the server
     */
    saveAll(entities: EntityCollection<EntityName>, context = Shopware.Context.api): Promise<unknown> {
        const promises: Promise<unknown>[] = [];

        entities.forEach((entity) => {
            promises.push(this.save(entity, context));
        });

        return Promise.all(promises);
    }

    /**
     * Detects changes of all provided entities and send the changes to the server
     */
    async sync(
        entities: EntityCollection<EntityName>,
        context = Shopware.Context.api,
        failOnError = true,
    ): Promise<unknown> {
        const { changeset, deletions } = this.getSyncChangeset(entities);

        if (!this.options.keepApiErrors) {
            await this.errorResolver.resetApiErrors();
        }

        await this.sendDeletions(deletions, context);
        return this.sendUpserts(changeset, failOnError, context);
    }

    /**
     * Detects changes of the provided entity and resets its first-level attributes to its origin state
     */
    discard(entity: Entity<EntityName>): void {
        if (!entity) {
            return;
        }

        const { changes } = this.changesetGenerator.generate(entity) as Changeset;

        if (!changes) {
            return;
        }

        const origin = entity.getOrigin();

        Object.keys(changes).forEach((changedField) => {
            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            entity[changedField] = origin[changedField];
        });
    }

    /**
     * @private
     */
    sendUpserts(changeset: $TSDangerUnknownObject[], failOnError: boolean, context = Shopware.Context.api): Promise<void> {
        if (changeset.length <= 0) {
            return Promise.resolve();
        }

        const payload = changeset.map(({ changes }) => changes);
        const headers = this.buildHeaders(context);
        headers['fail-on-error'] = failOnError;

        return this.httpClient.post(
            '_action/sync',
            {
                [this.entityName]: {
                    entity: this.entityName,
                    action: 'upsert',
                    payload,
                },
            },
            { headers },
        ).then(({ data }) => {
            if ((data as { success: boolean}).success === false) {
                throw data;
            }
            return Promise.resolve();
        }).catch((errorResponse: ErrorResponse) => {
            const errors = this.getSyncErrors(errorResponse);
            this.errorResolver.handleWriteErrors(
                { errors },
                changeset,
            );
            throw errorResponse;
        });
    }

    /**
     * @private
     */
    getSyncErrors(errorResponse: ErrorResponse): Error[] {
        const errors: Error[] = errorResponse?.response?.data?.errors ?? [];

        errors.forEach((current) => {
            if (!current.source || !current.source.pointer) {
                return;
            }

            const segments = current.source.pointer.split('/');

            // remove first empty element in list
            if (segments[0] === '') {
                segments.shift();
            }
            segments.shift();

            current.source.pointer = segments.join('/');
        });

        return errors;
    }

    /**
     * @private
     */
    getSyncChangeset(
        entities: EntityCollection<EntityName>,
    ): { changeset: $TSDangerUnknownObject[], deletions: DeletionQueue } {
        return entities.reduce((acc, entity) => {
            const { changes, deletionQueue } = this.changesetGenerator.generate(entity) as Changeset;

            // @ts-expect-error
            acc.deletions.push(...deletionQueue);

            if (changes === null) {
                return acc;
            }

            const pkData = this.changesetGenerator.getPrimaryKeyData(entity);
            Object.assign(changes, pkData);

            // @ts-expect-error
            acc.changeset.push({ entity, changes });

            return acc;
        }, { changeset: [], deletions: [] });
    }

    /**
     * Sends a create request for a many to many relation. This can only be used for many to many repository
     * where the base route contains already the owner key, e.g. /product/{id}/categories
     * The provided id contains the associated entity id.
     */
    assign(id: string, context = Shopware.Context.api): Promise<AxiosResponse> {
        const headers = this.buildHeaders(context);

        return this.httpClient.post(`${this.route}`, { id }, { headers });
    }

    /**
     * Sends a delete request for the provided id.
     */
    delete(id: string, context = Shopware.Context.api): Promise<AxiosResponse> {
        const headers = this.buildHeaders(context);

        const url = `${this.route}/${id}`;
        return this.httpClient.delete(url, { headers })
            .catch((errorResponse: ErrorResponse) => {
                const errors = errorResponse?.response?.data?.errors?.map((error) => {
                    return { error, id, entityName: this.entityName };
                });

                this.errorResolver.handleDeleteError(errors);

                throw errorResponse;
            });
    }

    /**
     * Allows to iterate all ids of the provided criteria.
     */
    iterateIds(
        criteria: Criteria,
        callback: (ids: string[]) => Promise<void>,
        context = Shopware.Context.api,
    ): Promise<unknown> {
        if (criteria.getLimit() === null) {
            criteria.setLimit(50);
        }
        criteria.setTotalCountMode(1);

        return this.searchIds(criteria, context).then((response) => {
            const ids = response.data;

            if (ids.length <= 0) {
                return Promise.resolve();
            }

            return callback(ids).then(() => {
                if (ids.length < criteria.getLimit()) {
                    return Promise.resolve();
                }

                criteria.setPage(criteria.getPage() + 1);

                return this.iterateIds(criteria, callback);
            });
        });
    }

    /**
     * Sends a delete request for a set of ids
     */
    syncDeleted(ids: string[], context = Shopware.Context.api): Promise<void> {
        const headers = this.buildHeaders(context);

        headers['fail-on-error'] = true;
        const payload = ids.map((id) => {
            return { id };
        });

        return this.httpClient.post(
            '_action/sync',
            {
                [this.entityName]: {
                    entity: this.entityName,
                    action: 'delete',
                    payload,
                },
            },
            { headers },
        ).then(({ data }) => {
            if ((data as {success: boolean}).success === false) {
                throw data;
            }
            return Promise.resolve();
        }).catch((errorResponse: ErrorResponse) => {
            const syncResult = errorResponse?.response?.data?.data[this.entityName].result;
            if (!syncResult) {
                return;
            }

            const errors = syncResult.reduce((acc, currentResult, index) => {
                if (currentResult.errors) {
                    currentResult.errors.forEach((error) => {
                        acc.push({ error, entityName: this.entityName, id: ids[index] });
                    });
                }
                return acc;
            }, [] as { error: Error, entityName: string, id: string }[]);
            this.errorResolver.handleDeleteError(errors);
            throw errorResponse;
        });
    }

    /**
     * Creates a new entity for the local schema.
     * To Many association are initialed with a collection with the corresponding remote api route
     */
    create(context = Shopware.Context.api, id: string | null = null): Entity<EntityName> {
        return this.entityFactory.create(this.entityName, id, context) as unknown as Entity<EntityName>;
    }

    /**
     * Creates a new version for the provided entity id. If no version id provided, the server
     * will generate a new version id.
     * If no version name provided, the server names the new version with `draft %date%`.
     */
    createVersion(
        entityId: string,
        context = Shopware.Context.api,
        versionId: string | null = null,
        versionName: string | null = null,
    ): Promise<apiContext> {
        const headers = this.buildHeaders(context);
        const params: {
            versionId?: string,
            versionName?: string,
        } = {};

        if (versionId) {
            params.versionId = versionId;
        }
        if (versionName) {
            params.versionName = versionName;
        }

        const url = `_action/version/${this.entityName.replace(/_/g, '-')}/${entityId}`;

        return this.httpClient.post(url, params, { headers })
            .then((response: AxiosResponse<{versionId: string}>) => {
                return { ...context, ...{ versionId: response.data.versionId } };
            });
    }

    /**
     * Sends a request to the server to merge all changes of the provided version id.
     * The changes are squashed into a single change and the remaining version will be removed.
     */
    mergeVersion(versionId: string, context = Shopware.Context.api): Promise<AxiosResponse> {
        const headers = this.buildHeaders(context);

        const url = `_action/version/merge/${this.entityName.replace(/_/g, '-')}/${versionId}`;

        return this.httpClient.post(url, {}, { headers });
    }

    /**
     * Deletes the provided version from the server. All changes to this version are reverted
     */
    deleteVersion(entityId: string, versionId: string, context = Shopware.Context.api): Promise<AxiosResponse> {
        const headers = this.buildHeaders(context);

        const url = `/_action/version/${versionId}/${this.entityName.replace(/_/g, '-')}/${entityId}`;

        return this.httpClient.post(url, {}, { headers });
    }

    /**
     * @private
     */
    sendChanges(
        entity: Entity<EntityName>,
        changes: Changeset,
        context = Shopware.Context.api,
    ): Promise<AxiosResponse | void> {
        const headers = this.buildHeaders(context);

        if (entity.isNew()) {
            changes = changes || {};
            Object.assign(changes, { id: entity.id });

            return this.httpClient.post(`${this.route}`, changes, { headers })
                .catch((errorResponse: ErrorResponse) => {
                    const errors = errorResponse?.response?.data?.errors;
                    if (!errors) {
                        return;
                    }

                    this.errorResolver.handleWriteErrors({ errors }, [{ entity, changes }]);
                    throw errorResponse;
                });
        }

        if (typeof changes === 'undefined' || changes === null) {
            return Promise.resolve();
        }

        return this.httpClient.patch(`${this.route}/${entity.id}`, changes, { headers })
            .catch((errorResponse: ErrorResponse) => {
                const errors = errorResponse?.response?.data?.errors;
                if (!errors) {
                    return;
                }

                this.errorResolver.handleWriteErrors({ errors }, [{ entity, changes }]);
                throw errorResponse;
            });
    }

    /**
     * Process the deletion queue
     */
    sendDeletions(
        queue: DeletionQueue,
        context = Shopware.Context.api,
    ): Promise<AxiosResponse[]> {
        const headers = this.buildHeaders(context);
        const requests = queue.map((deletion) => {
            return this.httpClient.delete(`${deletion.route}/${deletion.key}`, { headers })
                .catch((errorResponse) => {
                    this.errorResolver.handleDeleteError(errorResponse);
                    throw errorResponse;
                });
        });

        return Promise.all(requests);
    }

    /**
     * Builds the request header for read and write operations
     */
    buildHeaders(context = Shopware.Context.api): {
        Accept: string,
        Authorization: string,
        'Content-Type': string,
        'sw-api-compatibility': boolean,
        [key: string]: string | number | boolean,
    } {
        const { hasOwnProperty } = Shopware.Utils.object;
        const compatibility = hasOwnProperty(this.options, 'compatibility') ? this.options.compatibility : true;
        const appId = hasOwnProperty(this.options, 'sw-app-integration-id') ? this.options['sw-app-integration-id'] : false;

        let headers: { [key: string]: unknown } = {
            Accept: 'application/vnd.api+json',
            // @ts-expect-error
            Authorization: `Bearer ${context.authToken.access}`,
            'Content-Type': 'application/json',
            'sw-api-compatibility': compatibility as boolean,
        };

        if (context.languageId) {
            headers = {
                'sw-language-id': context.languageId,
                ...headers,
            };
        }

        if (context.currencyId) {
            headers = {
                'sw-currency-id': context.currencyId,
                ...headers,
            };
        }

        if (context.versionId) {
            headers = {
                'sw-version-id': context.versionId,
                ...headers,
            };
        }

        if (context.inheritance) {
            headers = {
                'sw-inheritance': context.inheritance,
                ...headers,
            };
        }

        if (appId) {
            headers = {
                'sw-app-integration-id': appId,
                ...headers,
            };
        }

        return headers as {
            Accept: string,
            Authorization: string,
            'Content-Type': string,
            'sw-api-compatibility': boolean,
            [key: string]: string | number | boolean,
        };
    }

    /**
     * @private
     */
    buildDeleteOperations(deletionQueue: DeletionQueue): Operation[] {
        const grouped: {[key: string]: unknown[]} = {};

        deletionQueue.forEach((deletion) => {
            const entityName = deletion.entity;

            if (!entityName) {
                return;
            }

            if (!grouped.hasOwnProperty(entityName)) {
                grouped[entityName] = [];
            }

            grouped[entityName].push(deletion.primary);
        });

        const operations: Operation[] = [];

        Object.keys(grouped).forEach((entity) => {
            const deletions = grouped[entity];

            operations.push({
                action: 'delete',
                payload: deletions,
                entity: entity,
            });
        });

        return operations;
    }
}
