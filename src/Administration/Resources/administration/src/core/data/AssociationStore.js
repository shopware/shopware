import { types } from 'src/core/service/util.service';
import EntityStore from './EntityStore';

export default class AssociationStore extends EntityStore {
    constructor(entityName, apiService, EntityClass, parentEntity = null, associationKey = null) {
        super(entityName, apiService, EntityClass);

        this.parentEntity = parentEntity;
        this.associationKey = associationKey;
        this.total = null;
    }

    /**
     * Loads a list of entities from the server.
     *
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
     * @param items
     * @return {*}
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
     * @return {null|Number}
     */
    getTotal() {
        return this.total;
    }
}
