/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 * Mixin which contains helpers to work with position integers.
 */
import type Repository from 'src/core/data/repository.data';
import Criteria from 'src/core/data/criteria.data';
import type EntityCollection from '@shopware-ag/admin-extension-sdk/es/data/_internals/EntityCollection';

Shopware.Mixin.register('position', {
    methods: {
        /**
         * Returns a new position value using the the current max position + 1
         * starting with 1
         */
        getNewPosition<EntityName extends keyof EntitySchema.Entities>(
            repository: Repository<EntityName>,
            criteria: Criteria,
            context: typeof Shopware.Context.api,
            field = 'position',
        ) {
            criteria.addAggregation(Criteria.max('maxPosition', field))
                .addSorting(Criteria.sort(field, 'DESC'));

            return repository.search(criteria, context).then((result) => {
                // @ts-expect-error - maxPosition is defined in addAggregation
                // eslint-disable-next-line @typescript-eslint/no-unsafe-argument,@typescript-eslint/no-unsafe-member-access
                const position = parseInt(result?.aggregations?.maxPosition?.max, 10);

                if (Number.isNaN(position)) {
                    return Promise.resolve(1);
                }

                return Promise.resolve(position + 1);
            });
        },

        /**
         * Lowers the position value bye swapping with the next entity
         */
        lowerPositionValue<EntityName extends keyof EntitySchema.Entities>(
            collection: EntityCollection<EntityName>,
            selectedItem: EntitySchema.Entities[EntityName],
            field = 'position',
        ) {
            return this.changePosition(collection, selectedItem, field, 'ASC');
        },

        /**
         * Raises the position value bye swapping with the next entity
         *
         * @param {EntityCollection} collection
         * @param {Entity} selectedItem
         * @param {String} field - Name of the position column
         *
         * @returns {EntityCollection}
         */
        raisePositionValue<EntityName extends keyof EntitySchema.Entities>(
            collection: EntityCollection<EntityName>,
            selectedItem: EntitySchema.Entities[EntityName],
            field = 'position',
        ) {
            return this.changePosition(collection, selectedItem, field, 'DESC');
        },

        /**
         * Raises/Lowers the position value bye swapping with the next entity
         *
         * @param {EntityCollection} collection
         * @param {Entity} selectedItem
         * @param {String} field - Name of the position column
         * @param {String} direction - 'ASC' or 'DESC' depending on the directions, where you want to move your position
         *
         * @returns {EntityCollection}
         */
        changePosition<EntityName extends keyof EntitySchema.Entities>(
            collection: EntityCollection<EntityName>,
            selectedItem: EntitySchema.Entities[EntityName],
            field = 'position',
            direction = 'ASC',
        ) {
            if (collection.length < 2) {
                return collection;
            }

            // @ts-expect-error
            collection.sort((a, b) => a[field] - b[field]);
            // @ts-expect-error
            const itemIndex = collection.findIndex(entity => entity[field] === selectedItem[field]);

            if (
                (direction === 'ASC' && itemIndex < 1) ||
                (direction === 'DESC' && itemIndex >= collection.length - 1)
            ) {
                return collection;
            }

            const siblingAdd = (direction !== 'DESC') ? -1 : 1;

            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            [collection[itemIndex][field], collection[itemIndex + siblingAdd][field]] =
                // @ts-expect-error
                [collection[itemIndex + siblingAdd][field], collection[itemIndex][field]];

            // @ts-expect-error
            collection.sort((a, b) => a[field] - b[field]);

            return collection;
        },

        /**
         * Gets the item id next to the selectedItem, while the direction decides which sibling to choose
         *
         * @param {EntityCollection} collection
         * @param {Entity} selectedItem
         * @param {String} field - Name of the position column
         * @param {String} direction - 'ASC' or 'DESC' depending on the directions, where you want to look for the sibling
         *
         * @returns {int}
         */
        getSiblingIndex<EntityName extends keyof EntitySchema.Entities>(
            collection: EntityCollection<EntityName>,
            selectedItem: EntitySchema.Entities[EntityName],
            field = 'position',
            direction = 'ASC',
        ) {
            if (collection.length < 2) {
                return -1;
            }

            // @ts-expect-error
            collection.sort((a, b) => a[field] - b[field]);
            // @ts-expect-error
            const itemIndex = collection.findIndex(entity => entity[field] === selectedItem[field]);

            if (
                (direction === 'ASC' && itemIndex < 1) ||
                (direction === 'DESC' && itemIndex >= collection.length - 1)
            ) {
                return -1;
            }

            const siblingAdd = (direction !== 'DESC') ? -1 : 1;

            return itemIndex + siblingAdd;
        },

        /**
         * Gets the item next to the selectedItem, while the direction decides which sibling to choose
         *
         * @param {EntityCollection} collection
         * @param {Entity} selectedItem
         * @param {String} field - Name of the position column
         * @param {String} direction - 'ASC' or 'DESC' depending on the directions, where you want to look for the sibling
         *
         * @returns {Entity|null}
         */
        getSibling<EntityName extends keyof EntitySchema.Entities>(
            collection: EntityCollection<EntityName>,
            selectedItem: EntitySchema.Entities[EntityName],
            field = 'position',
            direction = 'ASC',
        ) {
            // @ts-expect-error
            collection.sort((a, b) => a[field] - b[field]);

            const index = this.getSiblingIndex(collection, selectedItem, field, direction);

            if (index === -1) {
                return null;
            }

            return collection[index] || null;
        },

        /**
         * Renumbers all position values incrementally
         *
         * @param {EntityCollection} collection
         * @param {int} startIndex - Sets the position value of the first entry
         * @param {String} field - Name of the position column
         *
         * @returns {EntityCollection}
         */
        renumberPositions<EntityName extends keyof EntitySchema.Entities>(
            collection: EntityCollection<EntityName>,
            startIndex = 0,
            field = 'position',
        ) {
            // @ts-expect-error
            collection.sort((a, b) => a[field] - b[field]);

            let i = startIndex;
            collection.forEach((item) => {
                // @ts-expect-error
                item[field] = i;
                i += 1;
            });

            return collection;
        },
    },
});
