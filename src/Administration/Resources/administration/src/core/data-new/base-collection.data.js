import { warn } from 'src/core/service/utils/debug.utils';
import types from 'src/core/service/utils/types.utils';

export default class BaseCollection {
    constructor(source, entity, context, criteria, view) {
        this.source = source;
        this.context = context;
        this.criteria = criteria;
        this.elements = {};
        this.entity = entity;
        this.view = view;

        // makes the collection iterable via for(const item of collection)
        this[Symbol.iterator] = function* iterator() {
            const values = Object.values(this.elements);

            for (const item of values) { // eslint-disable-line no-restricted-syntax
                yield item;
            }
        };
    }

    /**
     * Returns the first element of the collection.
     * Returns null if the collection is empty
     * @returns {Object|null}
     */
    first() {
        const keys = Object.keys(this.elements);
        if (keys.length <= 0) {
            return null;
        }
        return this.elements[keys[0]];
    }

    /**
     * Removes an entity from the collection. The entity is identified by the provided id
     * Returns true if the entity removed, false if the entity wasn't found
     * @param {string} id
     * @returns {boolean}
     */
    remove(id) {
        if (!this.has(id)) {
            return false;
        }

        const entity = this.get(id);

        this.view.deleteReactive(this.elements, entity.id);

        return true;
    }

    /**
     * Checks if the provided id is inside the collection
     * @param {string} id
     * @returns {boolean}
     */
    has(id) {
        return Object.prototype.hasOwnProperty.call(this.elements, id);
    }

    /**
     * Returns the entity for the provided id, null if the entity is not inside the collection
     * @param {string} id
     * @returns {Object|null}
     */
    get(id) {
        if (this.elements[id]) {
            return this.elements[id];
        }
        return null;
    }

    /**
     * Returns all ids of the internal entities
     * @returns {string[]}
     */
    getIds() {
        return Object.keys(this.elements);
    }

    /**
     * If the entity already exists in the collection, it will be replaced with the new one
     * @param {Entity} entity
     * @returns {boolean}
     */
    add(entity) {
        return this.view.setReactive(this.elements, entity.id, entity);
    }

    forEach(iterator, scope = this) {
        if (!types.isFunction(iterator)) {
            warn('Base collection', 'No function provided to forEach function');

            return this.elements;
        }

        Object.keys(this.elements).forEach((id) => {
            iterator.call(scope, this.elements[id], id);
        });

        return this.elements;
    }
}
