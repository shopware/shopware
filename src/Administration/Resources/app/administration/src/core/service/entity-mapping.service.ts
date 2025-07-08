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
    [key: string]: unknown;
}

interface EntitySchema {
    properties: Record<string, PropertySchema>;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type EntityNameMapping = Record<string, string>;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type PropertyDefinition = Record<string, PropertySchema>;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    getEntityMapping,
};

const { cloneDeep } = Shopware.Utils.object;

const mappingTypesCache: Record<string, PropertyDefinition> = {};

function getEntityMapping(entityName?: string, entityNameMapping?: EntityNameMapping): PropertyDefinition {
    let schema: EntitySchema = {
        properties: {},
    };

    if (typeof entityName === 'undefined') {
        entityName = '';
    }

    if (entityNameMapping && Object.keys(entityNameMapping).length > 0) {
        Object.entries(entityNameMapping).forEach(
            ([
                mappedKey,
                mappedValue,
            ]) => {
                schema.properties[mappedKey] = {
                    entity: mappedValue,
                    type: 'object',
                };
            },
        );
    } else {
        return schema.properties;
    }

    if (entityName.indexOf('.') < 1) {
        return schema.properties;
    }

    const parts = entityName.split('.');
    let lastEntityName = '';
    let lastVal = lastEntityName;

    parts.forEach((val) => {
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
                mappingTypesCache[lastEntityName] = handlePropertyMappings(schema.properties);
            }
        }

        // Handle json_object type
        if (property?.type === 'json_object') {
            lastEntityName = dubbedVal;

            if (typeof mappingTypesCache[lastEntityName] === 'undefined' && property.properties) {
                mappingTypesCache[lastEntityName] = {};
                mappingTypesCache[lastEntityName] = handlePropertyMappings(property.properties);
            }
        }

        lastVal = dubbedVal;
    });

    if (lastVal === lastEntityName || !mappingTypesCache[lastEntityName]) {
        return {};
    }

    return mappingTypesCache[lastEntityName];
}

function handlePropertyMappings(propertyDefinitions: PropertyDefinition): PropertyDefinition {
    const blocklist: string[] = [];
    const formatBlocklist: string[] = ['uuid'];

    // Deep clone to avoid mutation
    const clonedMapping = cloneDeep(propertyDefinitions);

    Object.keys(propertyDefinitions).forEach((property) => {
        const propSchema = propertyDefinitions[property];

        if (blocklist.includes(property) || propSchema.readOnly === true) {
            delete clonedMapping[property];
            return;
        }

        if (propSchema.format && formatBlocklist.includes(propSchema.format)) {
            delete clonedMapping[property];
            return;
        }

        if (propSchema.type === 'array') {
            clonedMapping[property.concat('[0]')] = clonedMapping[property];
            delete clonedMapping[property];
        }
    });

    return clonedMapping;
}
