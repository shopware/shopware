/**
 * @module core/data/EntityProxy
 * @deprecated 6.1
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
    constructor(
        entityName,
        apiService,
        id = Shopware.Utils.createId(),
        store = null
    ) {
        /**
         * Load dependecies
         * @type {String}
         */
        this.deepCopyObject = Shopware.Utils.object.deepCopyObject;

        this.id = id;
        this._entityName = entityName;

        /**
         * The API service for operating CRUD operations for the entity.
         *
         * @type ApiService
         */
        this.apiService = apiService;

        /**
         * The corresponding store, which holds the entity.
         *
         * @type EntityStore
         */
        this.store = store;

        /**
         * Shows if there is an async action working on the entity.
         *
         * @type {boolean}
         */
        this.isLoading = false;

        /**
         * Symbolizes if the entity was never synchronized with the server.
         *
         * @type {boolean}
         */
        this.isLocal = true;

        /**
         * Symbolizes this the entity was deleted locally but was not already deleted on the server.
         *
         * @type {boolean}
         */
        this.isDeleted = false;

        /**
         * Holds all exceptions related to this entity.
         *
         * @type {Array}
         */
        this.errors = [];

        /**
         * A registry of all OneToMany associated stores of this entity.
         *
         * @type {Object}
         */
        this.associations = {};

        /**
         * The original data of the entity.
         * All changes which are made locally will not affect this object.
         *
         * @type {Object}
         */
        this.original = Shopware.Entity.getRawEntityObject(this.entitySchema);
        this.original.id = id;

        /**
         * The draft data of the entity on which local changes are applied.
         * For saving there will be a changeset generated between the draft and the original data.
         *
         * @type {Object}
         */
        this.draft = this.deepCopyObject(this.original);

        this.currentLanguageId = Shopware.StateDeprecated.getStore('language').getCurrentId();

        this.createAssociatedStores();

        const that = this;

        return new Proxy(this.exposedData, {
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
                    Object.assign(that.draft, value);
                    Object.assign(target, that.exposedData);
                    return true;
                }

                if (property === 'original') {
                    Object.assign(that.original, value);
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
            },

            deleteProperty(target, property) {
                delete target[property];

                if (property in that.draft) {
                    delete that.draft[property];
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
     * @param {Boolean} [keepChanges=false]
     * @param {String} [languageId='']
     * @return {void}
     */
    setData(data, removeAssociationKeysFromData = true, populateAssociations = false, keepChanges = false, languageId = '') {
        if (languageId && languageId.length > 0) {
            this.currentLanguageId = languageId;
        }

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
        this.original = this.deepCopyObject(data);
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
        this.draft = this.deepCopyObject(this.original);

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
        this.original = this.deepCopyObject(this.draft);
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
        const ApiService = Shopware.Classes.ApiService;
        changes.id = this.id;

        this.isLoading = true;

        let additionalHeaders = Shopware.DataDeprecated.EntityStore.getLanguageHeader(this.currentLanguageId);
        if (this.versionId) {
            additionalHeaders = Object.assign(additionalHeaders, ApiService.getVersionHeader(this.versionId));
        }

        return this.apiService.create(
            changes,
            { _response: true },
            additionalHeaders
        ).then((response) => {
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
        const ApiService = Shopware.Classes.ApiService;
        this.isLoading = true;

        let additionalHeaders = Shopware.DataDeprecated.EntityStore.getLanguageHeader(this.currentLanguageId);
        if (this.versionId) {
            additionalHeaders = Object.assign(additionalHeaders, ApiService.getVersionHeader(this.versionId));
        }

        return this.apiService.updateById(
            this.id,
            changes,
            { _response: true },
            additionalHeaders
        ).then((response) => {
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
            const criteria = Shopware.DataDeprecated.CriteriaFactory.equalsAny('id', associationIds);

            for (let i = 1; i <= pages; i += 1) {
                association.getList({ page: i, limit, criteria, versionId: this.versionId }, false);
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

        let additionalHeaders = {};
        if (this.versionId) {
            additionalHeaders = Shopware.Classes.ApiService.getVersionHeader(this.versionId);
        }

        return this.apiService.delete(this.id, {}, additionalHeaders).then(() => {
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
        const required = Shopware.Service('validationService').required;

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
                this.setErrorData(error);
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
    setErrorData(error) {
        if (error.id && this.errors.map(obj => obj.id).includes(error.id)) {
            return;
        }
        this.errors.push(error);
    }

    /**
     * Creates entity stores for each OneToMany association of the entity.
     *
     * @memberOf module:core/data/EntityProxy
     * @return {void}
     */
    createAssociatedStores() {
        const associationDefinitions = this.associatedEntityPropDefinitions;

        const initContainer = Shopware.Application.getContainer('init');

        Object.keys(associationDefinitions).forEach((prop) => {
            const definition = associationDefinitions[prop];
            const apiEndPoint = `${this.kebabEntityName}/${this.id}/${prop}`;

            const apiService = new Shopware.Classes.ApiService(
                initContainer.httpClient,
                Shopware.Service('loginService'),
                apiEndPoint
            );

            this.associations[prop] = new Shopware.DataDeprecated.AssociationStore(
                definition.entity,
                apiService,
                EntityProxy,
                this,
                prop
            );

            if (this.draft[prop] && this.draft[prop].length > 0) {
                this.populateAssociatedStore(prop, this.draft[prop]);
            }
        });
    }

    /**
     * Populates all associated stores and creates entities if there is initial data provided.
     *
     * @memberOf module:core/data/EntityProxy
     * @param {Object} data
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

        items.forEach((item, index) => {
            const entity = store.create(item.id);
            entity.setData(item, false, true, false);
            items[index] = entity;
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

                const entityChanges = entity.getChanges();
                const entityAssociationChanges = entity.getChangedAssociations();
                Object.assign(entityChanges, entityAssociationChanges);

                if (entity.isLocal || Object.keys(entityChanges).length > 0) {
                    entityChanges.id = id;
                    changes[associationKey] = changes[associationKey] || [];
                    changes[associationKey].push(entityChanges);
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

                entity.discardChanges(true);
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
    getChanges(a = this.original, b = this.draft, schema = Shopware.Entity.getDefinition(this.getEntityName())) {
        const properties = schema.properties;
        const propertyList = Object.keys(properties);
        const blacklist = Shopware.Entity.getPropertyBlacklist();
        const Entity = Shopware.Entity;
        const type = Shopware.Utils.types;

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
            // The key is not part of the schema, or it is blacklisted or it is readonly
            if (!propertyList.includes(key) || blacklist.includes(key) || properties[key].readOnly) {
                return acc;
            }

            // The property does not exist in the base object, so it is an addition
            if (!Shopware.Utils.object.hasOwnProperty(a, key)) {
                // The property is a OneToOne associated entity
                if (type.isPlainObject(b[key]) && properties[key].entity) {
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
                if (type.isPlainObject(b[key]) && properties[key].type === 'object' && properties[key].properties) {
                    const addition = EntityProxy.validateSchema(b[key], properties[key]);

                    if (Object.keys(addition).length <= 0) {
                        return acc;
                    }

                    return { ...acc, [key]: addition };
                }

                // The property is an unstructured JSON field
                if (type.isPlainObject(b[key]) && properties[key].type === 'object') {
                    return { ...acc, [key]: b[key] };
                }

                // The property is a OneToMany associated entity
                if (type.isArray(b[key] && properties[key].entity)) {
                    return acc; // OneToMany associations are handled in a separate store
                }

                return { ...acc, [key]: b[key] };
            }

            // The property is a OneToOne associated entity
            if (type.isPlainObject(b[key]) && properties[key].entity) {
                const changes = this.getChanges(a[key], b[key], Entity.getDefinition(properties[key].entity));

                // invalidate the entity
                if (b[key].store) {
                    b[key].store.remove(b[key]);
                }

                if (Object.keys(changes).length <= 0) {
                    return acc;
                }

                if (typeof b[key].id !== 'undefined') {
                    changes.id = b[key].id;
                }

                return { ...acc, [key]: changes };
            }

            // The property is a structured JSON field with schema
            if (type.isPlainObject(b[key]) && properties[key].type === 'object' && properties[key].properties) {
                const changes = this.getChanges(a[key], b[key], properties[key]);

                if (Object.keys(changes).length <= 0) {
                    return acc;
                }

                return { ...acc, [key]: EntityProxy.validateSchema(Object.assign({}, a[key], b[key]), properties[key]) };
            }

            // The property is an unstructured JSON field
            if (type.isPlainObject(b[key]) && properties[key].type === 'object') {
                const compareA = JSON.stringify(a[key]);
                const compareB = JSON.stringify(b[key]);

                if (compareA === compareB) {
                    return acc;
                }

                return { ...acc, [key]: this.deepCopyObject(b[key]) };
            }

            // The property is a OneToMany associated entity
            if (type.isArray(b[key]) && properties[key].entity) {
                return acc; // OneToMany associations are handled in a separate store
            }

            // The property is a normal array
            if (type.isArray(b[key])) {
                const changes = Shopware.Utils.object.getArrayChanges(a[key], b[key]);

                if (changes.length <= 0) {
                    return acc;
                }

                return { ...acc, [key]: b[key] };
            }

            // The property is a normal object
            if (type.isPlainObject(b[key])) {
                const changes = Shopware.Utils.object.getObjectDiff(a[key], b[key]);

                if (Object.keys(changes).length <= 0) {
                    return acc;
                }

                return { ...acc, [key]: changes };
            }

            // Any other property
            if (b[key] !== a[key]) {
                // Empty string fields have to be null instead of empty string
                if (a[key] && b[key] === '') {
                    return { ...acc, [key]: null };
                }

                // Don't reset properties if they are already null
                if ((!a[key] || a[key] === null) && (b[key] === '' || b[key] === null)) {
                    return acc;
                }

                return { ...acc, [key]: b[key] };
            }

            return acc;
        }, {});
    }

    /**
     * Checks if the entity has any changes. It also checks for deleted associations and changed associations
     *
     * @memberOf module:core/data/EntityProxy
     * @return {Boolean}
     */
    hasChanges() {
        if (this.getDeletedAssociationsPayload().length > 0) {
            return true;
        }

        if (Object.keys(this.getChangedAssociations()).length > 0) {
            return true;
        }

        return Object.keys(this.getChanges()).length > 0;
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
        const blacklist = Shopware.Entity.getPropertyBlacklist();

        return Object.keys(obj).reduce((acc, key) => {
            if (!propertyList.includes(key) || blacklist.includes(key)) {
                return acc;
            }

            return { ...acc, [key]: obj[key] };
        }, {});
    }

    /**
     * Getter for the private entityName
     *
     * @memberOf module:core/data/EntityProxy
     * @return {String}
     */
    getEntityName() {
        return this._entityName;
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
            errors: this.errors,
            versionId: this.versionId
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
        return Shopware.Entity.getDefinition(this.getEntityName());
    }

    /**
     * A list with names of all required properties of the entity.
     *
     * @memberOf module:core/data/EntityProxy
     * @return {*}
     */
    get requiredProperties() {
        return Shopware.Entity.getRequiredProperties(this.getEntityName());
    }

    /**
     * A list with names of all translatable properties of the entity.
     *
     * @memberOf module:core/data/EntityProxy
     * @return {*}
     */
    get translatableProperties() {
        return Shopware.Entity.getTranslatableProperties(this.getEntityName());
    }

    /**
     * All property names of the entity which define a OneToMany relation.
     *
     * @memberOf module:core/data/EntityProxy
     * @return {*}
     */
    get associatedEntityPropNames() {
        return Shopware.Entity.getAssociatedProperties(this.getEntityName());
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
        return this.getEntityName().replace(/_/g, '-');
    }
}
