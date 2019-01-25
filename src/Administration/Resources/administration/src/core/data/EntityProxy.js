import { Application, Entity, State } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import { deepCopyObject, hasOwnProperty, getArrayChanges, getObjectDiff } from 'src/core/service/utils/object.utils';
import { required } from 'src/core/service/validation.service';
import type from 'src/core/service/utils/types.utils';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import ApiService from 'src/core/service/api.service';
import AssociationStore from './AssociationStore';

/**
 * @module core/data/EntityProxy
 */

/**
 * @class
 * @memberOf module:core/data/EntityProxy
 */
export default class EntityProxy {
    /**
     * @constructor
     * @memberOf module:core/data/EntityProxy
     * @param {String} entityName
     * @param {ApiService} apiService
     * @param {String} [id]
     * @param {EntityStore} [store=null]
     * @return {Proxy}
     */
    constructor(entityName, apiService, id = utils.createId(), store = null) {
        const that = this;

        that.id = id;
        that.entityName = entityName;

        /**
         * The API service for operating CRUD operations for the entity.
         *
         * @type ApiService
         */
        that.apiService = apiService;

        /**
         * The corresponding store, which holds the entity.
         *
         * @type EntityStore
         */
        that.store = store;

        /**
         * Shows if there is an async action working on the entity.
         *
         * @type {boolean}
         */
        that.isLoading = false;

        /**
         * Symbolizes if the entity was never synchronized with the server.
         *
         * @type {boolean}
         */
        that.isLocal = true;

        /**
         * Symbolizes that the entity was deleted locally but was not already deleted on the server.
         *
         * @type {boolean}
         */
        that.isDeleted = false;

        /**
         * Holds all exceptions related to this entity.
         *
         * @type {Array}
         */
        that.errors = [];

        /**
         * A registry of all OneToMany associated stores of this entity.
         *
         * @type {Object}
         */
        that.associations = {};

        /**
         * The original data of the entity.
         * All changes which are made locally will not affect this object.
         *
         * @type {Object}
         */
        that.original = Entity.getRawEntityObject(this.entitySchema);
        that.original.id = id;

        /**
         * The draft data of the entity on which local changes are applied.
         * For saving there will be a changeset generated between the draft and the original data.
         *
         * @type {Object}
         */
        that.draft = deepCopyObject(that.original);

        that.createAssociatedStores();

        return new Proxy(that.exposedData, {
            get(target, property) {
                // The normal getter for the raw data.
                if (property in target) {
                    return target[property];
                }

                // You can also access some methods of the class directly on the object.
                if (property in that) {
                    return that[property];
                }

                return null;
            },

            set(target, property, value) {
                if (property === 'draft') {
                    Object.assign(that.draft, deepCopyObject(value));
                    Object.assign(target, that.exposedData);
                    return true;
                }

                if (property === 'original') {
                    Object.assign(that.original, deepCopyObject(value));
                    return true;
                }

                if (property in target) {
                    target[property] = value;
                }

                if (property in that.draft) {
                    that.draft[property] = value;
                }

                if (property in that) {
                    that[property] = value;
                }

                return true;
            }
        });
    }

    /**
     * Initializes data of the entity by setting the draft and original data.
     * This method is mostly used to set data which was loaded from the server.
     *
     * @memberOf module:core/data/EntityProxy
     * @param {Object} data
     * @param {Boolean} [removeAssociationKeysFromData=true]
     * @param {Boolean} [populateAssociations=false]
     * @param {Boolean} [keepChanges=true]
     * @return {void}
     */
    setData(data, removeAssociationKeysFromData = true, populateAssociations = false, keepChanges = false) {
        const associatedProps = this.associatedEntityPropNames;

        if (populateAssociations === true) {
            this.populateAssociatedStores(data);
        }

        if (removeAssociationKeysFromData === true) {
            Object.keys(data).forEach((prop) => {
                if (associatedProps.includes(prop)) {
                    delete data[prop];
                }
            });
        }

        // always keep local changes, even if data is reloaded from server
        let draft = data;
        if (keepChanges === true) {
            const changes = this.getChanges();

            draft = Object.assign({}, data, changes);
        }

        this.draft = draft;
        this.original = data;
        this.isLocal = false;
    }

    /**
     * Apply local data changes to the entity.
     *
     * @memberOf module:core/data/EntityProxy
     * @param {Object} data
     * @param {Boolean} [removeAssociationKeysFromData=true]
     * @param {Boolean} [applyAsChange=true]
     * @return {void}
     */
    setLocalData(data, removeAssociationKeysFromData = true, applyAsChange = true) {
        const associatedProps = this.associatedEntityPropNames;

        if (removeAssociationKeysFromData === true) {
            Object.keys(data).forEach((prop) => {
                if (associatedProps.includes(prop)) {
                    delete data[prop];
                }
            });
        }

        this.draft = data;

        if (applyAsChange !== true) {
            this.original = data;
        }
    }

    /**
     * Discards current changes of the entity.
     *
     * @memberOf module:core/data/EntityProxy
     * @param {Boolean} [includeAssociations=true]
     * @return {void}
     */
    discardChanges(includeAssociations = true) {
        this.draft = deepCopyObject(this.original);

        if (includeAssociations) {
            this.discardAssociationChanges();
        }
    }

    /**
     * Applies the changes of the entity, so they become the current state.
     *
     * @memberOf module:core/data/EntityProxy
     * @return {void}
     */
    applyChanges() {
        this.original = deepCopyObject(this.draft);
    }

    /**
     * Saves the entity to the server.
     *
     * @memberOf module:core/data/EntityProxy
     * @param {Boolean} includeAssociations
     * @return {Promise<{}>}
     */
    save(includeAssociations = true) {
        const changes = this.getChanges();
        let changedAssociations = {};
        let deletionQueue = [];

        if (includeAssociations === true) {
            changedAssociations = this.getChangedAssociations();

            Object.assign(changes, changedAssociations);
            deletionQueue = this.getDeletedAssociationsQueue();
        }

        if (this.isLocal) {
            return this.sendCreateRequest(changes, changedAssociations);
        }

        this.isLoading = true;
        return Promise.all(deletionQueue).then(() => {
            if (!Object.keys(changes).length) {
                this.isLoading = false;
                return Promise.resolve(this.exposedData);
            }

            return this.sendUpdateRequest(changes, changedAssociations);
        }).catch((exception) => {
            this.isLoading = false;
            return Promise.reject(this.handleException(exception));
        });
    }

    /**
     * Internal method for sending the create request.
     *
     * @private
     * @memberOf module:core/data/EntityProxy
     * @param {Object} changes
     * @param {Object} changedAssociations
     * @return {Promise}
     */
    sendCreateRequest(changes, changedAssociations) {
        changes.id = this.id;

        this.isLoading = true;

        return this.apiService.create(changes, { _response: true }).then((response) => {
            this.isLoading = false;

            if (response.data) {
                this.setData(response.data);
            }

            this.refreshAssociations(changedAssociations);

            return Promise.resolve(this.exposedData);
        }).catch((exception) => {
            this.isLoading = false;
            return Promise.reject(this.handleException(exception));
        });
    }

    /**
     * Internal method for sending the update request.
     *
     * @private
     * @memberOf module:core/data/EntityProxy
     * @param {Object} changes
     * @param {Object} changedAssociations
     * @return {Promise}
     */
    sendUpdateRequest(changes, changedAssociations = {}) {
        this.isLoading = true;

        return this.apiService.updateById(this.id, changes, { _response: true }).then((response) => {
            this.isLoading = false;

            if (response.data) {
                this.setData(response.data);
            }

            this.refreshAssociations(changedAssociations);

            return Promise.resolve(this.exposedData);
        }).catch((exception) => {
            this.isLoading = false;
            return Promise.reject(this.handleException(exception));
        });
    }

    /**
     * Reloads changed associations from the server.
     *
     * @memberOf module:core/data/EntityProxy
     * @param {Object} changedAssociations
     * @return {void}
     */
    refreshAssociations(changedAssociations) {
        Object.keys(changedAssociations).forEach((associationKey) => {
            const association = this.associations[associationKey];
            const associationIds = changedAssociations[associationKey].reduce((acc, item) => {
                return [...acc, item.id];
            }, []);

            const limit = 50;
            const pages = Math.ceil(associationIds.length / limit);
            const criteria = CriteriaFactory.equalsAny('id', associationIds);

            for (let i = 1; i <= pages; i += 1) {
                association.getList({ page: i, limit, criteria }, false);
            }
        });
    }

    /**
     * Deletes the entity.
     *
     * @memberOf module:core/data/EntityProxy
     * @param {Boolean} directDelete
     * @return {Promise}
     */
    delete(directDelete = false) {
        this.isDeleted = true;

        if (directDelete !== true) {
            return Promise.resolve();
        }

        if (this.isLocal) {
            this.remove();
            return Promise.resolve();
        }

        return this.apiService.delete(this.id).then(() => {
            this.remove();
        }).catch((exception) => {
            // delete is idempotent so 404 is no error
            if (exception.response.status === 404) {
                this.remove();

                return Promise.resolve();
            }

            return Promise.reject(this.handleException(exception));
        });
    }

    /**
     * Removes the entity from its corresponding store.
     *
     * @memberOf module:core/data/EntityProxy
     * @return {boolean}
     */
    remove() {
        if (this.store === null) {
            return false;
        }

        return this.store.remove(this);
    }

    /**
     * Validates the entity.
     *
     * @memberOf module:core/data/EntityProxy
     * @return {Boolean}
     */
    validate(data = this.draft) {
        return this.requiredProperties.every((property) => {
            return required(data[property]);
        });
    }

    /**
     * Handles exceptions returned from the server.
     *
     * @memberOf module:core/data/EntityProxy
     * @param {Object} exception
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
     * @memberOf module:core/data/EntityProxy
     * @param {Object} error
     * @return {void}
     */
    addError(error) {
        if (error.id && this.errors.map(obj => obj.id).includes(error.id)) {
            return;
        }

        this.errors.push(error);

        State.getStore('error').addError({
            type: this.entityName,
            error
        });
    }

    /**
     * Creates entity stores for each OneToMany association of the entity.
     *
     * @memberOf module:core/data/EntityProxy
     * @return {void}
     */
    createAssociatedStores() {
        const associationDefinitions = this.associatedEntityPropDefinitions;

        const initContainer = Application.getContainer('init');
        const serviceContainer = Application.getContainer('service');

        Object.keys(associationDefinitions).forEach((prop) => {
            const definition = associationDefinitions[prop];
            const apiEndPoint = `${this.kebabEntityName}/${this.id}/${prop}`;

            const apiService = new ApiService(
                initContainer.httpClient,
                serviceContainer.loginService,
                apiEndPoint
            );

            this.associations[prop] = new AssociationStore(definition.entity, apiService, EntityProxy, this, prop);

            if (this.draft[prop] && this.draft[prop].length > 0) {
                this.populateAssociatedStore(prop, this.draft[prop]);
            }
        });
    }

    /**
     * Populates all associated stores and creates entities if there is initial data provided.
     *
     * @memberOf module:core/data/EntityProxy
     * @return {void}
     */
    populateAssociatedStores(data = this.draft) {
        const associatedProps = this.associatedEntityPropNames;

        associatedProps.forEach((prop) => {
            if (data[prop] && data[prop].length > 0) {
                this.populateAssociatedStore(prop, data[prop]);
            }
        });
    }

    /**
     * Populates an associated store and creates entities based on the provided data.
     *
     * @memberOf module:core/data/EntityProxy
     * @param {String} associationName
     * @param {Array} items
     * @return {EntityStore}
     */
    populateAssociatedStore(associationName, items) {
        const store = this.associations[associationName];

        items.forEach((item) => {
            const entity = store.create(item.id);
            entity.setData(item);
        });

        return store;
    }

    /**
     * Returns the store for a OneToMany association by property name.
     *
     * @memberOf module:core/data/EntityProxy
     * @param {String} associationName
     * @return {EntityStore}
     */
    getAssociation(associationName) {
        return this.associations[associationName];
    }

    /**
     * Returns a promise queue for syncing all deleted OneToMany associations.
     *
     * @memberOf module:core/data/EntityProxy
     * @return {Array}
     */
    getDeletedAssociationsQueue() {
        let deletionQueue = [];

        Object.keys(this.associations).forEach((associationKey) => {
            const association = this.associations[associationKey];
            const assocDeletionQueue = association.getDeletionQueue();

            if (assocDeletionQueue.length > 0) {
                deletionQueue = [...deletionQueue, ...assocDeletionQueue];
            }
        });

        return deletionQueue;
    }

    /**
     * Returns a payload for the sync api with all deleted OneToMany associations.
     *
     * @return {Array}
     */
    getDeletedAssociationsPayload() {
        let deletionPayload = [];

        Object.keys(this.associations).forEach((associationKey) => {
            const association = this.associations[associationKey];
            const assocDeletionPayload = association.getDeletionPayload();

            if (assocDeletionPayload.length > 0) {
                deletionPayload = [...deletionPayload, ...assocDeletionPayload];
            }
        });

        return deletionPayload;
    }

    /**
     * Get all changed OneToMany associations.
     * Includes changes and additions but no deletions, because they are handled separately.
     * Returns an object which fits the structure of the entity so it can be merged into other data or changesets.
     *
     * @memberOf module:core/data/EntityProxy
     * @return {Object}
     */
    getChangedAssociations() {
        const changes = {};

        Object.keys(this.associations).forEach((associationKey) => {
            const association = this.associations[associationKey];

            Object.keys(association.store).forEach((id) => {
                const entity = association.store[id];

                // Deletions are handled in separate requests
                if (entity.isDeleted) {
                    return;
                }

                const associationChanges = entity.getChanges();

                if (entity.isLocal || Object.keys(associationChanges).length > 0) {
                    associationChanges.id = id;
                    changes[associationKey] = changes[associationKey] || [];
                    changes[associationKey].push(associationChanges);
                }
            });
        });

        return changes;
    }

    /**
     * Discard all changes in OneToMany associations.
     * Associations marked as deleted will be unmarked and "local" associations will be removed from the store
     *
     * @memberOf module:core/data/EntityProxy
     * @return {void}
     */
    discardAssociationChanges() {
        Object.keys(this.associations).forEach((associationKey) => {
            const associationStore = this.associations[associationKey];

            associationStore.forEach((entity) => {
                if (entity.isDeleted) {
                    entity.isDeleted = false;
                }

                if (entity.isLocal) {
                    entity.remove();
                    return;
                }

                entity.discardChanges();
            });
        });
    }

    /**
     * Get all changes made to the data of the entity.
     * This method will generate a detailed changeset considering the schema definition of the entity.
     * Also handles changes for OneToOne associations and special JSON fields.
     *
     * @memberOf module:core/data/EntityProxy
     * @param {Object} a
     * @param {Object} b
     * @param {Object} schema
     * @return {Object}
     */
    getChanges(a = this.original, b = this.draft, schema = Entity.getDefinition(this.entityName)) {
        const properties = schema.properties;
        const propertyList = Object.keys(properties);
        const blacklist = Entity.getPropertyBlacklist();

        if (a === b) {
            return {};
        }

        if (!type.isObject(a) || !type.isObject(b)) {
            return b;
        }

        if (type.isDate(a) || type.isDate(b)) {
            if (a.valueOf() === b.valueOf()) {
                return {};
            }

            return b;
        }

        return Object.keys(b).reduce((acc, key) => {
            // The key is not part of the schema, or it is blacklisted
            if (!propertyList.includes(key) || blacklist.includes(key)) {
                return acc;
            }

            // The property does not exist in the base object, so it is an addition
            if (!hasOwnProperty(a, key)) {
                // The property is a OneToOne associated entity
                if (type.isObject(b[key]) && properties[key].entity) {
                    const addition = EntityProxy.validateSchema(b[key], Entity.getDefinition(properties[key].entity));

                    // invalidate the entity
                    if (b[key].store) {
                        b[key].store.remove(b[key]);
                    }

                    if (Object.keys(addition).length <= 0) {
                        return acc;
                    }

                    return { ...acc, [key]: addition };
                }

                // The property is a structured JSON field with schema
                if (type.isObject(b[key]) && properties[key].type === 'object' && properties[key].properties) {
                    const addition = EntityProxy.validateSchema(b[key], properties[key]);

                    if (Object.keys(addition).length <= 0) {
                        return acc;
                    }

                    return { ...acc, [key]: addition };
                }

                // The property is an unstructured JSON field
                if (type.isObject(b[key]) && properties[key].type === 'object') {
                    return { ...acc, [key]: b[key] };
                }

                // The property is a OneToMany associated entity
                if (type.isArray(b[key] && properties[key].entity)) {
                    return acc; // OneToMany associations are handled in a separate store
                }

                return { ...acc, [key]: b[key] };
            }

            // The property is a OneToOne associated entity
            if (type.isObject(b[key]) && properties[key].entity) {
                const changes = this.getChanges(a[key], b[key], Entity.getDefinition(properties[key].entity));

                // invalidate the entity
                if (b[key].store) {
                    b[key].store.remove(b[key]);
                }

                if (Object.keys(changes).length <= 0) {
                    return acc;
                }

                return { ...acc, [key]: changes };
            }

            // The property is a structured JSON field with schema
            if (type.isObject(b[key]) && properties[key].type === 'object' && properties[key].properties) {
                const changes = this.getChanges(a[key], b[key], properties[key]);

                if (Object.keys(changes).length <= 0) {
                    return acc;
                }

                return { ...acc, [key]: EntityProxy.validateSchema(Object.assign({}, a[key], b[key]), properties[key]) };
            }

            // The property is an unstructured JSON field
            if (type.isObject(b[key]) && properties[key].type === 'object') {
                const changes = getObjectDiff(a[key], b[key]);

                if (Object.keys(changes).length <= 0) {
                    return acc;
                }

                return { ...acc, [key]: Object.assign({}, a[key], b[key]) };
            }

            // The property is a OneToMany associated entity
            if (type.isArray(b[key] && properties[key].entity)) {
                return acc; // OneToMany associations are handled in a separate store
            }

            // The property is a normal array
            if (type.isArray(b[key])) {
                const changes = getArrayChanges(a[key], b[key]);

                if (changes.length <= 0) {
                    return acc;
                }

                return { ...acc, [key]: b[key] };
            }

            // The property is a normal object
            if (type.isObject(b[key])) {
                const changes = getObjectDiff(a[key], b[key]);

                if (Object.keys(changes).length <= 0) {
                    return acc;
                }

                return { ...acc, [key]: changes };
            }

            // Any other property
            if (b[key] !== a[key]) {
                return { ...acc, [key]: b[key] };
            }

            return acc;
        }, {});
    }

    /**
     * Validates the property structure of an object against an entity schema.
     * Removes also all properties which are blacklisted.
     *
     * @memberOf module:core/data/EntityProxy
     * @param {Object} obj
     * @param {Object} schema
     * @return {Object}
     */
    static validateSchema(obj, schema) {
        const properties = schema.properties;
        const propertyList = Object.keys(properties);
        const blacklist = Entity.getPropertyBlacklist();

        return Object.keys(obj).reduce((acc, key) => {
            if (!propertyList.includes(key) || blacklist.includes(key)) {
                return acc;
            }

            return { ...acc, [key]: obj[key] };
        }, {});
    }

    /**
     * Properties which will be exposed with the entity which can be used for internal tasks.
     * These will not be included in the entity definition or the changeset.
     *
     * @memberOf module:core/data/EntityProxy
     * @return {{isLoading: boolean, errors: Array}}
     */
    get privateData() {
        return {
            isDeleted: this.isDeleted,
            isLoading: this.isLoading,
            errors: this.errors
        };
    }

    /**
     * The data which is exposed by the entity.
     * This data will be used by the view layer.
     *
     * @memberOf module:core/data/EntityProxy
     * @return {Object}
     */
    get exposedData() {
        return Object.assign({}, this.privateData, this.draft);
    }

    /**
     * The schema definition of the entity.
     *
     * @memberOf module:core/data/EntityProxy
     * @return {*}
     */
    get entitySchema() {
        return Entity.getDefinition(this.entityName);
    }

    /**
     * A list with names of all required properties of the entity.
     *
     * @memberOf module:core/data/EntityProxy
     * @return {*}
     */
    get requiredProperties() {
        return Entity.getRequiredProperties(this.entityName);
    }

    /**
     * All property names of the entity which define a OneToMany relation.
     *
     * @memberOf module:core/data/EntityProxy
     * @return {*}
     */
    get associatedEntityPropNames() {
        return Entity.getAssociatedProperties(this.entityName);
    }

    /**
     * Get all property definitions of OneToMany associations of the entity.
     *
     * @memberOf module:core/data/EntityProxy
     * @return {{}}
     */
    get associatedEntityPropDefinitions() {
        const schema = this.entitySchema;
        const associationProps = this.associatedEntityPropNames;

        return Object.keys(schema.properties).reduce((acc, prop) => {
            if (associationProps.includes(prop)) {
                return { ...acc, [prop]: schema.properties[prop] };
            }

            return acc;
        }, {});
    }

    /**
     * Get the kebab version of the entity name.
     *
     * @memberOf module:core/data/EntityProxy
     * @return {String}
     */
    get kebabEntityName() {
        return this.entityName.replace(/_/g, '-');
    }
}
