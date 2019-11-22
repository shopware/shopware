import EntityDefinition, { getScalarTypes, getJsonTypes } from 'src/core/data-new/entity-definition.data';

export default {
    getScalarTypes,
    getJsonTypes,
    getDefinitionRegistry,
    get,
    add,
    remove,
    getTranslatedFields,
    getAssociationFields,
    getRequiredFields
};

const entityDefinitionRegistry = new Map();

function getDefinitionRegistry() {
    return entityDefinitionRegistry;
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
 * takes a plain schema object and converts it to an shopware Entitydefinition
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
