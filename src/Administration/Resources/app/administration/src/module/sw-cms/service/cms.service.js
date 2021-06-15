const { Application, Entity } = Shopware;
const Criteria = Shopware.Data.Criteria;

Application.addServiceProvider('cmsService', () => {
    return {
        registerCmsElement,
        registerCmsBlock,
        getCmsElementConfigByName,
        getCmsBlockConfigByName,
        getCmsElementRegistry,
        getCmsBlockRegistry,
        getEntityMappingTypes,
        getPropertyByMappingPath,
    };
});

const elementRegistry = {};
const blockRegistry = {};
const mappingTypesCache = {};

function registerCmsElement(config) {
    if (!config.name || !config.component || config.flag === false) {
        return false;
    }

    if (!config.collect) {
        config.collect = function collect(elem) {
            const criteriaList = {};

            Object.keys(elem.config).forEach((configKey) => {
                if (elem.config[configKey].source === 'mapped') {
                    return;
                }

                const entity = elem.config[configKey].entity;

                if (entity && elem.config[configKey].value) {
                    const entityKey = entity.name;
                    const entityData = getEntityData(elem, configKey);

                    entityData.searchCriteria.setIds(entityData.value);

                    criteriaList[`entity-${entityKey}`] = entityData;
                }
            });

            return criteriaList;
        };
    }

    if (!config.enrich) {
        config.enrich = function enrich(elem, data) {
            if (Object.keys(data).length < 1) {
                return;
            }

            Object.keys(elem.config).forEach((configKey) => {
                const entity = elem.config[configKey].entity;

                if (!entity) {
                    return;
                }

                const entityKey = entity.name;
                if (!data[`entity-${entityKey}`]) {
                    return;
                }

                if (Array.isArray(elem.config[configKey].value)) {
                    elem.data[configKey] = [];

                    elem.config[configKey].value.forEach((value) => {
                        elem.data[configKey].push(data[`entity-${entityKey}`].get(value));
                    });
                } else {
                    elem.data[configKey] = data[`entity-${entityKey}`].get(elem.config[configKey].value);
                }
            });
        };
    }

    elementRegistry[config.name] = config;

    return true;
}

function getEntityData(element, configKey) {
    const entity = element.config[configKey].entity;
    const configValue = element.config[configKey].value;
    let entityData = {};

    // if multiple entities are given in a slot
    if (Array.isArray(configValue)) {
        const entityIds = [];

        if (configValue.length && configValue[0].mediaId) {
            configValue.forEach((val) => {
                entityIds.push(val.mediaId);
            });
        } else {
            entityIds.push(...configValue);
        }

        entityData = {
            value: entityIds,
            key: configKey,
            ...entity,
        };
    } else {
        entityData = {
            value: [configValue],
            key: configKey,
            ...entity,
        };
    }

    entityData.searchCriteria = entity.criteria ? entity.criteria : new Criteria();

    return entityData;
}

function getCmsElementConfigByName(name) {
    return elementRegistry[name];
}

function getCmsElementRegistry() {
    return elementRegistry;
}

function registerCmsBlock(config) {
    if (!config.name || !config.component || config.flag === false) {
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
    const blacklist = ['parent', 'cmsPage'];
    const formatBlacklist = ['uuid'];

    Object.keys(propertyDefinitions).forEach((property) => {
        const propSchema = propertyDefinitions[property];

        if (blacklist.includes(property) || propSchema.readOnly === true) {
            return;
        }

        if (propSchema.format && formatBlacklist.includes(propSchema.format)) {
            return;
        }

        if (propSchema.type === 'object') {
            if (propSchema.entity) {
                if (!mappings.entity) {
                    mappings.entity = {};
                }

                if (!mappings.entity[propSchema.entity]) {
                    mappings.entity[propSchema.entity] = [];
                }

                mappings.entity[propSchema.entity].push(`${pathPrefix}.${property}`);

                if (deep === true) {
                    const schema = Entity.getDefinition(propSchema.entity);

                    if (schema) {
                        handlePropertyMappings(schema.properties, mappings, `${pathPrefix}.${property}`, false);
                    }
                }
            } else if (propSchema.properties) {
                handlePropertyMappings(
                    propSchema.properties,
                    mappings,
                    `${pathPrefix}.${property}`,
                    false,
                );
            }
        } else if (propSchema.type === 'array') {
            if (propSchema.entity) {
                if (!mappings.entity) {
                    mappings.entity = {};
                }

                if (!mappings.entity[propSchema.entity]) {
                    mappings.entity[propSchema.entity] = [];
                }

                mappings.entity[propSchema.entity].push(`${pathPrefix}.${property}`);
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

        return (obj.translated?.[key]) || obj[key];
    }, entity);
}
