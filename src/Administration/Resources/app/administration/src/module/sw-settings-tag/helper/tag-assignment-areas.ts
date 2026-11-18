/**
 * @sw-package inventory
 */
import type { ReadableArea } from 'src/core/helper/readable-areas.helper';

type TagDefinitionProperties = Record<string, { relation?: string; entity?: string }>;

/**
 * An area a tag can be assigned to, one per many-to-many association of `tag`.
 * See `ReadableArea` for when an area counts as readable.
 *
 * @private
 */
export type TagAssignmentArea = ReadableArea & {
    /**
     * Entity the tag is assigned to, for example `product`.
     */
    entity: string;
};

/**
 * Associations the assignment list loads in addition to the assigned entity itself.
 */
const ADDITIONAL_ENTITIES: Record<string, string[]> = {
    products: [
        'property_group_option',
        'property_group',
    ],
    orders: ['order_customer'],
};

/**
 * Builds the areas from the tag definition, so associations added by extensions are covered too.
 *
 * @private
 */
export function getTagAssignmentAreas(properties: TagDefinitionProperties): TagAssignmentArea[] {
    return Object.entries(properties).flatMap(([propertyName, property]) => {
        if (property.relation !== 'many_to_many' || !property.entity) {
            return [];
        }

        return [
            {
                key: propertyName,
                label: `sw-settings-tag.detail.assignments.${propertyName}`,
                entity: property.entity,
                entities: [
                    property.entity,
                    ...(ADDITIONAL_ENTITIES[propertyName] ?? []),
                ],
            },
        ];
    });
}
