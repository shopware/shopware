import EventEmitter from 'events';
import SearchResult from './search-result.data';
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

    searchIds(criteria, context) {
        const headers = this._buildHeaders(context);

        this.emit('loading-ids', criteria, context);

        const url = `/search-ids${this.route}`;

        return this.httpClient
            .post(url, criteria.parse(), { headers })
            .then((response) => {
                const result = response.data;

                this.emit('loaded-ids', result);

                return result;
            });
    }

    search(criteria, context) {
        const headers = this._buildHeaders(context);

        this.emit('loading', criteria, context);

        const url = `/search${this.route}`;

        return this.httpClient
            .post(url, criteria.parse(), { headers })
            .then((response) => {
                const result = this._hydrate(criteria, context, response);

                this.emit('loaded', result);

                return result;
            });
    }

    get(id, context, criteria) {
        criteria = criteria || new Criteria();
        criteria.setIds([id]);

        return this.search(criteria, context).then((result) => {
            return result.get(id);
        });
    }

    save(entity, context) {
        const deletionQueue = [];

        const changes = this.changesetGenerator.generate(entity, deletionQueue);

        this.emit('saving', entity, context);

        return new Promise((resolve) => {
            this._sendDeletions(deletionQueue, context).then(() => {
                this._sendChanges(entity, changes, context).then(() => {
                    this.emit('saved', entity, context);

                    resolve();
                });
            });
        });
    }

    assign(id, context) {
        const headers = this._buildHeaders(context);

        return this.httpClient.post(`${this.route}`, { id }, { headers });
    }

    delete(id, context) {
        const headers = this._buildHeaders(context);

        this.emit('deleting', id, context);

        const url = `${this.route}/${id}`;

        return this.httpClient.delete(url, { headers }).then(() => {
            this.emit('deleted', id, context);
        });
    }

    create(id, context) {
        return this.entityFactory.create(this.schema.entity, id, context);
    }

    createVersion(entityId, context, versionId, versionName) {
        const headers = this._buildHeaders(context);
        const params = {};

        if (versionId) {
            params.versionId = versionId;
        }
        if (versionName) {
            params.versionName = versionName;
        }

        this.emit('version.creating', entityId, context, versionId, versionName);

        const url = `_action/version/${this.schema.entity}/${entityId}`;

        return this.httpClient.post(url, params, { headers }).then((response) => {
            const versionContext = { ...context, ...{ versionId: response.data.versionId } };

            this.emit('version.created', entityId, versionContext, versionName);

            return versionContext;
        });
    }

    mergeVersion(versionId, context) {
        const headers = this._buildHeaders(context);

        this.emit('version.merging', versionId, context);

        const url = `_action/version/merge/${this.schema.entity}/${versionId}`;

        return this.httpClient.post(url, {}, { headers }).then(() => {
            this.emit('version.merged', versionId, context);
        });
    }

    deleteVersion(entityId, versionId, context) {
        const headers = this._buildHeaders(context);

        this.emit('version.deleting', entityId, versionId, context);

        const url = `/_action/version/${versionId}/${this.schema.entity}/${entityId}`;

        return this.httpClient.post(url, {}, { headers }).then(() => {
            this.emit('version.deleted', entityId, versionId, context);
        });
    }

    _hydrate(criteria, context, response) {
        const collection = this.hydrator.hydrate(this.route, this.schema.entity, response.data, context, criteria);

        return new SearchResult(
            this.route,
            this.schema.entity,
            collection.elements,
            response.data.meta.total,
            criteria,
            context,
            response.aggregations
        );
    }

    _sendChanges(entity, changes, context) {
        if (changes === null) {
            return new Promise((resolve) => {
                resolve();
            });
        }

        const headers = this._buildHeaders(context);

        if (entity.isNew()) {
            return this.httpClient.post(`${this.route}`, changes, { headers });
        }

        return this.httpClient.patch(`${this.route}/${entity.id}`, changes, { headers });
    }

    _sendDeletions(queue, context) {
        const requests = [];

        const headers = this._buildHeaders(context);
        queue.forEach((deletion) => {
            requests.push(this.httpClient.delete(`${deletion.route}/${deletion.key}`, { headers }));
        });

        return Promise.all(requests);
    }

    _buildHeaders(context) {
        let headers = {
            Accept: 'application/vnd.api+json',
            Authorization: `Bearer ${context.authToken.access}`,
            'Content-Type': 'application/json'
        };

        if (context.versionId) {
            headers = Object.assign(
                { 'x-sw-version-id': context.versionId },
                headers
            );
        }

        return headers;
    }
}
