import { cloneDeep } from 'src/core/service/utils/object.utils';

export default class Entity {
    constructor(id, entityName, data) {
        this.id = id;
        this._origin = cloneDeep(data);
        this._entityName = entityName;
        this._draft = data;
        this._isDirty = false;
        this._isNew = false;
        const that = this;

        return new Proxy(this._draft, {
            get(target, property) {
                if (property in that._draft) {
                    return that._draft[property];
                }

                return that[property];
            },

            set(target, property, value) {
                Shopware.Application.view.setReactive(that._draft, property, value);
                that._isDirty = true;

                return true;
            },
        });
    }

    /**
     * Marks the entity as new. New entities will be provided as create request to the server
     */
    markAsNew() {
        this._isNew = true;
    }

    /**
     * Allows to check if the entity is a new entity and should be provided as create request
     * to the server
     *
     * @returns {boolean}
     */
    isNew() {
        return this._isNew;
    }

    /**
     * Allows to check if the entity changed
     * @returns {boolean}
     */
    getIsDirty() {
        return this._isDirty;
    }

    /**
     * Allows access the origin entity value. The origin value contains the server values
     * @returns {Object}
     */
    getOrigin() {
        return this._origin;
    }

    /**
     * Allows to access the draft value. The draft value contains all local changes of the entity
     * @returns {Object}
     */
    getDraft() {
        return this._draft;
    }

    /**
     * Allows to access the entity name. The entity name is used as unique identifier `product`, `media`, ...
     * @returns {string}
     */
    getEntityName() {
        return this._entityName;
    }
}
