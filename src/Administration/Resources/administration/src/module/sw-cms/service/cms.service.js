import { Application, Entity } from 'src/core/shopware';

Application.addServiceProvider('cmsService', () => {
    return {
        registerCmsElement,
        registerCmsBlock,
        getCmsElementConfigByName,
        getCmsBlockConfigByName,
        getCmsElementRegistry,
        getCmsBlockRegistry,
        getEntityMappingTypes,
        getPropertyByMappingPath
    };
});

const elementRegistry = {};
const blockRegistry = {};
const mappingTypesCache = {};

function registerCmsElement(config) {
    if (!config.name || !config.component) {
        return false;
    }

    elementRegistry[config.name] = config;
    return true;
}

function getCmsElementConfigByName(name) {
    return elementRegistry[name];
}

function getCmsElementRegistry() {
    return elementRegistry;
}

function registerCmsBlock(config) {
    if (!config.name || !config.component) {
        return false;
    }

    blockRegistry[config.name] = config;
    return true;
}

function getCmsBlockConfigByName(name) {
    return blockRegistry[name];
}

function getCmsBlockRegistry() {
    return blockRegistry;
}

function getEntityMappingTypes(entityName = null) {
    const schema = Entity.getDefinition(entityName);

    if (entityName === null || typeof schema === 'undefined') {
        return {};
    }

    if (typeof mappingTypesCache[entityName] === 'undefined') {
        mappingTypesCache[entityName] = {};
        handlePropertyMappings(schema.properties, mappingTypesCache[entityName], entityName);
    }

    return mappingTypesCache[entityName];
}

function handlePropertyMappings(propertyDefinitions, mappings, pathPrefix, deep = true) {
    const blacklist = ['parent'];
    const formatBlacklist = ['uuid'];

    Object.keys(propertyDefinitions).forEach((property) => {
        const propSchema = propertyDefinitions[property];

        if (blacklist.includes(property) || propSchema.readOnly === true) {
            return;
        }

        if (propSchema.format && formatBlacklist.includes(propSchema.format)) {
            return;
        }

        // ToDo: Just a workaround because of issue NEXT-2617
        if (propSchema.type === 'string' && property.includes('Id')) {
            return;
        }

        if (propSchema.type === 'object') {
            if (propSchema.properties) {
                handlePropertyMappings(
                    propSchema.properties,
                    mappings,
                    `${pathPrefix}.${property}`,
                    false
                );
            } else if (propSchema.entity && deep === true) {
                const schema = Entity.getDefinition(propSchema.entity);

                if (schema) {
                    handlePropertyMappings(
                        schema.properties,
                        mappings,
                        `${pathPrefix}.${property}`,
                        false
                    );
                }
            }
        } else if (propSchema.type === 'array') {
            if (propSchema.entity) {
                if (!mappings.entity) {
                    mappings.entity = [];
                }

                mappings.entity.push(`${pathPrefix}.${property}`);
            }
        } else {
            if (!mappings[propSchema.type]) {
                mappings[propSchema.type] = [];
            }

            mappings[propSchema.type].push(`${pathPrefix}.${property}`);
        }
    });
}

function getPropertyByMappingPath(entity, propertyPath) {
    const path = propertyPath.split('.');

    path.splice(0, 1);

    return path.reduce((obj, key) => {
        if (obj === null ||
            typeof obj !== 'object' ||
            typeof obj[key] === 'undefined') {
            return null;
        }

        return obj[key];
    }, entity);
}
