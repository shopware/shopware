/**
 * @module core/data/EntityStore
 * @deprecated 6.1
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
        this._entityName = entityName;
        this.EntityClass = EntityClass;

        const serviceContainer = Shopware.Application.getContainer('service');
        this.versionId = Shopware.Context.api.liveVersionId;

        if (Shopware.Utils.types.isString(apiService)) {
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
     * @param {String} [languageId]
     * @param {String} [versionId]
     * @return {EntityProxy}
     */
    getById(id, force = false, languageId = '', versionId = this.versionId) {
        if (!languageId || languageId.length < 1) {
            languageId = this.getLanguageStore().getCurrentId();
        }

        if (this.hasId(id) && force !== true) {
            // return directly from store if entity language fits or the entity has no translatable properties
            if (this.store[id].currentLanguageId === languageId || this.store[id].translatableProperties.length < 1) {
                return this.store[id];
            }
        }

        const ApiService = Shopware.Classes.ApiService;
        const entity = this.create(id);
        const headers = Object.assign(
            EntityStore.getLanguageHeader(languageId), ApiService.getVersionHeader(versionId)
        );

        entity.isLoading = true;

        this.apiService.getById(
            id,
            {},
            headers
        ).then((response) => {
            entity.setData(response.data, true, true, false, languageId);
            entity.isLoading = false;
        });

        return entity;
    }

    /**
     * Returns an entity by its id asynchronously.
     *
     * @memberOf module:core/data/EntityStore
     * @param {String} id
     * @param {String} [languageId]
     * @param {String} [versionId]
     * @return {Promise<never> | Promise<any>}
     */
    getByIdAsync(id, languageId = '', versionId = this.versionId) {
        if (!languageId || languageId.length < 1) {
            languageId = this.getLanguageStore().getCurrentId();
        }

        if (!id || !id.length) {
            return Promise.reject();
        }

        const entity = this.create(id);

        const ApiService = Shopware.Classes.ApiService;
        const headers = Object.assign(EntityStore.getLanguageHeader(languageId), ApiService.getVersionHeader(versionId));

        entity.isLoading = true;
        return this.apiService.getById(
            id,
            {},
            headers
        ).then((response) => {
            entity.setData(response.data, true, false, false, languageId);
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
     * @param languageId
     * @return {Promise}
     */
    getList(params, keepAssociations = false, languageId = '') {
        this.isLoading = true;

        if (!languageId || languageId.length < 1) {
            languageId = this.getLanguageStore().getCurrentId();
        }

        params.headers = Object.assign({}, EntityStore.getLanguageHeader(languageId), params.headers);

        return this.apiService.getList(params).then((response) => {
            const total = response.meta.total;
            const items = [];
            const aggregations = response.aggregations;

            this.isLoading = false;

            response.data.forEach((item) => {
                const entity = this.create(item.id);
                entity.setData(item, !keepAssociations, keepAssociations, keepAssociations, languageId);
                items.push(entity);
            });

            return { items, total, aggregations };
        });
    }

    /**
     * Get the language store
     *
     * @memberOf module:core/data/EntityStore
     * @return {EntityStore}
     */
    getLanguageStore() {
        return Shopware.StateDeprecated.getStore('language');
    }

    /**
     * Creates a new entity in the store.
     *
     * @memberOf module:core/data/EntityStore
     * @param {String} id
     * @return {EntityProxy}
     */
    create(id = Shopware.Utils.createId()) {
        if (this.hasId(id) && !this.store[id].deleted) {
            return this.store[id];
        }

        this.store[id] = new this.EntityClass(
            this.getEntityName(),
            this.apiService,
            id,
            this
        );
        return this.store[id];
    }

    /**
     * Duplicates an entity in the store.
     *
     * @memberOf module:core/data/EntityStore
     * @param {String} id
     * @param {boolean} includeAssociations
     * @return {EntityProxy}
     */
    duplicate(id, includeAssociations = false) {
        const newId = Shopware.Utils.createId();
        const deepCopyObject = Shopware.Utils.object.deepCopyObject;

        this.store[newId] = new this.EntityClass(this.getEntityName(), this.apiService, newId, this);

        if (this.hasId(id)) {
            const duplicateData = deepCopyObject(this.store[id].draft);
            duplicateData.id = newId;

            this.store[newId].setLocalData(duplicateData);

            if (includeAssociations) {
                Object.keys(this.store[id].associations).forEach((key) => {
                    const associations = [];
                    Object.keys(this.store[id].associations[key].store).forEach((associationId) => {
                        const association = this.store[newId].associations[key].create(associationId);
                        association.setLocalData(
                            deepCopyObject(this.store[id].associations[key].store[associationId])
                        );
                        this.store[id].associations[key].store[associationId].isLocal = true;
                        associations.push(this.store[id].associations[key].store[associationId]);
                    });

                    this.store[newId].associations[key].populateParentEntity(associations);
                });
            }
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
        if (!Shopware.Utils.object.hasOwnProperty(entity, 'id')) {
            return false;
        }

        this.store[entity.id] = entity;
        return true;
    }

    /**
     * Check if the given id exists
     *
     * @memberOf module:core/data/EntityStore
     * @param {string} id
     * @return {boolean}
     */
    hasId(id) {
        return this.store[id] !== undefined;
    }

    /**
     * Removes an entity from the store.
     *
     * @memberOf module:core/data/EntityStore
     * @param {EntityProxy} entity
     * @return {boolean}
     */
    remove(entity) {
        if (!Shopware.Utils.object.hasOwnProperty(entity, 'id') || !this.hasId(entity.id)) {
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
        if (!this.hasId(id)) {
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
        if (!Shopware.Utils.types.isFunction(iterator)) {
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
     * @param {String} [languageId='']
     * @return {Promise<any[]>}
     */
    sync(deletionsOnly = false, languageId = '') {
        if (!languageId || languageId.length < 1) {
            languageId = this.getLanguageStore().getCurrentId();
        }
        const syncService = Shopware.Service('syncService');
        let payload = this.getDeletionPayload();

        if (deletionsOnly === false) {
            payload = [...payload, ...this.getUpdatePayload()];
        }

        this.isLoading = true;

        return syncService.sync(
            payload,
            {},
            EntityStore.getLanguageHeader(languageId)
        ).then(() => {
            this.isLoading = false;
            payload.forEach((update) => {
                update.payload.forEach((entity) => {
                    if (this.store[entity.id]) {
                        this.store[entity.id].isLocal = false;
                    }
                });
            });
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
     * Get a payload for the sync api with all entities to be deleted.
     *
     * @return {Array}
     */
    getDeletionPayload() {
        const deletionPayload = [];

        Object.keys(this.store).forEach((id) => {
            const entity = this.store[id];

            if (entity.isDeleted) {
                const payload = { id: id };
                if (entity.versionId) {
                    payload.versionId = entity.versionId;
                }

                deletionPayload.push(payload);
            }
        });

        if (deletionPayload.length < 1) {
            return [];
        }

        return [{
            action: 'delete',
            entity: this.getEntityName(),
            payload: deletionPayload
        }];
    }

    /**
     * Get a payload for the sync api with all changes of entities.
     *
     * @return {Array}
     */
    getUpdatePayload() {
        const payload = [];
        let upsertPayload = [];

        Object.keys(this.store).forEach((id) => {
            const entity = this.store[id];

            // Deletions are handled in the getDeletionPayload() function
            if (entity.isDeleted) {
                return;
            }

            const changes = entity.getChanges();
            const changedAssociations = entity.getChangedAssociations();
            const deletedAssociationsPayload = entity.getDeletedAssociationsPayload();

            if (Object.keys(deletedAssociationsPayload).length > 0) {
                payload.push(deletedAssociationsPayload.pop());
            }

            if (entity.isLocal || Object.keys(changes).length > 0 || Object.keys(changedAssociations).length > 0) {
                upsertPayload.push(Object.assign({ id: id }, changes, changedAssociations));
            }
        });

        if (upsertPayload.length > 0) {
            upsertPayload = [{
                action: 'upsert',
                entity: this.getEntityName(),
                payload: upsertPayload
            }];
        }

        return [...payload, ...upsertPayload];
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

    /**
     * Getter for the private entityName
     *
     * @memberOf module:core/data/EntityStore
     * @return {String}
     */
    getEntityName() {
        return this._entityName;
    }

    /**
     * Gets the language header for api requests
     *
     * @memberOf module:core/data/EntityStore
     * @param {String} languageId
     * @return {Object}
     */
    static getLanguageHeader(languageId) {
        return { 'sw-language-id': languageId };
    }
}
