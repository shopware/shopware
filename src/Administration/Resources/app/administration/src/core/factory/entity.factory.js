/**
 * @module core/factory/entity
 */
import { hasOwnProperty } from 'src/core/service/utils/object.utils';

export default {
    addEntityDefinition,
    getEntityDefinition,
    getDefinitionRegistry,
    getRawEntityObject,
    getPropertyBlacklist,
    getRequiredProperties,
    getAssociatedProperties,
    getTranslatableProperties,
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
 * Returns a raw entity object by its schema with empty properties.
 *
 * @param {Object} schema
 * @param {Boolean} deep
 * @return {{}}
 */
function getRawEntityObject(schema, deep = true) {
    const properties = schema.properties;
    const obj = {};

    Object.keys(properties).forEach((property) => {
        const propSchema = properties[property];

        obj[property] = getRawPropertyValue(propSchema, deep);
    });

    return obj;
}

/**
 * Returns the default value for a property type to symbolize an empty state.
 *
 * @param {Object} propSchema
 * @param {Boolean} deep
 * @return {*}
 */
function getRawPropertyValue(propSchema, deep = true) {
    if (propSchema.type === 'boolean') {
        return null;
    }

    if (propSchema.type === 'string') {
        return '';
    }

    if (propSchema.type === 'number' || propSchema.type === 'integer') {
        return null;
    }

    if (propSchema.type === 'array') {
        return [];
    }

    // OneToOne Relation
    if (propSchema.type === 'object' && propSchema.entity) {
        if (deep === true) {
            return getRawEntityObject(getEntityDefinition(propSchema.entity), false);
        }

        return {};
    }

    // JSON Field
    if (propSchema.type === 'object') {
        if (deep === true && propSchema.properties) {
            return getRawEntityObject(propSchema, false);
        }

        return {};
    }

    if (propSchema.type === 'string' && propSchema.format === 'date-time') {
        return '';
    }

    return null;
}

function getPropertyBlacklist() {
    return [
        'createdAt',
        'updatedAt',
        'uploadedAt',
        'childCount',
        'versionId',
        'links',
        'extensions',
        'mimeType',
        'fileExtension',
        'metaData',
        'fileSize',
        'fileName',
        'mediaType',
        'mediaFolder',
    ];
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
    const blacklist = getPropertyBlacklist();
    const requiredFields = [];

    definition.required.forEach((property) => {
        if (!blacklist.includes(property)) {
            requiredFields.push(property);
        }
    });

    return requiredFields;
}

/**
 * Get a list of all entity properties which are translatable.
 *
 * @param {String} entityName
 * @return {Array}
 */
function getTranslatableProperties(entityName) {
    if (!entityDefinitions.has(entityName)) {
        return [];
    }

    const definition = entityDefinitions.get(entityName);

    return definition.translatable;
}

/**
 * Returns the associated properties of an entity.
 *
 * @param {String} entityName
 * @returns {Array}
 */
function getAssociatedProperties(entityName) {
    const definition = entityDefinitions.get(entityName);

    return Object.keys(definition.properties).reduce((accumulator, propName) => {
        const prop = definition.properties[propName];
        if (prop.type === 'array' && hasOwnProperty(prop, 'entity')) {
            accumulator.push(propName);
        }

        return accumulator;
    }, []);
}
