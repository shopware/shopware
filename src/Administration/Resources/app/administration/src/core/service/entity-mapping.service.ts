/**
 * @sw-package framework
 *
 * @module core/service/validation
 */

interface PropertySchema {
    type?: string;
    entity?: string;
    format?: string;
    readOnly?: boolean;
    properties?: Record<string, PropertySchema>;
    [key: string]: any;
}

interface EntitySchema {
    properties: Record<string, PropertySchema>;
}

type EntityNameMapping = Record<string, string>;
type PropertyDefinition = Record<string, PropertySchema>;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    getEntityMapping,
};

const mappingTypesCache: Record<string, PropertyDefinition> = {};

function getEntityMapping(
    entityName?: string,
    entityNameMapping?: EntityNameMapping
): PropertyDefinition {
    let schema: EntitySchema = {
        properties: {},
    };

    if (typeof entityName === 'undefined') {
        entityName = '';
    }

    if (entityNameMapping && Object.keys(entityNameMapping).length > 0) {
        for (const mappedKey of Object.keys(entityNameMapping)) {
            schema.properties[mappedKey] = {
                entity: entityNameMapping[mappedKey],
                type: 'object',
            };
        }
    } else {
        return schema.properties;
    }

    if (entityName.indexOf('.') < 1) {
        return schema.properties;
    }

    const parts = entityName.split('.');
    let lastEntityName = '';
    let lastVal = lastEntityName;

    for (const val of parts) {
        const cleanVal = val.replace(/\[.*\]/, '');
        const dubbedVal = val.replace(/\[.*\]/, '[0]');

        if (val === '') {
            lastEntityName = lastVal;
        }

        const property = schema.properties[cleanVal];

        // Handle entity mapping
        if (property?.entity) {
            const entityDef = Shopware.EntityDefinition.getDefinitionRegistry().get(property.entity) as EntitySchema;
            schema = entityDef;
            lastEntityName = dubbedVal;

            if (typeof mappingTypesCache[lastEntityName] === 'undefined') {
                mappingTypesCache[lastEntityName] = {};
                mappingTypesCache[lastEntityName] = handlePropertyMappings(
                    schema.properties,
                    mappingTypesCache[lastEntityName]
                );
            }
        }

        // Handle json_object type
        if (property?.type === 'json_object') {
            lastEntityName = dubbedVal;

            if (typeof mappingTypesCache[lastEntityName] === 'undefined' && property.properties) {
                mappingTypesCache[lastEntityName] = {};
                mappingTypesCache[lastEntityName] = handlePropertyMappings(
                    property.properties,
                    mappingTypesCache[lastEntityName]
                );
            }
        }

        lastVal = dubbedVal;
    }

    if (lastVal === lastEntityName || !mappingTypesCache[lastEntityName]) {
        return {};
    }

    return mappingTypesCache[lastEntityName];
}

function handlePropertyMappings(
    propertyDefinitions: PropertyDefinition,
    mapping: PropertyDefinition
): PropertyDefinition {
    const blocklist: string[] = [];
    const formatBlocklist: string[] = ['uuid'];

    // Deep clone to avoid mutation
    mapping = JSON.parse(JSON.stringify(propertyDefinitions));

    for (const property of Object.keys(propertyDefinitions)) {
        const propSchema = propertyDefinitions[property];

        if (blocklist.includes(property) || propSchema.readOnly === true) {
            delete mapping[property];
            continue;
        }

        if (propSchema.format && formatBlocklist.includes(propSchema.format)) {
            delete mapping[property];
            continue;
        }

        if (propSchema.type === 'array') {
            mapping[`${property}[0]`] = mapping[property];
            delete mapping[property];
        }
    }

    return mapping;
}
