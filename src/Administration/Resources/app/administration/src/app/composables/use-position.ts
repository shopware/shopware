/**
 * @sw-package framework
 *
 * @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES
 */
import type Repository from 'src/core/data/repository.data';
import Criteria from 'src/core/data/criteria.data';

/**
 * Composable alternative to the `position` mixin: helpers for the position integers of an entity
 * collection. The mixin's helpers never touched `this` beyond calling each other, so the bodies are
 * duplicated as-is; the mixin stays in place for Options API components.
 *
 * Keep this and `src/app/mixin/position.mixin.ts` in sync — change both together.
 *
 * @private
 */
export default function usePosition(): {
    getNewPosition: <EntityName extends keyof EntitySchema.Entities>(
        repository: Repository<EntityName>,
        criteria: Criteria,
        context: typeof Shopware.Context.api,
        field?: string,
    ) => Promise<number>;
    lowerPositionValue: <EntityName extends keyof EntitySchema.Entities>(
        collection: EntityCollection<EntityName>,
        selectedItem: EntitySchema.Entities[EntityName],
        field?: string,
    ) => EntityCollection<EntityName>;
    raisePositionValue: <EntityName extends keyof EntitySchema.Entities>(
        collection: EntityCollection<EntityName>,
        selectedItem: EntitySchema.Entities[EntityName],
        field?: string,
    ) => EntityCollection<EntityName>;
    changePosition: <EntityName extends keyof EntitySchema.Entities>(
        collection: EntityCollection<EntityName>,
        selectedItem: EntitySchema.Entities[EntityName],
        field?: string,
        direction?: string,
    ) => EntityCollection<EntityName>;
    getSiblingIndex: <EntityName extends keyof EntitySchema.Entities>(
        collection: EntityCollection<EntityName>,
        selectedItem: EntitySchema.Entities[EntityName],
        field?: string,
        direction?: string,
    ) => number;
    getSibling: <EntityName extends keyof EntitySchema.Entities>(
        collection: EntityCollection<EntityName>,
        selectedItem: EntitySchema.Entities[EntityName],
        field?: string,
        direction?: string,
    ) => EntitySchema.Entities[EntityName] | null;
    renumberPositions: <EntityName extends keyof EntitySchema.Entities>(
        collection: EntityCollection<EntityName>,
        startIndex?: number,
        field?: string,
    ) => EntityCollection<EntityName>;
} {
    /**
     * Returns a new position value using the current max position + 1, starting with 1.
     */
    function getNewPosition<EntityName extends keyof EntitySchema.Entities>(
        repository: Repository<EntityName>,
        criteria: Criteria,
        context: typeof Shopware.Context.api,
        field = 'position',
    ): Promise<number> {
        criteria.addAggregation(Criteria.max('maxPosition', field)).addSorting(Criteria.sort(field, 'DESC'));

        return repository.search(criteria, context).then((result) => {
            // @ts-expect-error - maxPosition is defined in addAggregation
            const position = parseInt(result?.aggregations?.maxPosition?.max, 10);

            if (Number.isNaN(position)) {
                return Promise.resolve(1);
            }

            return Promise.resolve(position + 1);
        });
    }

    /**
     * Lowers the position value by swapping with the next entity.
     */
    function lowerPositionValue<EntityName extends keyof EntitySchema.Entities>(
        collection: EntityCollection<EntityName>,
        selectedItem: EntitySchema.Entities[EntityName],
        field = 'position',
    ): EntityCollection<EntityName> {
        return changePosition(collection, selectedItem, field, 'ASC');
    }

    /**
     * Raises the position value by swapping with the next entity.
     */
    function raisePositionValue<EntityName extends keyof EntitySchema.Entities>(
        collection: EntityCollection<EntityName>,
        selectedItem: EntitySchema.Entities[EntityName],
        field = 'position',
    ): EntityCollection<EntityName> {
        return changePosition(collection, selectedItem, field, 'DESC');
    }

    /**
     * Raises/lowers the position value by swapping with the next entity.
     */
    function changePosition<EntityName extends keyof EntitySchema.Entities>(
        collection: EntityCollection<EntityName>,
        selectedItem: EntitySchema.Entities[EntityName],
        field = 'position',
        direction = 'ASC',
    ): EntityCollection<EntityName> {
        if (collection.length < 2) {
            return collection;
        }

        // @ts-expect-error
        collection.sort((a, b) => a[field] - b[field]);
        // @ts-expect-error
        const itemIndex = collection.findIndex((entity) => entity[field] === selectedItem[field]);

        if ((direction === 'ASC' && itemIndex < 1) || (direction === 'DESC' && itemIndex >= collection.length - 1)) {
            return collection;
        }

        const siblingAdd = direction !== 'DESC' ? -1 : 1;

        [
            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            collection[itemIndex][field],
            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            collection[itemIndex + siblingAdd][field],
        ] = [
            // @ts-expect-error
            collection[itemIndex + siblingAdd][field],
            // @ts-expect-error
            collection[itemIndex][field],
        ];

        // @ts-expect-error
        collection.sort((a, b) => a[field] - b[field]);

        return collection;
    }

    /**
     * Gets the index of the sibling next to the selectedItem; direction decides which sibling.
     */
    function getSiblingIndex<EntityName extends keyof EntitySchema.Entities>(
        collection: EntityCollection<EntityName>,
        selectedItem: EntitySchema.Entities[EntityName],
        field = 'position',
        direction = 'ASC',
    ): number {
        if (collection.length < 2) {
            return -1;
        }

        // @ts-expect-error
        collection.sort((a, b) => a[field] - b[field]);
        // @ts-expect-error
        const itemIndex = collection.findIndex((entity) => entity[field] === selectedItem[field]);

        if ((direction === 'ASC' && itemIndex < 1) || (direction === 'DESC' && itemIndex >= collection.length - 1)) {
            return -1;
        }

        const siblingAdd = direction !== 'DESC' ? -1 : 1;

        return itemIndex + siblingAdd;
    }

    /**
     * Gets the sibling next to the selectedItem; direction decides which sibling.
     */
    function getSibling<EntityName extends keyof EntitySchema.Entities>(
        collection: EntityCollection<EntityName>,
        selectedItem: EntitySchema.Entities[EntityName],
        field = 'position',
        direction = 'ASC',
    ): EntitySchema.Entities[EntityName] | null {
        // @ts-expect-error
        collection.sort((a, b) => a[field] - b[field]);

        const index = getSiblingIndex(collection, selectedItem, field, direction);

        if (index === -1) {
            return null;
        }

        return collection[index] || null;
    }

    /**
     * Renumbers all position values incrementally.
     */
    function renumberPositions<EntityName extends keyof EntitySchema.Entities>(
        collection: EntityCollection<EntityName>,
        startIndex = 0,
        field = 'position',
    ): EntityCollection<EntityName> {
        // @ts-expect-error
        collection.sort((a, b) => a[field] - b[field]);

        let i = startIndex;
        collection.forEach((item) => {
            // @ts-expect-error
            item[field] = i;
            i += 1;
        });

        return collection;
    }

    return {
        getNewPosition,
        lowerPositionValue,
        raisePositionValue,
        changePosition,
        getSiblingIndex,
        getSibling,
        renumberPositions,
    };
}
