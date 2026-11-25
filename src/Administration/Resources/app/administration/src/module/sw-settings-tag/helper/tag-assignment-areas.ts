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

    /**
     * Associations the assignment list loads with the assigned entities.
     */
    associations: string[];
};

/**
 * Associations the assignment list loads, with the entities they read.
 */
const ADDITIONAL_ASSOCIATIONS: Record<string, { associations: string[]; entities: string[] }> = {
    products: {
        associations: ['options.group'],
        entities: [
            'property_group_option',
            'property_group',
        ],
    },
    orders: {
        associations: ['orderCustomer'],
        entities: ['order_customer'],
    },
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

        const additional = ADDITIONAL_ASSOCIATIONS[propertyName];

        return [
            {
                key: propertyName,
                label: `sw-settings-tag.detail.assignments.${propertyName}`,
                entity: property.entity,
                associations: additional?.associations ?? [],
                entities: [
                    property.entity,
                    ...(additional?.entities ?? []),
                ],
            },
        ];
    });
}
