import EventEmitter from 'events';
import Criteria from './criteria.data';

export default class Repository extends EventEmitter {
    /**
     * @param {String} route
     * @param {Object} schema
     * @param {Object} httpClient
     * @param {EntityHydrator} hydrator
     * @param {ChangesetGenerator} changesetGenerator
     * @param {EntityFactory} entityFactory
     */
    constructor(route, schema, httpClient, hydrator, changesetGenerator, entityFactory) {
        super();
        this.route = route;
        this.schema = schema;
        this.httpClient = httpClient;
        this.hydrator = hydrator;
        this.changesetGenerator = changesetGenerator;
        this.entityFactory = entityFactory;
    }

    /**
     * Sends a search request to the server to find entity ids for the provided criteria.
     * @param {Criteria} criteria
     * @param {Object} context
     * @returns {Promise}
     */
    searchIds(criteria, context) {
        const headers = this.buildHeaders(context);

        this.emit('start.loading.ids', criteria, context);

        const url = `/search-ids${this.route}`;

        return this.httpClient
            .post(url, criteria.parse(), { headers })
            .then((response) => {
                const result = response.data;

                this.emit('finish.loading.ids', result);

                return result;
            });
    }

    /**
     * Sends a search request for the repository entity.
     * @param {Criteria} criteria
     * @param {Object} context
     * @returns {Promise}
     */
    search(criteria, context) {
        const headers = this.buildHeaders(context);

        this.emit('start.loading', criteria, context);

        const url = `/search${this.route}`;

        return this.httpClient
            .post(url, criteria.parse(), { headers })
            .then((response) => {
                const result = this.hydrator.hydrateSearchResult(
                    this.route,
                    this.schema.entity,
                    response,
                    context,
                    criteria
                );

                this.emit('finish.loading', result);

                return result;
            });
    }

    /**
     * Short hand to fetch a single entity from the server
     * @param {String} id
     * @param {Object} context
     * @param {Criteria} criteria
     * @returns {Promise}
     */
    get(id, context, criteria) {
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
    save(entity, context) {
        const [changes, deletionQueue] = this.changesetGenerator.generate(entity);

        this.emit('start.save', entity, context);

        return new Promise((resolve) => {
            this.sendDeletions(deletionQueue, context).then(() => {
                this.sendChanges(entity, changes, context).then(() => {
                    this.emit('finish.save', entity, context);

                    resolve();
                });
            });
        });
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
    assign(id, context) {
        const headers = this.buildHeaders(context);

        return this.httpClient.post(`${this.route}`, { id }, { headers });
    }

    /**
     * Sends a delete request for the provided id.
     * @param {String} id
     * @param {Object} context
     * @returns {Promise}
     */
    delete(id, context) {
        const headers = this.buildHeaders(context);

        this.emit('start.delete', id, context);

        const url = `${this.route}/${id}`;

        return this.httpClient.delete(url, { headers }).then(() => {
            this.emit('finish.delete', id, context);
        });
    }

    /**
     * Creates a new entity for the local schema.
     * To Many association are initialed with a collection with the corresponding remote api route
     *
     * @param {String} id
     * @param {Object} context
     * @returns {Entity}
     */
    create(id, context) {
        return this.entityFactory.create(this.schema.entity, id, context);
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
    createVersion(entityId, context, versionId, versionName) {
        const headers = this.buildHeaders(context);
        const params = {};

        if (versionId) {
            params.versionId = versionId;
        }
        if (versionName) {
            params.versionName = versionName;
        }

        this.emit('start.create.version', entityId, context, versionId, versionName);

        const url = `_action/version/${this.schema.entity}/${entityId}`;

        return this.httpClient.post(url, params, { headers }).then((response) => {
            const versionContext = { ...context, ...{ versionId: response.data.versionId } };

            this.emit('finish.create.version', entityId, versionContext, versionName);

            return versionContext;
        });
    }

    /**
     * Sends a request to the server to merge all changes of the provided version id.
     * The changes are squashed into a single change and the remaining version will be removed.
     * @param {String} versionId
     * @param {Object} context
     * @returns {Promise}
     */
    mergeVersion(versionId, context) {
        const headers = this.buildHeaders(context);

        this.emit('start.merge.version', versionId, context);

        const url = `_action/version/merge/${this.schema.entity}/${versionId}`;

        return this.httpClient.post(url, {}, { headers }).then(() => {
            this.emit('finish.merge.version', versionId, context);
        });
    }

    /**
     * Deletes the provided version from the server. All changes to this version are reverted
     * @param {String} entityId
     * @param {String} versionId
     * @param {Object} context
     * @returns {Promise}
     */
    deleteVersion(entityId, versionId, context) {
        const headers = this.buildHeaders(context);

        this.emit('start.delete.version', entityId, versionId, context);

        const url = `/_action/version/${versionId}/${this.schema.entity}/${entityId}`;

        return this.httpClient.post(url, {}, { headers }).then(() => {
            this.emit('finish.delete.version', entityId, versionId, context);
        });
    }

    /**
     * @private
     * @param {Entity} entity
     * @param {Object} changes
     * @param {Object} context
     * @returns {*}
     */
    sendChanges(entity, changes, context) {
        if (changes === null) {
            return new Promise((resolve) => {
                resolve();
            });
        }

        const headers = this.buildHeaders(context);

        if (entity.isNew()) {
            return this.httpClient.post(`${this.route}`, changes, { headers });
        }

        return this.httpClient.patch(`${this.route}/${entity.id}`, changes, { headers });
    }

    /**
     * Process the deletion queue
     * @param {Array} queue
     * @param {Object} context
     * @returns {Promise}
     */
    sendDeletions(queue, context) {
        const requests = [];

        const headers = this.buildHeaders(context);
        queue.forEach((deletion) => {
            requests.push(this.httpClient.delete(`${deletion.route}/${deletion.key}`, { headers }));
        });

        return Promise.all(requests);
    }

    /**
     * Builds the request header for read and write operations
     * @param {Object} context
     * @returns {Object}
     */
    buildHeaders(context) {
        let headers = {
            Accept: 'application/vnd.api+json',
            Authorization: `Bearer ${context.authToken.access}`,
            'Content-Type': 'application/json'
        };

        if (context.languageId) {
            headers = Object.assign(
                { 'x-sw-language-id': context.languageId },
                headers
            );
        }
        if (context.versionId) {
            headers = Object.assign(
                { 'x-sw-version-id': context.versionId },
                headers
            );
        }

        return headers;
    }
}
