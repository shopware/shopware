/**
 * @module core/data/EntityStore
 */
import { Application, Entity } from 'src/core/shopware';
import utils, { types } from 'src/core/service/util.service';
import EntityProxy from './EntityProxy';

/**
 * The entity store is like a repository and contains multiple entities. It can be used to fetch a list of entities as
 * well as sync the changed entities to the remote server.
 *
 * @class
 */
class EntityStore {
    /**
     * Initializes the entity store.
     *
     * @constructor
     * @param {String} entityName
     * @param {ApiService|String} apiService
     * @param {any} [EntityClass = EntityProxy]
     */
    constructor(entityName, apiService, EntityClass = EntityProxy) {
        this.entityName = entityName;

        if (types.isString(apiService)) {
            const serviceContainer = Application.getContainer('service');
            this._apiService = serviceContainer[apiService];
        } else {
            this._apiService = apiService;
        }

        this.EntityClass = EntityClass;

        this.store = {};
        this.isLoading = false;
    }

    /**
     * Get an entity by its id.
     *
     * @param {String} id
     * @param {Object} additionalParams
     * @param {Object} additionalHeaders
     * @return {Object}
     */
    getById(id, additionalParams = {}, additionalHeaders = {}) {
        if (this.store[id]) {
            return this.store[id];
        }

        const entity = Entity.getRawEntityObject(this.entityName, true);
        entity.id = id;

        this.store[id] = new this.EntityClass(this.entityName, this.apiService, entity);
        this.store[id].$store = this;

        this.store[id].isLoading = true;
        this.apiService.getById(id, additionalParams, additionalHeaders).then((response) => {
            this.store[id].initData(response.data);
            this.store[id].isLoading = false;
        });

        return this.store[id];
    }

    /**
     * Get a list of entities.
     *
     * @param {Number} offset
     * @param {Number} limit
     * @param {String} sortBy
     * @param {String} sortDirection
     * @param {String} term
     * @param {Object} criteria
     * @return {Promise}
     */
    getList({ offset, limit, sortBy, sortDirection, term, criteria }) {
        const params = {};

        if (sortBy && sortBy.length) {
            params.sort = (sortDirection.toLowerCase() === 'asc' ? '' : '-') + sortBy;
        }

        if (term) {
            params.term = term;
        }

        if (criteria) {
            params.filter = [criteria.getQuery()];
        }

        this.isLoading = true;

        return this.apiService.getList({
            offset,
            limit,
            additionalParams: params
        }).then((response) => {
            const newItems = response.data;
            const total = response.meta.total;

            const items = newItems.map((item) => {
                if (this.store[item.id]) {
                    this.store[item.id].initData(item);
                    return this.getById(item.id);
                }

                const entity = new this.EntityClass(this.entityName, this.apiService, item);
                return this.add(entity);
            });

            // Hook for associations, when a store has a $parent property, we're filling it up with the items
            if (this.$parent && this.$type) {
                this.populateParentEntity(items);
            }

            this.isLoading = false;

            return { items, total };
        });
    }

    /**
     * Populates the parent entity with new items.
     *
     * @param {Array} items
     * @returns {Array}
     */
    populateParentEntity(items) {
        const parent = this.$parent.draft[this.$type];

        if (parent) {
            parent.splice(0, parent.length);
            parent.push(...items);
        }

        return items;
    }

    /**
     * Create a new empty local entity.
     *
     * @param {String} id
     * @param {Object} [entityData = {}]
     * @param {Boolean} [isAddition = false]
     * @return {EntityProxy}
     */
    create(id = utils.createId(), entityData = {}, isAddition = false) {
        if (typeof this.store[id] !== 'undefined') {
            return this.store[id];
        }

        const entity = Entity.getRawEntityObject(this.entityName, true);

        Object.assign(entity, entityData);
        entity.id = id;

        this.store[id] = new this.EntityClass(this.entityName, this.apiService, entity);

        this.store[id].isNew = true;
        this.store[id].isAddition = isAddition;
        this.store[id].$store = this;

        return this.store[id];
    }

    /**
     * Adds an entity to the store
     *
     * @param {EntityProxy} entity
     * @returns {Boolean|EntityProxy}
     */
    add(entity) {
        const id = entity.id;

        if (!id || !id.length) {
            return false;
        }

        entity.$store = this;

        this.store[id] = entity;
        return this.store[id];
    }

    /**
     * Adds a relationship to the store
     *
     * @param {EntityProxy} entity
     * @returns {Boolean|EntityProxy}
     */
    addAddition(entity) {
        const id = entity.id;

        if (!id || !id.length) {
            return false;
        }

        entity.$store = this;

        this.store[id] = entity;
        this.store[id].isAddition = true;

        return this.store[id];
    }

    /**
     * Removes the given record from the store
     *
     * @param {EntityProxy} entity
     * @returns {Boolean}
     */
    remove(entity) {
        const id = entity.id;

        return this.removeById(id);
    }

    /**
     * Removes the entity from the store using the given id.
     *
     * @param {String} id
     * @returns {Boolean}
     */
    removeById(id) {
        if (!this.store[id]) {
            return false;
        }

        delete this.store[id];
        return true;
    }

    /**
     * Removes all items from the store.
     *
     * @returns {Boolean}
     */
    removeAll() {
        this.store = {};

        return true;
    }

    /**
     * Sync all entities in the store to the server.
     *
     * @param {Boolean} [deletionsOnly = false]
     * @param {Array} [itemIds = []]
     * @return {Promise<T>}
     */
    sync(deletionsOnly = false, itemIds = []) {
        const syncCue = this.getSyncCue(deletionsOnly, itemIds);

        this.isLoading = true;

        return Promise.all(syncCue).then(() => {
            this.isLoading = false;
        });
    }

    /**
     * Builds up the promise cue for a sync with the server.
     *
     * @param {Boolean} [deletionsOnly = false]
     * @param {Array} [itemIds = []]
     * @return {Array}
     */
    getSyncCue(deletionsOnly = false, itemIds = []) {
        const syncCue = [];

        Object.keys(this.store).forEach((id) => {
            if (itemIds.length > 0 && !itemIds.includes(id)) {
                return;
            }

            const entry = this.getById(id);
            const changes = entry.getChanges();

            if (entry.isDeleted) {
                syncCue.push(new Promise((resolve, reject) => {
                    entry.delete(true)
                        .then((response) => {
                            resolve(response);
                        })
                        .catch((response) => {
                            reject(response);
                        });
                }));
            } else if (Object.keys(changes).length && !deletionsOnly) {
                syncCue.push(new Promise((resolve, reject) => {
                    entry.save()
                        .then((response) => {
                            resolve(response);
                        })
                        .catch((response) => {
                            reject(response);
                        });
                }));
            }
        });

        return syncCue;
    }

    /**
     * Returns the changed entity ids.
     *
     * @returns {Array<String>}
     */
    getChangedIds() {
        return Object.keys(this.store).filter((entityKey) => {
            const entity = this.store[entityKey];
            return Object.keys(entity.getChanges()).length;
        });
    }

    /**
     * Getter for the api service.
     *
     * @return {ApiService}
     */
    get apiService() {
        return this._apiService;
    }

    /**
     * Setter for the api service.
     *
     * @param {ApiService} service
     * @returns {void}
     */
    set apiService(service) {
        this._apiService = service;
    }
}

export default EntityStore;
