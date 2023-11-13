/**
 * @package admin
 *
 * @module core/service/validation
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    getEntityMapping,
};

const mappingTypesCache = {};

function getEntityMapping(entityName, entityNameMapping) {
    let schema = {
        properties: {},
    };

    if (typeof entityName === 'undefined') {
        entityName = '';
    }

    if (typeof entityNameMapping !== 'undefined' && Object.keys(entityNameMapping).length > 0) {
        Object.keys(entityNameMapping).forEach((mappedKey) => {
            schema.properties[mappedKey] = {
                entity: entityNameMapping[mappedKey],
                type: 'object',
            };
        });
    } else {
        return schema.properties;
    }

    if (entityName.indexOf('.') < 1) {
        return schema.properties;
    }

    entityName = entityName.split('.');

    let lastEntityName = '';
    let lastVal = lastEntityName;
    if (entityName.length > 0) {
        entityName.forEach((val) => {
            const cleanVal = val.replace(/\[.*\]/, '');
            const dubbedVal = val.replace(/\[.*\]/, '[0]');
            if (val === '') {
                lastEntityName = lastVal;
            }
            if (schema.properties[cleanVal]?.entity) {
                schema = Shopware.EntityDefinition.getDefinitionRegistry().get(schema.properties[cleanVal].entity);
                lastEntityName = dubbedVal;
                if (typeof mappingTypesCache[lastEntityName] === 'undefined') {
                    mappingTypesCache[lastEntityName] = {};
                    mappingTypesCache[lastEntityName] = handlePropertyMappings(
                        schema.properties,
                        mappingTypesCache[lastEntityName],
                    );
                }
            }
            lastVal = dubbedVal;
        });
    }

    if (!mappingTypesCache[lastEntityName]) {
        return {};
    }
    return mappingTypesCache[lastEntityName];
}

function handlePropertyMappings(propertyDefinitions, mapping) {
    const blocklist = [];
    const formatBlocklist = ['uuid'];
    mapping = JSON.parse(JSON.stringify(propertyDefinitions));
    Object.keys(propertyDefinitions).forEach((property) => {
        const propSchema = propertyDefinitions[property];

        if (blocklist.includes(property) || propSchema.readOnly === true) {
            delete (mapping[property]);
            return;
        }

        if (propSchema.format && formatBlocklist.includes(propSchema.format)) {
            delete (mapping[property]);
            return;
        }

        if (propSchema.type === 'array') {
            mapping[property.concat('[0]')] = mapping[property];
            delete (mapping[property]);
        }
    });
    return mapping;
}
