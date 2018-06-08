/**
 * @module core/data/EntityProxy
 */
import { Application, Entity, State } from 'src/core/shopware';
import { deepCopyObject, getAssociatedDeletions, getObjectChangeSet } from 'src/core/service/utils/object.utils';
import utils, { types } from 'src/core/service/util.service';

class EntityProxy {
    constructor(entityName, apiServiceName, data = null) {
        const me = this;

        me.entityName = entityName;
        me.apiServiceName = apiServiceName;

        me.errors = [];
        me.isLoading = false;
        me.isDeleted = false;
        me.isNew = false;
        me.isDetail = false;

        if (!data.id || data === null) {
            data = Entity.getRawEntityObject(entityName, true);
            data.id = utils.createId();
            me.isNew = true;
        }

        me.id = data.id;

        me.draft = deepCopyObject(data);
        me.original = deepCopyObject(data);

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
     * Initialize the entity with new data.
     *
     * @param {Object} data
     */
    initData(data) {
        if (this.hasChanges()) {
            this.draft = Object.assign(deepCopyObject(data), this.draft);
        } else {
            this.draft = deepCopyObject(data);
        }

        this.original = deepCopyObject(data);
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
     * @return {Object}
     */
    getChanges() {
        return getObjectChangeSet(this.original, this.draft, this.entityName);
    }

    /**
     * Check if there are local changes made to the entity.
     *
     * @return {boolean}
     */
    hasChanges() {
        return Object.keys(this.getChanges()).length > 0;
    }

    /**
     * Saves the entity and sends the local changes to the server.
     *
     * @return {Promise}
     */
    save() {
        const changeset = this.getChanges();
        const deletionCue = this.getDeletionPromiseCue();

        this.isLoading = true;

        if (this.isNew) {
            changeset.id = this.id;
            return this.apiService.create(changeset)
                .then((response) => {
                    this.isLoading = false;
                    this.initData(response.data);
                    return Promise.resolve(this.exposedData);
                })
                .catch((exception) => {
                    this.isLoading = false;
                    return Promise.reject(this.handleException(exception));
                });
        }

        return Promise.all(deletionCue).then((deleteResponse) => {
            if (types.isEmpty(changeset)) {
                this.isLoading = false;
                return Promise.resolve(deleteResponse);
            }

            return this.apiService.updateById(this.id, changeset)
                .then((response) => {
                    this.isLoading = false;
                    this.initData(response.data);
                    return Promise.resolve(this.exposedData);
                })
                .catch((exception) => {
                    this.isLoading = false;
                    return Promise.reject(this.handleException(exception));
                });
        }).catch((deleteException) => {
            this.isLoading = false;
            return Promise.reject(this.handleException(deleteException));
        });
    }

    /**
     * Deletes the entity.
     *
     * @param {boolean} directDeletion
     * @return {Promise<void>}
     */
    delete(directDeletion = false) {
        this.draft = {};
        this.isDeleted = true;

        if (directDeletion) {
            return this.apiService.delete(this.id).then(() => {
                delete State.getStore(this.entityName).store[this.id];
            });
        }

        return Promise.resolve();
    }

    /**
     * Gets the promise cue for the deletion process.
     *
     * @return {Array}
     */
    getDeletionPromiseCue() {
        const deletionCue = [];
        const deletions = this.getDeletedAssociations();

        Object.keys(deletions).forEach((property) => {
            if (types.isArray(deletions[property])) {
                deletions[property].forEach((association) => {
                    deletionCue.push(new Promise((resolve, reject) => {
                        this.apiService.deleteAssociation(this.draft.id, property, association.id)
                            .then((response) => {
                                resolve(response);
                            })
                            .catch((response) => {
                                reject(response);
                            });
                    }));
                });
            }
        });

        return deletionCue;
    }

    /**
     * Handles exceptions returned from the server.
     *
     * @param exception
     * @return {*}
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
     * @param error
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
     * @return {boolean}
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
            isDetail: this.isDetail,
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
}

export default EntityProxy;
