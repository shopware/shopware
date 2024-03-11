/**
 * @package admin
 */

import EntityDefinition, { getScalarTypes, getJsonTypes } from 'src/core/data/entity-definition.data';

/**
 * @private
 */
export default {
    getScalarTypes,
    getJsonTypes,
    getDefinitionRegistry,
    has,
    get,
    add,
    remove,
    getTranslatedFields,
    getAssociationFields,
    getRequiredFields,
};

const entityDefinitionRegistry = new Map();

function getDefinitionRegistry() {
    return entityDefinitionRegistry;
}

/**
 * Checks the EntityDefinition object for a given entity
 * @param entityName
 * @returns {Boolean}
 */
function has(entityName) {
    return entityDefinitionRegistry.has(entityName);
}

/**
 * returns the EntityDefinition object for a given entity
 * @param entityName
 * @returns {EntityDefinition}
 */
function get(entityName) {
    const definition = entityDefinitionRegistry.get(entityName);

    if (typeof definition === 'undefined') {
        throw new Error(`[EntityDefinitionRegistry] No definition found for entity type ${entityName}`);
    }

    return definition;
}

/**
 * takes a plain schema object and converts it to a shopware EntityDefinition
 * @param entityName
 * @param schema
 */
function add(entityName, schema) {
    entityDefinitionRegistry.set(entityName, new EntityDefinition(schema));
}

/**
 * removes an entity definition from the registry
 * @param entityName
 * @returns {boolean}
 */
function remove(entityName) {
    return entityDefinitionRegistry.delete(entityName);
}

function getTranslatedFields(entityName) {
    return get(entityName).getTranslatableFields();
}

function getAssociationFields(entityName) {
    return get(entityName).getAssociationFields();
}

function getRequiredFields(entityName) {
    return get(entityName).getRequiredFields();
}
