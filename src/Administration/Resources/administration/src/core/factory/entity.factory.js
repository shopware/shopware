/**
 * @module core/factory/entity
 */
import { warn } from 'src/core/service/utils/debug.utils';

export default {
    addEntityDefinition,
    getEntityDefinition,
    getDefinitionRegistry,
    getRawEntityObject,
    getRequiredProperties
};

/**
 * Registry which holds all entity definitions.
 *
 * @type {Map<String, Object>}
 */
const entityDefinitions = new Map();

/**
 * @param {String} entityName
 * @param {Object} entityDefinition
 * @returns {boolean}
 */
function addEntityDefinition(entityName, entityDefinition = {}) {
    if (!entityName || !entityName.length) {
        return false;
    }

    entityDefinitions.set(entityName, entityDefinition);
    return true;
}

/**
 * Get an entity definition by name.
 *
 * @param {String} entityName
 * @returns {Object}
 */
function getEntityDefinition(entityName) {
    return entityDefinitions.get(entityName);
}

/**
 * Get the complete entity definition registry.
 *
 * @returns {Map<any, any>}
 */
function getDefinitionRegistry() {
    return entityDefinitions;
}

/**
 * Get a raw object containing all properties of the given entity with empty values.
 *
 * @param {String} entityName
 * @param {Boolean} includeObjectAssociations
 * @returns {Object}
 */
function getRawEntityObject(entityName, includeObjectAssociations = false) {
    if (!entityDefinitions.has(entityName)) {
        return {};
    }

    const definition = entityDefinitions.get(entityName);
    const entity = {};

    Object.keys(definition.properties).forEach((propertyName) => {
        const property = definition.properties[propertyName];

        if (property.type === 'array' || property.type === 'json_array') {
            entity[propertyName] = [];
        } else if (property.type === 'json_object') {
            /**
             * Set up a trap for json objects, because wo don't know its properties.
             */
            entity[propertyName] = new Proxy({}, {
                get(target, key) {
                    return target[key] || null;
                }
            });
        } else if (property.type === 'object') {
            if (property.entity && includeObjectAssociations) {
                entity[propertyName] = getRawEntityObject(property.entity);
            } else {
                entity[propertyName] = {};
            }
        } else if (property.type === 'boolean') {
            entity[propertyName] = false;
        } else if (property.type === 'string' || property.type === 'number' || property.type === 'integer') {
            entity[propertyName] = null;
        } else {
            warn('EntityFactory', `Unknown property type ${property.type} in ${entityName} entity.`, definition);
            entity[propertyName] = null;
        }
    });

    return entity;
}

/**
 * Get a list of all entity properties which are required.
 *
 * @param {String} entityName
 * @returns {Array}
 */
function getRequiredProperties(entityName) {
    if (!entityDefinitions.has(entityName)) {
        return [];
    }

    const definition = entityDefinitions.get(entityName);
    return definition.required;
}
