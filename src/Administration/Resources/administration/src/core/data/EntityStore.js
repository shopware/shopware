import { Application } from 'src/core/shopware';
import utils, { types } from 'src/core/service/util.service';
import { deepCopyObject, hasOwnProperty } from 'src/core/service/utils/object.utils';

/**
 * @module core/data/EntityStore
 */

/**
 * @class
 * @memberOf module:core/data/EntityStore
 */
export default class EntityStore {
    /**
     * @constructor
     * @memberOf module:core/data/EntityStore
     * @param {String} entityName
     * @param {String|ApiService} apiService
     * @param {EntityProxy} EntityClass
     */
    constructor(entityName, apiService, EntityClass) {
        this.entityName = entityName;
        this.EntityClass = EntityClass;

        if (types.isString(apiService)) {
            const serviceContainer = Application.getContainer('service');
            this.apiService = serviceContainer[apiService];
        } else {
            this.apiService = apiService;
        }

        this.isLoading = false;
        this.store = {};
    }

    /**
     * Returns an entity by its id synchronously.
     *
     * @memberOf module:core/data/EntityStore
     * @param {String} id
     * @param {Boolean} [force=false]
     * @return {EntityProxy}
     */
    getById(id, force = false) {
        if (this.store[id] && force !== true) {
            return this.store[id];
        }

        const entity = this.create(id);

        entity.isLoading = true;
        this.apiService.getById(id).then((response) => {
            entity.setData(response.data);
            entity.isLoading = false;
        });

        return entity;
    }

    /**
     * Returns an entity by its id asynchronously.
     *
     * @memberOf module:core/data/EntityStore
     * @param {String} id
     * @return {Promise<never> | Promise<any>}
     */
    getByIdAsync(id) {
        if (!id || !id.length) {
            return Promise.reject();
        }

        const entity = this.create(id);

        entity.isLoading = true;
        return this.apiService.getById(id).then((response) => {
            entity.setData(response.data);
            entity.isLoading = false;

            return entity;
        });
    }

    /**
     * Loads a list of entities from the server.
     *
     * @memberOf module:core/data/EntityStore
     * @param {Object} params
     * @param {Boolean} keepAssociations
     * @return {Promise}
     */
    getList(params, keepAssociations = false) {
        this.isLoading = true;

        return this.apiService.getList(params).then((response) => {
            const total = response.meta.total;
            const items = [];
            const aggregations = response.aggregations;

            this.isLoading = false;

            response.data.forEach((item) => {
                const entity = this.create(item.id);
                entity.setData(item, !keepAssociations, keepAssociations);
                items.push(entity);
            });

            return { items, total, aggregations };
        });
    }

    /**
     * Creates a new entity in the store.
     *
     * @memberOf module:core/data/EntityStore
     * @param {String} id
     * @return {EntityProxy}
     */
    create(id = utils.createId()) {
        if (this.store[id]) {
            return this.store[id];
        }

        this.store[id] = new this.EntityClass(this.entityName, this.apiService, id, this);
        return this.store[id];
    }

    /**
     * Duplicates an entity in the store.
     *
     * @memberOf module:core/data/EntityStore
     * @param {String} id
     * @return {EntityProxy}
     */
    duplicate(id) {
        const newId = utils.createId();

        this.store[newId] = new this.EntityClass(this.entityName, this.apiService, newId, this);

        if (this.store[id]) {
            const duplicateData = deepCopyObject(this.store[id].draft);
            duplicateData.id = newId;

            this.store[newId].setLocalData(duplicateData);
        }

        return this.store[newId];
    }

    /**
     * Adds an entity proxy to the store.
     *
     * @memberOf module:core/data/EntityStore
     * @param {EntityProxy} entity
     * @return {boolean}
     */
    add(entity) {
        if (!hasOwnProperty(entity, 'id')) {
            return false;
        }

        this.store[entity.id] = entity;
        return true;
    }

    /**
     * Removes an entity from the store.
     *
     * @memberOf module:core/data/EntityStore
     * @param {EntityProxy} entity
     * @return {boolean}
     */
    remove(entity) {
        if (!hasOwnProperty(entity, 'id') || !this.store[entity.id]) {
            return false;
        }

        delete this.store[entity.id];
        return true;
    }

    /**
     * Removes an entity from the store by its id.
     *
     * @memberOf module:core/data/EntityStore
     * @param {String} id
     * @return {boolean}
     */
    removeById(id) {
        if (!this.store[id]) {
            return false;
        }

        delete this.store[id];
        return true;
    }

    /**
     * Removes all entities from the store.
     *
     * @memberOf module:core/data/EntityStore
     * @return {boolean}
     */
    removeAll() {
        this.store = {};
        return true;
    }

    /**
     * Iterator method to apply on all store items.
     *
     * @memberOf module:core/data/EntityStore
     * @param {Function} iterator
     * @param {Object} scope
     * @return {EntityStore}
     */
    forEach(iterator, scope = this) {
        if (!types.isFunction(iterator)) {
            return this.store;
        }

        Object.keys(this.store).forEach((id) => {
            iterator.call(scope, this.store[id], id);
        });

        return this.store;
    }

    /**
     * Syncs all entities in the store with the server.
     *
     * @memberOf module:core/data/EntityStore
     * @param {Boolean} deletionsOnly
     * @return {Promise<any[]>}
     */
    sync(deletionsOnly = false) {
        let syncQueue = this.getDeletionQueue();

        if (deletionsOnly === false) {
            syncQueue = [...syncQueue, ...this.getUpdateQueue()];
        }

        this.isLoading = true;

        return Promise.all(syncQueue).then(() => {
            this.isLoading = false;
        });
    }

    /**
     * Get a promise queue of deleted entities.
     *
     * @memberOf module:core/data/EntityStore
     * @return {Array}
     */
    getDeletionQueue() {
        const deletionQueue = [];

        Object.keys(this.store).forEach((id) => {
            const entity = this.store[id];

            if (entity.isDeleted) {
                deletionQueue.push(new Promise((resolve, reject) => {
                    entity.delete(true)
                        .then((response) => { resolve(response); })
                        .catch((response) => { reject(response); });
                }));
            }
        });

        return deletionQueue;
    }

    /**
     * Get a promise queue of changed entities.
     *
     * @memberOf module:core/data/EntityStore
     * @return {Array}
     */
    getUpdateQueue() {
        const updateQueue = [];

        Object.keys(this.store).forEach((id) => {
            const entity = this.store[id];

            if (entity.isDeleted) {
                return;
            }

            const changes = entity.getChanges();

            if (entity.isLocal || Object.keys(changes).length > 0) {
                updateQueue.push(
                    new Promise((resolve, reject) => {
                        entity.save()
                            .then((response) => { resolve(response); })
                            .catch((response) => { reject(response); });
                    })
                );
            }
        });

        return updateQueue;
    }
}
