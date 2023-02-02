const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * Mixin which contains helpers to work with position integers.
 */
Mixin.register('position', {
    methods: {
        /**
         * Returns a new position value using the the current max position + 1
         * starting with 1
         *
         * @param {Repository} repository
         * @param {Criteria} criteria - Criteria for cases when the position isn't unique itself
         * @param {Context} context
         * @param {String} field - Name of the property, which is the used position
         *
         * @returns {Promise}
         */
        getNewPosition(repository, criteria, context, field = 'position') {
            criteria.addAggregation(Criteria.max('maxPosition', field))
                .addSorting(Criteria.sort(field, 'DESC'));

            return repository.search(criteria, context).then((result) => {
                const position = parseInt(result.aggregations.maxPosition.max, 10);

                if (Number.isNaN(position)) {
                    return Promise.resolve(1);
                }

                return Promise.resolve(position + 1);
            });
        },

        /**
         * Lowers the position value bye swapping with the next entity
         *
         * @param {EntityCollection} collection
         * @param {Entity} selectedItem
         * @param {String} field - Name of the position column
         *
         * @returns {EntityCollection}
         */
        lowerPositionValue(collection, selectedItem, field = 'position') {
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
        raisePositionValue(collection, selectedItem, field = 'position') {
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
        changePosition(collection, selectedItem, field = 'position', direction = 'ASC') {
            if (collection.length < 2) {
                return collection;
            }

            collection.sort((a, b) => a[field] - b[field]);
            const itemIndex = collection.findIndex(entity => entity[field] === selectedItem[field]);

            if (
                (direction === 'ASC' && itemIndex < 1) ||
                (direction === 'DESC' && itemIndex >= collection.length - 1)
            ) {
                return collection;
            }

            const siblingAdd = (direction !== 'DESC') ? -1 : 1;

            [collection[itemIndex][field], collection[itemIndex + siblingAdd][field]] =
                [collection[itemIndex + siblingAdd][field], collection[itemIndex][field]];

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
        getSiblingIndex(collection, selectedItem, field = 'position', direction = 'ASC') {
            if (collection.length < 2) {
                return -1;
            }

            collection.sort((a, b) => a[field] - b[field]);
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
        getSibling(collection, selectedItem, field = 'position', direction = 'ASC') {
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
        renumberPositions(collection, startIndex = 0, field = 'position') {
            collection.sort((a, b) => a[field] - b[field]);

            let i = startIndex;
            collection.forEach((item) => {
                item[field] = i;
                i += 1;
            });

            return collection;
        },
    },
});
