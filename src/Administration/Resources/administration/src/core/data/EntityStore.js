/**
 * @module core/data/EntityStore
 */
import { Application, Entity } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import EntityProxy from './EntityProxy';

class EntityStore {
    constructor(entityName, apiServiceName, EntityClass = EntityProxy) {
        this.entityName = entityName;
        this.apiServiceName = apiServiceName;
        this.EntityClass = EntityClass;

        this.store = {};
        this.isLoading = false;
    }

    /**
     * Get an entity by its id.
     *
     * @param {String} id
     * @return {Object}
     */
    getById(id) {
        if (this.store[id] && (this.store[id].isDetail || this.store[id].isNew)) {
            return this.store[id];
        }

        this.createEntityInStore(id);

        this.store[id].isLoading = true;
        this.apiService.getById(id).then((response) => {
            this.store[id].initData(response.data);
            this.store[id].isLoading = false;
            this.store[id].isDetail = true;
        });

        return this.store[id];
    }

    /**
     * Get A list of entities.
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

        return this.apiService.getList(offset, limit, params).then((response) => {
            const newItems = response.data;
            const total = response.meta.total;
            const items = [];

            newItems.forEach((item) => {
                if (this.store[item.id]) {
                    this.store[item.id].initData(item);
                } else {
                    this.store[item.id] = new this.EntityClass(this.entityName, this.apiServiceName, item);
                }

                items.push(this.store[item.id]);
            });

            this.isLoading = false;

            return { items, total };
        });
    }

    /**
     * Create a new empty local entity.
     *
     * @param {String} id
     * @return {Object}
     */
    create(id = utils.createId()) {
        if (typeof this.store[id] !== 'undefined') {
            return this.store[id];
        }

        return this.createEntityInStore(id, { isNew: true });
    }

    /**
     * Sync all entities in the store to the server.
     *
     * @param {Array} itemIds
     * @return {Promise}
     */
    sync(itemIds = []) {
        const syncCue = this.getSyncCue(itemIds);

        this.isLoading = true;

        return Promise.all(syncCue).then(() => {
            this.isLoading = false;
        });
    }

    /**
     * Builds up the promise cue for a sync with the server.
     *
     * @param {Array} itemIds
     * @return {Array}
     */
    getSyncCue(itemIds = []) {
        const syncCue = [];

        Object.keys(this.store).forEach((id) => {
            if (itemIds.length > 0 && !itemIds.includes(id)) {
                return;
            }

            if (this.store[id].isDeleted) {
                syncCue.push(new Promise((resolve, reject) => {
                    this.store[id].delete(true)
                        .then((response) => {
                            resolve(response);
                        })
                        .catch((response) => {
                            reject(response);
                        });
                }));
            } else if (this.store[id].isNew || this.store[id].hasChanges()) {
                syncCue.push(new Promise((resolve, reject) => {
                    this.store[id].save()
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
     * Helper function to create new entities in the store.
     *
     * @private
     * @param {String} id
     * @param {Object} entityOpts
     * @param {Object} storeOpts
     * @return {Object}
     */
    createEntityInStore(id = utils.createId(), storeOpts = {}, entityOpts = {}) {
        const entity = Entity.getRawEntityObject(this.entityName, true);
        Object.assign(entity, entityOpts);
        entity.id = id;

        this.store[id] = new this.EntityClass(this.entityName, this.apiServiceName, entity);
        Object.assign(this.store[id], storeOpts);

        return this.store[id];
    }

    /**
     * Getter for the api service.
     *
     * @return {Object}
     */
    get apiService() {
        const serviceContainer = Application.getContainer('service');
        return serviceContainer[this.apiServiceName];
    }
}

export default EntityStore;
