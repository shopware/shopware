import Criteria from './criteria.data';

export default class EntityCollection extends Array {
    constructor(source, entity, context, criteria = null, entities = [], total = null, aggregations = null) {
        super();

        this.entity = entity;
        this.source = source;
        this.context = context;
        this.criteria = criteria;
        this.aggregations = aggregations;
        this.total = total;

        this.push(...entities);

        /**
         * Returns the first item of the collection.
         * Returns null if the collection is empty
         * @returns {Object}
         */
        this.first = function firstEntityOfCollection() {
            if (this.length <= 0) {
                return null;
            }

            return this[0];
        };

        /**
         * Returns the last item of the collection.
         * Returns null if the collection is empty.
         * @return {Object}
         */
        this.last = function lastEntityOfCollection() {
            if (this.length <= 0) {
                return null;
            }

            return this[this.length - 1];
        };

        /**
         * Removes an entity from the collection. The entity is identified by the provided id
         * Returns true if the entity removed, false if the entity wasn't found
         * @param {string} id
         * @returns {boolean}
         */
        this.remove = function removeEntityFromCollection(id) {
            const itemIndex = this.findIndex(i => i.id === id);

            if (itemIndex < 0) {
                return false;
            }

            this.splice(itemIndex, 1);
            return true;
        };

        /**
         * Checks if the provided id is inside the collection
         * @param {string} id
         * @returns {boolean}
         */
        this.has = function hasEntityInCollection(id) {
            return this.some(i => i.id === id);
        };

        /**
         * Returns the entity for the provided id, null if the entity is not inside the collection
         * @param {String} id
         * @returns {Object|null}
         */
        this.get = function getEntityByIdOfCollection(id) {
            const item = this.find(i => i.id === id);

            if (typeof item !== 'undefined') {
                return item;
            }
            return null;
        };

        /**
         * Returns the entity at the given index position.
         * @param {Number} index
         * @return {Object|null}
         */
        this.getAt = function getEntityAtIndexOfCollection(index) {
            const item = this[index];

            if (typeof item !== 'undefined') {
                return item;
            }
            return null;
        };

        /**
         * Returns all ids of the internal entities
         * @returns {String[]}
         */
        this.getIds = function getEntityIdsOfCollection() {
            return this.map(i => i.id);
        };

        /**
         * Adds a new item to the collection
         * @param {Entity} e
         * @returns {number}
         */
        this.add = function addEntityToCollection(e) {
            this.push(e);
        };

        /**
         * Adds an entity to the collection at the given position.
         * @param {Entity} e
         * @param {Number} insertIndex
         */
        this.addAt = function addEntityAtIndexOfCollection(e, insertIndex) {
            if (typeof insertIndex === 'undefined') {
                this.add(e);
                return;
            }

            this.splice(insertIndex, 0, e);
        };

        /**
         * Move an item of the collection from an old index to a new index position.
         * @param {Number} oldIndex
         * @param {Number} newIndex
         * @return {Object}
         */
        this.moveItem = function moveEntityToNewIndexInCollection(oldIndex, newIndex = this.length) {
            if (oldIndex < 0 || oldIndex >= this.length) {
                return null;
            }

            if (newIndex === oldIndex) {
                return this.getAt(oldIndex);
            }

            const movedItem = this.find((item, index) => index === oldIndex);
            if (typeof movedItem === 'undefined') {
                return null;
            }

            const remainingItems = this.filter((item, index) => index !== oldIndex);

            const orderedItems = [
                ...remainingItems.slice(0, newIndex),
                movedItem,
                ...remainingItems.slice(newIndex),
            ];

            this.splice(0, this.length, ...orderedItems);

            return movedItem;
        };

        /**
         * Filters an EntityCollection and preserves its type. Resets criteria and total since it would mismatch.
         * @param {function} callback
         * @param {Object} scope
         * @return {Object}
         */
        this.filter = function filterEntityCollection(callback, scope) {
            const filtered = Object.getPrototypeOf(this).filter.call(this, callback, scope);
            return new EntityCollection(
                this.source,
                this.entity,
                this.context,
                this.criteria,
                filtered,
                this.total,
                this.aggregations,
            );
        };
    }

    /**
     * Returns a new collection from given one with
     * @param collection
     * @returns {EntityCollection}
     */
    static fromCollection(collection) {
        return new EntityCollection(
            collection.source,
            collection.entity,
            collection.context,
            Criteria.fromCriteria(collection.criteria),
            collection,
            collection.total,
            collection.aggregations,
        );
    }
}
