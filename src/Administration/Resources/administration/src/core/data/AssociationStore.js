import { types } from 'src/core/service/util.service';
import EntityStore from './EntityStore';

/**
 * @module core/data/AssociationStore
 */

/**
 * @class
 * @memberOf module:core/data/AssociationStore
 */
export default class AssociationStore extends EntityStore {
    /**
     * @constructor
     * @memberOf module:core/data/AssociationStore
     * @param {String} entityName
     * @param {ApiService} apiService
     * @param {EntityProxy} EntityClass
     * @param {EntityProxy} [parentEntity=null]
     * @param {String} [associationKey=null]
     */
    constructor(entityName, apiService, EntityClass, parentEntity = null, associationKey = null) {
        super(entityName, apiService, EntityClass);

        this.parentEntity = parentEntity;
        this.associationKey = associationKey;
        this.total = null;
    }

    /**
     * Loads a list of entities from the server.
     *
     * @memberOf module:core/data/AssociationStore
     * @param {Object} params
     * @param {Boolean} populateParent
     * @return {Promise<{}>}
     */
    getList(params, populateParent = true) {
        return super.getList(params).then((response) => {
            if (populateParent === true && response.items && response.items.length) {
                this.populateParentEntity(response.items);
            }

            if (response.total) {
                this.total = response.total;
            }

            return response;
        });
    }

    /**
     * Populates the data of the parent entity with associated data.
     *
     * @memberOf module:core/data/AssociationStore
     * @param {Array} items
     * @return {String|Array}
     */
    populateParentEntity(items) {
        const parentProp = this.parentEntity.draft[this.associationKey];

        if (parentProp && types.isArray(parentProp)) {
            parentProp.splice(0, parentProp.length);
            parentProp.push(...items);
        }

        return parentProp;
    }

    /**
     * Returns the last total number of associated entities.
     *
     * @memberOf module:core/data/AssociationStore
     * @return {null|Number}
     */
    getTotal() {
        return this.total;
    }
}
