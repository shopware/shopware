const { Application } = Shopware;
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
        getCollectFunction,
        isBlockAllowedInPageType,
        isElementAllowedInPageType,
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

            let entityCount = 0;
            Object.keys(elem.config).forEach((configKey) => {
                if (['mapped', 'default'].includes(elem.config[configKey].source)) {
                    return;
                }

                const entity = elem.config[configKey].entity;

                if (entity && elem.config[configKey].value) {
                    const entityKey = `entity-${entity.name}-${entityCount}`;
                    entityCount += 1;

                    const entityData = getEntityData(elem, configKey);

                    entityData.searchCriteria.setIds(entityData.value);

                    criteriaList[entityKey] = entityData;
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

            let entityCount = 0;
            Object.keys(elem.config).forEach((configKey) => {
                const entity = elem.config[configKey].entity;

                if (!entity) {
                    return;
                }

                const entityKey = `entity-${entity.name}-${entityCount}`;
                if (!data[entityKey]) {
                    return;
                }

                entityCount += 1;

                if (Array.isArray(elem.config[configKey].value)) {
                    elem.data[configKey] = [];

                    elem.config[configKey].value.forEach((value) => {
                        elem.data[configKey].push(data[entityKey].get(value));
                    });
                } else {
                    elem.data[configKey] = data[entityKey].get(elem.config[configKey].value);
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

    entityData.searchCriteria = entity.criteria ? entity.criteria : new Criteria(1, 25);

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
    const schema = Shopware.EntityDefinition.has(entityName) ? Shopware.EntityDefinition.get(entityName) : undefined;

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
    const blocklist = ['parent', 'cmsPage', 'translations', 'createdAt', 'updatedAt'];

    Object.keys(propertyDefinitions).forEach((property) => {
        const propSchema = propertyDefinitions[property];

        if (
            blocklist.includes(property) ||
            (Array.isArray(propSchema?.flags?.write_protected) && propSchema.type !== 'association')
        ) {
            return;
        }

        if (propSchema.type === 'association' && ['many_to_one', 'one_to_one'].includes(propSchema.relation)) {
            if (propSchema.entity) {
                addToMappingEntity(mappings, propSchema, pathPrefix, property);

                if (deep === true) {
                    const schema = Shopware.EntityDefinition.get(propSchema.entity);

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
        } else if (propSchema.type === 'association' && ['one_to_many', 'many_to_many'].includes(propSchema.relation)) {
            if (propSchema.entity) {
                addToMappingEntity(mappings, propSchema, pathPrefix, property);
            }
        } else {
            let schemaType = propSchema.type;

            if (['uuid', 'text', 'date'].includes(schemaType)) {
                schemaType = 'string';
            } else if (['float'].includes(schemaType)) {
                schemaType = 'number';
            } else if (['int'].includes(schemaType)) {
                schemaType = 'integer';
            }

            if (['blob', 'json_object', 'json_list'].includes(schemaType)) {
                return;
            }

            if (!mappings[schemaType]) {
                mappings[schemaType] = [];
            }

            mappings[schemaType].push(`${pathPrefix}.${property}`);
        }
    });
}

function addToMappingEntity(mappings, propSchema, pathPrefix, property) {
    if (!mappings.entity) {
        mappings.entity = {};
    }

    if (!mappings.entity[propSchema.entity]) {
        mappings.entity[propSchema.entity] = [];
    }

    if (propSchema.flags?.extension) {
        mappings.entity[propSchema.entity].push(`${pathPrefix}.extensions.${property}`);
    } else {
        mappings.entity[propSchema.entity].push(`${pathPrefix}.${property}`);
    }
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

function getCollectFunction() {
    return function collect(elem) {
        const context = {
            ...Shopware.Context.api,
            inheritance: true,
        };

        const criteriaList = {};

        let entityCount = 0;
        Object.keys(elem.config).forEach((configKey) => {
            if (['mapped', 'default'].includes(elem.config[configKey].source)) {
                return;
            }

            const entity = elem.config[configKey].entity;

            if (entity && elem.config[configKey].value) {
                const entityKey = `${entity.name}-${entityCount}`;
                entityCount += 1;

                const entityData = {
                    value: [elem.config[configKey].value].flat(),
                    key: configKey,
                    searchCriteria: entity.criteria ? entity.criteria : new Criteria(1, 25),
                    ...entity,
                };

                entityData.searchCriteria.setIds(entityData.value);
                entityData.context = context;

                criteriaList[`entity-${entityKey}`] = entityData;
            }
        });

        return criteriaList;
    };
}

function isBlockAllowedInPageType(blockName, pageType) {
    const allowedPageTypes = blockRegistry[blockName]?.allowedPageTypes;

    if (!Array.isArray(allowedPageTypes)) {
        return true;
    }

    return allowedPageTypes.includes(pageType);
}


function isElementAllowedInPageType(elementName, pageType) {
    const allowedPageTypes = elementRegistry[elementName]?.allowedPageTypes;

    if (!Array.isArray(allowedPageTypes)) {
        return true;
    }

    return allowedPageTypes.includes(pageType);
}
