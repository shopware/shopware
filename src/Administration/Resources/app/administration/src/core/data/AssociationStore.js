import EntityStore from './EntityStore';

/**
 * @module core/data/AssociationStore
 * @deprecated tag:v6.4.0
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
    constructor(
        entityName,
        apiService,
        EntityClass,
        parentEntity = null,
        associationKey = null
    ) {
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
     * @param {String} [languageId='']
     * @return {Promise<{}>}
     */
    getList(params, populateParent = true, languageId = '') {
        return super.getList(params, false, languageId).then((response) => {
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

        if (parentProp && Shopware.Utils.types.isArray(parentProp)) {
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

    /**
     * Get a payload for the sync api with all associations to be deleted
     *
     * @return {Array}
     */
    getDeletionPayload() {
        const string = Shopware.Utils.string;
        const deletionPayload = [];

        Object.keys(this.store).forEach((id) => {
            const entity = this.store[id];

            if (entity.isDeleted) {
                deletionPayload.push({
                    [`${string.camelCase(this.getEntityName())}Id`]: id,
                    [`${string.camelCase(this.parentEntity.getEntityName())}Id`]: this.parentEntity.id
                });
            }
        });

        if (deletionPayload.length < 1) {
            return [];
        }

        return [{
            action: 'delete',
            entity: `${this.parentEntity.getEntityName()}_${this.getEntityName()}`,
            payload: deletionPayload
        }];
    }

    add(entity) {
        if (!Shopware.Utils.object.hasOwnProperty(entity, 'id')) {
            return false;
        }

        const newEntity = this.create(entity.id);
        newEntity.setLocalData(Shopware.Utils.object.deepCopyObject(entity));
        newEntity.isLocal = true;

        return true;
    }
}
