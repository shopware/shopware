import BaseEntityCollection from '@shopware-ag/admin-extension-sdk/es/data/_internals/BaseEntityCollection';
import Criteria from './criteria.data';
import Entity from './entity.data';

export default class EntityCollection extends BaseEntityCollection {
    entity: string;

    source: string;

    context: apiContext;

    criteria: Criteria|null;

    aggregations: string[]|null;

    total: number|null;

    first: () => Entity|null;

    last: () => Entity|null;

    remove: (id: string) => boolean;

    has: (id: string) => boolean;

    get: (id: string) => Entity|null;

    getAt: (index: number) => Entity|null;

    getIds: () => string[];

    add: (e: Entity) => void;

    addAt: (e: Entity, indexAt: number) => void;

    moveItem: (oldIndex: number, newIndex: number) => Entity|null;

    constructor(
        source: string,
        entityName: string,
        context: apiContext,
        criteria: Criteria|null = null,
        entities: Entity[] = [],
        total: number|null = null,
        aggregations: string[]|null = null,
    ) {
        super();

        this.entity = entityName;
        this.source = source;
        this.context = context;
        this.criteria = criteria;
        this.aggregations = aggregations;
        this.total = total;

        this.push(...entities);

        /**
         * Returns the first item of the collection.
         * Returns null if the collection is empty
         */
        this.first = function firstEntityOfCollection(): Entity|null {
            if (this.length <= 0) {
                return null;
            }

            return this[0];
        };

        /**
         * Returns the last item of the collection.
         * Returns null if the collection is empty.
         */
        this.last = function lastEntityOfCollection(): Entity|null {
            if (this.length <= 0) {
                return null;
            }

            return this[this.length - 1];
        };

        /**
         * Removes an entity from the collection. The entity is identified by the provided id
         * Returns true if the entity removed, false if the entity wasn't found
         */
        this.remove = function removeEntityFromCollection(id): boolean {
            const itemIndex = this.findIndex(i => i.id === id);

            if (itemIndex < 0) {
                return false;
            }

            this.splice(itemIndex, 1);
            return true;
        };

        /**
         * Checks if the provided id is inside the collection
         */
        this.has = function hasEntityInCollection(id: string): boolean {
            return this.some(i => i.id === id);
        };

        /**
         * Returns the entity for the provided id, null if the entity is not inside the collection
         */
        this.get = function getEntityByIdOfCollection(id: string): Entity|null {
            const item = this.find(i => i.id === id);

            if (typeof item !== 'undefined') {
                return item;
            }
            return null;
        };

        /**
         * Returns the entity at the given index position.
         */
        this.getAt = function getEntityAtIndexOfCollection(index: number): Entity|null {
            const item = this[index];

            if (typeof item !== 'undefined') {
                return item;
            }
            return null;
        };

        /**
         * Returns all ids of the internal entities
         */
        this.getIds = function getEntityIdsOfCollection(): string[] {
            return this.map(i => i.id);
        };

        /**
         * Adds a new item to the collection
         */
        this.add = function addEntityToCollection(e: Entity): void {
            this.push(e);
        };

        /**
         * Adds an entity to the collection at the given position.
         */
        this.addAt = function addEntityAtIndexOfCollection(e: Entity, insertIndex: number): void {
            if (typeof insertIndex === 'undefined') {
                this.add(e);
                return;
            }

            this.splice(insertIndex, 0, e);
        };

        /**
         * Move an item of the collection from an old index to a new index position.
         */
        this.moveItem = function moveEntityToNewIndexInCollection(
            oldIndex: number,
            newIndex: number|null = null,
        ): Entity|null {
            if (newIndex === null) {
                newIndex = this.length;
            }

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
         */
        // @ts-expect-error
        this.filter = function filterEntityCollection(
            callback: (e: Entity, index: number) => boolean,
            scope: unknown,
        ): EntityCollection {
            const filtered = (Object.getPrototypeOf(this) as EntityCollection)
                .filter.call(this, callback, scope);
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
     */
    static fromCollection(collection: EntityCollection): EntityCollection {
        return new EntityCollection(
            collection.source,
            collection.entity,
            collection.context,
            collection.criteria === null ? collection.criteria : Criteria.fromCriteria(collection.criteria),
            collection,
            collection.total,
            collection.aggregations,
        );
    }
}
