import Criteria from './criteria.data';

export default class Repository {
    /**
     * @param {String} route
     * @param {String} entityName
     * @param {Object} httpClient
     * @param {EntityHydrator} hydrator
     * @param {ChangesetGenerator} changesetGenerator
     * @param {EntityFactory} entityFactory
     * @param {ErrorResolver} errorResolver
     * @param {Object} options
     */
    constructor(
        route,
        entityName,
        httpClient,
        hydrator,
        changesetGenerator,
        entityFactory,
        errorResolver,
        options,
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

    get schema() {
        return Shopware.EntityDefinition.get(this.entityName);
    }

    /**
     * Sends a search request to the server to find entity ids for the provided criteria.
     * @param {Criteria} criteria
     * @param {Object} context
     * @returns {Promise}
     */
    searchIds(criteria, context = Shopware.Context.api) {
        const headers = this.buildHeaders(context);

        const url = `/search-ids${this.route}`;

        return this.httpClient
            .post(url, criteria.parse(), { headers, version: this.options.version })
            .then((response) => {
                return response.data;
            });
    }

    /**
     * Sends a search request for the repository entity.
     * @param {Criteria} criteria
     * @param {Object} context
     * @returns {Promise}
     */
    search(criteria, context = Shopware.Context.api) {
        const headers = this.buildHeaders(context);

        const url = `/search${this.route}`;

        return this.httpClient
            .post(url, criteria.parse(), {
                headers,
                version: this.options.version,
            })
            .then((response) => {
                return this.hydrator.hydrateSearchResult(this.route, this.entityName, response, context, criteria);
            });
    }

    /**
     * Short hand to fetch a single entity from the server
     * @param {String} id
     * @param {Object} context
     * @param {Criteria} criteria
     * @returns {Promise}
     */
    get(id, context = Shopware.Context.api, criteria) {
        criteria = criteria || new Criteria();
        criteria.setIds([id]);

        return this.search(criteria, context).then((result) => {
            return result.get(id);
        });
    }

    /**
     * Detects all entity changes and send the changes to the server.
     * If the entity is marked as new, the repository will send a POST create. Updates will be send as PATCH request.
     * Deleted associations will be send as additional request
     *
     * @param {Entity} entity
     * @param {Object} context
     * @returns {Promise<any>}
     */
    save(entity, context = Shopware.Context.api) {
        const { changes, deletionQueue } = this.changesetGenerator.generate(entity);

        return this.errorResolver.resetApiErrors()
            .then(() => this.sendDeletions(deletionQueue, context))
            .then(() => this.sendChanges(entity, changes, context));
    }

    /**
     * Clones an existing entity
     *
     * @param {String} entityId
     * @param {Object} context
     * @param {Object} behavior
     * @returns {Promise<T>}
     */
    clone(entityId, context = Shopware.Context.api, behavior) {
        if (!entityId) {
            return Promise.reject(new Error('Missing required argument: id'));
        }

        return this.httpClient
            .post(`/_action/clone${this.route}/${entityId}`, behavior, {
                headers: this.buildHeaders(context),
                version: this.options.version,
            })
            .then((response) => {
                return response.data;
            });
    }

    /**
     * Detects if the entity or the relations has remaining changes which are not synchronized with the server
     * @param {Entity} entity
     * @returns {boolean}
     */
    hasChanges(entity) {
        const { changes, deletionQueue } = this.changesetGenerator.generate(entity);

        return changes !== null || deletionQueue.length > 0;
    }

    /**
     * Detects changes of all provided entities and send the changes to the server
     *
     * @param {Array} entities
     * @param {Object} context
     * @returns {Promise<any[]>}
     */
    saveAll(entities, context = Shopware.Context.api) {
        const promises = [];

        entities.forEach((entity) => {
            promises.push(this.save(entity, context));
        });

        return Promise.all(promises);
    }

    /**
     * Detects changes of all provided entities and send the changes to the server
     *
     * @param {Array} entities
     * @param {Object} context
     * @param {Boolean} failOnError
     * @returns {Promise<any[]>}
     */
    sync(entities, context = Shopware.Context.api, failOnError = true) {
        const { changeset, deletions } = this.getSyncChangeset(entities);

        return this.errorResolver.resetApiErrors()
            .then(() => this.sendDeletions(deletions, context))
            .then(() => this.sendUpserts(changeset, failOnError, context));
    }

    /**
     * Detects changes of the provided entity and resets its first-level attributes to its origin state
     *
     * @param {Object} entity
     */
    discard(entity) {
        if (!entity) {
            return;
        }

        const { changes } = this.changesetGenerator.generate(entity);

        if (!changes) {
            return;
        }

        const origin = entity.getOrigin();

        Object.keys(changes).forEach((changedField) => {
            entity[changedField] = origin[changedField];
        });
    }

    /**
     * @private
     * @param changeset
     * @param failOnError
     * @param context
     * @returns {*}
     */
    sendUpserts(changeset, failOnError, context = Shopware.Context.api) {
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
            { headers, version: this.options.version },
        ).then(({ data }) => {
            if (data.success === false) {
                throw data;
            }
            return Promise.resolve();
        }).catch((errorResponse) => {
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
     * @param errorResponse
     * @returns {Array}
     */
    getSyncErrors(errorResponse) {
        const operation = errorResponse.response.data.data[this.entityName];
        return operation.result.reduce((acc, result) => {
            acc.push(...result.errors);
            return acc;
        }, []);
    }

    /**
     * @private
     * @param entities
     * @returns {*}
     */
    getSyncChangeset(entities) {
        return entities.reduce((acc, entity) => {
            const { changes, deletionQueue } = this.changesetGenerator.generate(entity);
            acc.deletions.push(...deletionQueue);

            if (changes === null) {
                return acc;
            }

            const pkData = this.changesetGenerator.getPrimaryKeyData(entity);
            Object.assign(changes, pkData);

            acc.changeset.push({ entity, changes });

            return acc;
        }, { changeset: [], deletions: [] });
    }

    /**
     * Sends a create request for a many to many relation. This can only be used for many to many repository
     * where the base route contains already the owner key, e.g. /product/{id}/categories
     * The provided id contains the associated entity id.
     *
     * @param {String} id
     * @param {Object} context
     * @returns {Promise}
     */
    assign(id, context = Shopware.Context.api) {
        const headers = this.buildHeaders(context);

        return this.httpClient.post(`${this.route}`, { id }, { headers, version: this.options.version });
    }

    /**
     * Sends a delete request for the provided id.
     * @param {String} id
     * @param {Object} context
     * @returns {Promise}
     */
    delete(id, context = Shopware.Context.api) {
        const headers = this.buildHeaders(context);

        const url = `${this.route}/${id}`;
        return this.httpClient.delete(url, { headers, version: this.options.version })
            .catch((errorResponse) => {
                const errors = errorResponse.response.data.errors.map((error) => {
                    return { error, id, entityName: this.entityName };
                });

                this.errorResolver.handleDeleteError(errors);

                throw errorResponse;
            });
    }

    /**
     * Allows to iterate all ids of the provided criteria.
     * @param {Criteria} criteria
     * @param {function} callback
     * @param context
     * @returns {Promise}
     */
    iterateIds(criteria, callback, context = Shopware.Context.api) {
        if (criteria.limit == null) {
            criteria.setLimit(50);
        }
        criteria.setTotalCountMode(1);

        return this.searchIds(criteria, context).then((response) => {
            const ids = response.data;

            if (ids.length <= 0) {
                return Promise.resolve();
            }

            return callback(ids).then(() => {
                if (ids.length < criteria.limit) {
                    return Promise.resolve();
                }

                criteria.setPage(criteria.page + 1);

                return this.iterateIds(criteria, callback);
            });
        });
    }

    /**
     * Sends a delete request for a set of ids
     * @param {Array} ids
     * @param {Object} context
     * @returns {Promise}
     */
    syncDeleted(ids, context = Shopware.Context.api) {
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
            { headers, version: this.options.version },
        ).then(({ data }) => {
            if (data.success === false) {
                throw data;
            }
            return Promise.resolve();
        }).catch((errorResponse) => {
            const syncResult = errorResponse.response.data.data[this.entityName].result;

            const errors = syncResult.reduce((acc, currentResult, index) => {
                if (currentResult.errors) {
                    currentResult.errors.forEach((error) => {
                        acc.push({ error, entityName: this.entityName, id: ids[index] });
                    });
                }
                return acc;
            }, []);
            this.errorResolver.handleDeleteError(errors);
            throw errorResponse;
        });
    }

    /**
     * Creates a new entity for the local schema.
     * To Many association are initialed with a collection with the corresponding remote api route
     *
     * @param {Object} context
     * @param {String|null} id
     * @returns {Entity}
     */
    create(context = Shopware.Context.api, id) {
        return this.entityFactory.create(this.entityName, id, context);
    }

    /**
     * Creates a new version for the provided entity id. If no version id provided, the server
     * will generate a new version id.
     * If no version name provided, the server names the new version with `draft %date%`.
     *
     * @param {string} entityId
     * @param {Object} context
     * @param {String|null} versionId
     * @param {String|null} versionName
     * @returns {Promise}
     */
    createVersion(entityId, context = Shopware.Context.api, versionId = null, versionName = null) {
        const headers = this.buildHeaders(context);
        const params = {};

        if (versionId) {
            params.versionId = versionId;
        }
        if (versionName) {
            params.versionName = versionName;
        }

        const url = `_action/version/${this.entityName.replace(/_/g, '-')}/${entityId}`;

        return this.httpClient.post(url, params, { headers, version: this.options.version }).then((response) => {
            return { ...context, ...{ versionId: response.data.versionId } };
        });
    }

    /**
     * Sends a request to the server to merge all changes of the provided version id.
     * The changes are squashed into a single change and the remaining version will be removed.
     * @param {String} versionId
     * @param {Object} context
     * @returns {Promise}
     */
    mergeVersion(versionId, context = Shopware.Context.api) {
        const headers = this.buildHeaders(context);

        const url = `_action/version/merge/${this.entityName.replace(/_/g, '-')}/${versionId}`;

        return this.httpClient.post(url, {}, { headers, version: this.options.version });
    }

    /**
     * Deletes the provided version from the server. All changes to this version are reverted
     * @param {String} entityId
     * @param {String} versionId
     * @param {Object} context
     * @returns {Promise}
     */
    deleteVersion(entityId, versionId, context = Shopware.Context.api) {
        const headers = this.buildHeaders(context);

        const url = `/_action/version/${versionId}/${this.entityName.replace(/_/g, '-')}/${entityId}`;

        return this.httpClient.post(url, {}, { headers, version: this.options.version });
    }

    /**
     * @private
     * @param {Entity} entity
     * @param {Object} changes
     * @param {Object} context
     * @returns {*}
     */
    sendChanges(entity, changes, context = Shopware.Context.api) {
        const headers = this.buildHeaders(context);

        if (entity.isNew()) {
            changes = changes || {};
            Object.assign(changes, { id: entity.id });

            return this.httpClient.post(`${this.route}`, changes, { headers, version: this.options.version })
                .catch((errorResponse) => {
                    this.errorResolver.handleWriteErrors(errorResponse.response.data, [{ entity, changes }]);
                    throw errorResponse;
                });
        }

        if (typeof changes === 'undefined' || changes === null) {
            return Promise.resolve();
        }

        return this.httpClient.patch(`${this.route}/${entity.id}`, changes, { headers, version: this.options.version })
            .catch((errorResponse) => {
                this.errorResolver.handleWriteErrors(errorResponse.response.data, [{ entity, changes }]);
                throw errorResponse;
            });
    }

    /**
     * Process the deletion queue
     * @param {Array} queue
     * @param {Object} context
     * @returns {Promise}
     */
    sendDeletions(queue, context = Shopware.Context.api) {
        const headers = this.buildHeaders(context);
        const requests = queue.map((deletion) => {
            return this.httpClient.delete(`${deletion.route}/${deletion.key}`, { headers, version: this.options.version })
                .catch((errorResponse) => {
                    this.errorResolver.handleDeleteError(errorResponse);
                    throw errorResponse;
                });
        });

        return Promise.all(requests);
    }

    /**
     * Builds the request header for read and write operations
     * @param {Object} context
     * @returns {Object}
     */
    buildHeaders(context = Shopware.Context.api) {
        const { hasOwnProperty } = Shopware.Utils.object;
        const compatibility = hasOwnProperty(this.options, 'compatibility') ? this.options.compatibility : true;

        let headers = {
            Accept: 'application/vnd.api+json',
            Authorization: `Bearer ${context.authToken.access}`,
            'Content-Type': 'application/json',
            'sw-api-compatibility': compatibility,
        };

        if (context.languageId) {
            headers = Object.assign(
                { 'sw-language-id': context.languageId },
                headers,
            );
        }

        if (context.currencyId) {
            headers = Object.assign(
                { 'sw-currency-id': context.currencyId },
                headers,
            );
        }

        if (context.versionId) {
            headers = Object.assign(
                { 'sw-version-id': context.versionId },
                headers,
            );
        }

        if (context.inheritance) {
            headers = Object.assign(
                { 'sw-inheritance': context.inheritance },
                headers,
            );
        }

        return headers;
    }
}
