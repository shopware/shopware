/**
 * @module core/data/EntityProxy
 */
import { Application, Entity, State } from 'src/core/shopware';
import {
    deepCopyObject,
    getAssociatedDeletions,
    getObjectChangeSet,
    hasOwnProperty
} from 'src/core/service/utils/object.utils';
import utils from 'src/core/service/util.service';
import EntityStore from 'src/core/data/EntityStore';
import ApiService from 'src/core/service/api/api.service';

/**
 * The entity proxy represents a single entity in the application. It automatically generates and updates the changeset
 * of the entity, provides helper methods for the CRUD operations and generates stores for the association stores.
 *
 * @class
 */
class EntityProxy {
    constructor(entityName, apiServiceName, data = null) {
        const me = this;

        me.entityName = entityName;
        me.apiServiceName = apiServiceName;

        me.errors = [];
        me.isLoading = false;
        me.isDeleted = false;
        me.isNew = false;
        me.isAddition = false;

        if (data === null || !data.id) {
            data = Entity.getRawEntityObject(entityName, true);
            data.id = utils.createId();
            me.isNew = true;
        }

        me.id = data.id;

        me.draft = deepCopyObject(data);
        me.original = deepCopyObject(data);

        me.$store = null;
        me.associationStores = new Map();
        me.createAssociationStores();

        // Return a proxy with the exposed data as the base instead of the whole class.
        return new Proxy(me.exposedData, {
            get(target, property) {
                // The normal getter for the raw data.
                if (property in target) {
                    return target[property];
                }

                // You can also access some methods of the class directly on the object.
                if (property in me) {
                    return me[property];
                }

                return null;
            },

            set(target, property, value) {
                if (property === 'draft') {
                    Object.assign(target, value);
                    me.draft = value;
                    return true;
                }

                if (property in target) {
                    target[property] = value;

                    if (property in me.draft) {
                        me.draft[property] = value;
                    }

                    return true;
                }

                if (property in me) {
                    me[property] = value;
                    return true;
                }

                return false;
            }
        });
    }

    /**
     * Initialize the entity with new data. If the entity exists already, we're applying the changes onto the new data.
     *
     * @param {Object} data
     * @param {Boolean} [removeAssociationsKeysFromData = true]
     * @returns {EntityProxy}
     */
    initData(data, removeAssociationsKeysFromData = true) {
        const changes = this.getChanges();

        if (removeAssociationsKeysFromData) {
            data = Object.keys(data).reduce((acc, key) => {
                if (!this.associatedProperties.includes(key)) {
                    acc[key] = data[key];
                }

                return acc;
            }, {});
        }

        if (Object.keys(changes).length) {
            this.draft = Object.assign(deepCopyObject(data), this.draft);
        } else {
            this.draft = Object.assign(this.draft, deepCopyObject(data));
        }

        this.original = Object.assign(this.original, deepCopyObject(data));

        return this;
    }

    /**
     * Creates the association stores for the entity. The associations are based on the entity scheme. The
     * associated store will be configured with their own api service, otherwise we had to configure the base resource
     * api service which will lead to unpredictable behavior of the services.
     *
     * @returns {Map<String, Object>}
     */
    createAssociationStores() {
        const entityDefinition = Entity.getDefinition(this.entityName);

        const initContainer = Application.getContainer('init');
        const serviceContainer = Application.getContainer('service');

        const associations = this.associatedProperties.reduce((accumulator, propName) => {
            const prop = entityDefinition.properties[propName];

            accumulator.push({
                name: propName,
                entity: prop.entity
            });

            return accumulator;
        }, []);

        associations.forEach((association) => {
            const name = association.name;
            const kebabEntityName = this.entityName.replace('_', '-');
            const apiEndPoint = `${kebabEntityName}/${this.id}/${name}`;

            const store = new EntityStore(association.entity, new ApiService(
                initContainer.httpClient,
                serviceContainer.loginService,
                apiEndPoint
            ));

            // When the association gets data on the initial call, we're adding them straight away
            if (hasOwnProperty(this.draft, name) && this.draft[name].length) {
                this.draft[name].forEach((item) => {
                    const entity = new EntityProxy(this.entityName, this.apiService, item);
                    store.add(entity);
                });
            }

            // Set additional private properties
            store.$parent = this;
            store.$type = name;

            this.associationStores.set(name, store);
        });

        return this.associationStores;
    }

    /**
     * Returns an association store if it exists.
     * Otherwise it will return a falsy value.
     *
     * @param {String} storeName
     * @returns {Boolean|EntityStore}
     */
    getAssociationStore(storeName) {
        if (!this.associationStores.has(storeName)) {
            return false;
        }

        return this.associationStores.get(storeName);
    }

    /**
     * Lists all available association stores.
     *
     * @returns {IterableIterator<[EntityStore]>}
     */
    listAssociationStores() {
        return this.associationStores.entries();
    }

    /**
     * Get all associations which got deleted.
     *
     * @return {Object}
     */
    getDeletedAssociations() {
        return getAssociatedDeletions(this.original, this.draft, this.entityName);
    }

    /**
     * Get all local changes made to the entity.
     *
     * @param {Boolean} [includeAssociations = false]
     * @return {Object}
     */
    getChanges(includeAssociations = false) {
        return getObjectChangeSet(this.original, this.draft, this.entityName, includeAssociations);
    }

    /**
     * Saves the entity and sends the local changes to the server. By default the method will sync the association
     * stores automatically.
     *
     * @param {Boolean} [syncAssociations = true]
     * @param {Boolean} [changesetIncludeAssociations = false]
     * @return {Promise<T>}
     */
    save(syncAssociations = true, changesetIncludeAssociations = false) {
        const changeset = this.getChanges(changesetIncludeAssociations);
        const associationCue = [];

        // Apply the association changes to the changeset, so we're just having one request to update associations
        if (syncAssociations) {
            this.associationStores.forEach((associationStore, entityKey) => {
                Object.keys(associationStore.store).forEach((entityId) => {
                    const storeEntity = associationStore.store[entityId];
                    const changes = storeEntity.getChanges();

                    if (storeEntity.isDeleted) {
                        return;
                    }

                    if (!storeEntity.isAddition && !Object.keys(changes).length) {
                        return;
                    }

                    if (!hasOwnProperty(changeset, entityKey)) {
                        changeset[entityKey] = [];
                    }

                    changes.id = entityId;
                    changeset[entityKey].push(changes);
                });
            });
        }

        /**
         * The association stores will be automatically synced (deletions only),
         * the rest will be send using the main entry using the generated changeset.
         */
        if (syncAssociations) {
            this.associationStores.forEach((store) => {
                associationCue.push(new Promise((resolve, reject) => {
                    store.sync(true).then(resolve).catch(reject);
                }));
            });
        }

        this.isLoading = true;

        if (this.isNew) {
            changeset.id = this.id;

            if (syncAssociations && associationCue.length) {
                return Promise.all(associationCue).then(() => {
                    if (!Object.keys(changeset).length) {
                        return Promise.resolve(this.exposedData);
                    }

                    return this.sendCreateRequest(changeset);
                });
            }

            if (!Object.keys(changeset).length) {
                return Promise.resolve(this.exposedData);
            }

            return this.sendCreateRequest(changeset);
        }

        if (syncAssociations && associationCue.length) {
            return Promise.all(associationCue).then(() => {
                if (!Object.keys(changeset).length) {
                    return Promise.resolve(this.exposedData);
                }

                return this.sendUpdateRequest(changeset);
            });
        }

        if (!Object.keys(changeset).length) {
            return Promise.resolve(this.exposedData);
        }

        return this.sendUpdateRequest(changeset);
    }

    sendCreateRequest(changeset) {
        return this.apiService.create(changeset, { _response: true })
            .then((response) => {
                this.isLoading = false;

                if (response.data) {
                    this.initData(response.data);
                }

                return Promise.resolve(this.exposedData);
            })
            .catch((exception) => {
                this.isLoading = false;
                return Promise.reject(this.handleException(exception));
            });
    }

    sendUpdateRequest(changeset) {
        return this.apiService.updateById(this.id, changeset, { _response: true })
            .then((response) => {
                this.isLoading = false;

                if (response.data) {
                    this.initData(response.data);
                }

                return Promise.resolve(this.exposedData);
            })
            .catch((exception) => {
                this.isLoading = false;
                return Promise.reject(this.handleException(exception));
            });
    }

    /**
     * Removes this entity from the store.
     *
     * @returns {Boolean}
     */
    remove() {
        if (!this.$store) {
            return false;
        }
        return this.$store.remove(this);
    }

    /**
     * Deletes the entity using the configured API service. By default the method marks the entity as deleted, but it is
     * also possible to delete it directly.
     *
     * @param {Boolean} [directDeletion = true]
     * @return {Promise<void>}
     */
    delete(directDeletion = false) {
        this.draft = {};
        this.isDeleted = true;

        if (this.isAddition && this.$store) {
            this.remove();
        }

        if (directDeletion && !this.isAddition) {
            return this.apiService.delete(this.id).then(() => {
                if (this.$store && this.$store[this.id]) {
                    this.remove();
                } else {
                    delete State.getStore(this.entityName).remove(this.id);
                }
            });
        }

        return Promise.resolve();
    }

    /**
     * Handles exceptions returned from the server.
     *
     * @param exception
     * @return {Object}
     */
    handleException(exception) {
        if (exception.response.data && exception.response.data.errors) {
            exception.response.data.errors.forEach((error) => {
                this.addError(error);
            });
        }

        return exception;
    }

    /**
     * Adds a new error for the entity.
     *
     * @param {Object} error
     * @returns {void}
     */
    addError(error) {
        this.errors.push(error);

        State.getStore('error').addError({
            type: this.entityName,
            error
        });
    }

    /**
     * Validates the entity.
     *
     * @return {Boolean}
     */
    validate() {
        return this.requiredProperties.every((property) => {
            return this.draft[property] !== null &&
                   typeof this.draft[property] !== 'undefined';
        });
    }

    /**
     * The additional properties you want to add to the exposed data.
     *
     * @return {Object}
     */
    get localData() {
        return {
            isLoading: this.isLoading,
            isNew: this.isNew,
            isDeleted: this.isDeleted,
            errors: this.errors
        };
    }

    /**
     * The exposed data of the entity which is used for data binding.
     *
     * @return {Object}
     */
    get exposedData() {
        return Object.assign({}, this.localData, this.draft);
    }

    /**
     * Getter for the corresponding api service.
     *
     * @return {Object}
     */
    get apiService() {
        if (this.$store) {
            return this.$store.apiService;
        }

        const serviceContainer = Application.getContainer('service');
        return serviceContainer[this.apiServiceName];
    }

    /**
     * Getter for the corresponding entity schema.
     *
     * @return {Object}
     */
    get entitySchema() {
        return Entity.getDefinition(this.entityName);
    }

    /**
     * Getter for the required properties of the entity.
     *
     * @return {Array}
     */
    get requiredProperties() {
        return Entity.getRequiredProperties(this.entityName);
    }

    /**
     * Getter for the associated properties of the entity.
     *
     * @returns {Array}
     */
    get associatedProperties() {
        return Entity.getAssociatedProperties(this.entityName);
    }
}

export default EntityProxy;
