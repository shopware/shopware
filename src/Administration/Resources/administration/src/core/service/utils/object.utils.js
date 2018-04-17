/**
 * @module core/service/utils/object
 */

import type from './types.utils';

export default {
    deepCopyObject,
    getObjectChangeSet,
    getAssociatedDeletions,
    getPropertyBlacklist
};

/**
 * Deep copy an object
 *
 * @param {Object} copyObject
 * @returns {Object}
 */
export function deepCopyObject(copyObject = {}) {
    return JSON.parse(JSON.stringify(copyObject));
}

/**
 * Compares two entity versions and returns all deleted associations of that entity.
 *
 * @param baseObject
 * @param compareObject
 * @param entitySchemaName
 * @returns {{}}
 */
export function getAssociatedDeletions(baseObject, compareObject, entitySchemaName) {
    if (!baseObject || !compareObject || !entitySchemaName) {
        return {};
    }

    const entitySchema = Shopware.Entity.getDefinition(entitySchemaName);
    const entityProperties = Object.keys(entitySchema.properties);

    const b = { ...baseObject };
    const c = { ...compareObject };

    return Object.keys(b).reduce((acc, key) => {
        if (!entityProperties.includes(key)) {
            return { ...acc };
        }

        const property = entitySchema.properties[key];
        let associatedEntity = null;

        if (property.entity && property.entity.length) {
            associatedEntity = property.entity;
        }

        // The property does not exist on the compare object, so it is a direct deletion.
        if (typeof c[key] === 'undefined' && associatedEntity !== null) {
            return { ...acc, [key]: b[key] };
        }

        if (type.isArray(b[key]) && associatedEntity !== null) {
            const arrayDeletions = [];

            b[key].forEach((item) => {
                if (type.isObject(item) && item.id) {
                    if (typeof c[key].find((compareItem) => compareItem.id === item.id) === 'undefined') {
                        arrayDeletions.push(item);
                    }
                }
            });

            if (arrayDeletions.length > 0) {
                return { ...acc, [key]: arrayDeletions };
            }

            return { ...acc };
        }

        return { ...acc };
    }, {});
}

/**
 * Compares to objects recursively and returns a new object including the changeset.
 * You can optionally pass the name of an entity to validate all properties against the entity schema.
 *
 * @param baseObject
 * @param compareObject
 * @param entitySchemaName
 * @returns {*}
 */
export function getObjectChangeSet(baseObject, compareObject, entitySchemaName = null) {
    // Both objects or properties are the same, so there is no change.
    if (baseObject === compareObject) {
        return {};
    }

    // The passed properties are also no comparable objects so there must be a change.
    if (!type.isObject(baseObject) || !type.isObject(compareObject)) {
        return compareObject;
    }

    // Handle the special case of date properties.
    if (type.isDate(baseObject) || type.isDate(compareObject)) {
        if (baseObject.valueOf() === compareObject.valueOf()) {
            return {};
        }

        return compareObject;
    }

    let entitySchema = null;
    let entityProperties = null;

    if (entitySchemaName !== null) {
        entitySchema = Shopware.Entity.getDefinition(entitySchemaName) || null;

        if (entitySchema !== null) {
            entityProperties = Object.keys(entitySchema.properties);
        }
    }

    const b = { ...baseObject };
    const c = { ...compareObject };

    // Iterate through all properties of the compare object and check for differences.
    return Object.keys(c).reduce((acc, key) => {
        let property = null;
        let associatedEntity = null;

        // If there is a given entity schema definition, validate the property against the schema.
        if (entityProperties !== null) {
            // When the property is not a part of the definition, it will not be considered.
            if (!entityProperties.includes(key)) {
                return { ...acc };
            }

            property = entitySchema.properties[key];

            // If the property is an associated entity the recursive call will also validate the associated schema.
            if (property.entity && property.entity.length) {
                associatedEntity = property.entity;
            }

            // If the type of the property is one of the json fields, it will get special treatment.
            if (isJsonFieldProp(property.type)) {
                return handleJsonFieldProp(b[key], c[key], acc, key);
            }
        }

        // If the property is not present on the base object, it is an addition from the compare object.
        if (!b.hasOwnProperty(key)) {
            const addition = validateObjectSchema(c[key], associatedEntity);

            if (hasNoChanges(addition)) {
                return { ...acc };
            }

            return { ...acc, [key]: addition };
        }

        // If the property is an array, we also try to find changes in the array items.
        if (type.isArray(b[key])) {
            return handleArrayProp(b[key], c[key], acc, key, associatedEntity);
        }

        // Recursively get changes of nested object properties.
        const diff = getObjectChangeSet(b[key], c[key], associatedEntity);

        // When there are no actual changes, the property is not considered for the changeset.
        if (hasNoChanges(diff)) {
            return { ...acc };
        }

        // When the compared objects have their own "id" property, we assume that it is an association.
        if (type.isObject(b[key]) && b[key].id) {
            // Changes to associated entities always need the id for reference.
            diff.id = b[key].id;
        }

        return { ...acc, [key]: diff };
    }, {});
}

/**
 * Compares two arrays and their items to generate a changeset.
 *
 * @param baseArray
 * @param compareArray
 * @param entitySchemaName
 * @returns {*}
 */
function getArrayChangeSet(baseArray, compareArray, entitySchemaName = null) {
    if (baseArray === compareArray) {
        return [];
    }

    // The passed properties are no comparable arrays so there must be a change.
    if (!type.isArray(baseArray) || !type.isArray(compareArray)) {
        return compareArray;
    }

    // If there are no items in the compare array, there are no changes.
    // Deletions are handled separately.
    if (compareArray.length === 0) {
        return [];
    }

    const b = [...baseArray];
    const c = [...compareArray];

    // If the items of the arrays are no comparable objects, we simply get the additions.
    if (!type.isObject(c[0])) {
        return c.filter(value => b.indexOf(value) < 0);
    }

    const diff = [];

    // If the arrays have comparable items, we try to also get their changes.
    c.forEach((item, index) => {
        // If the items have no identifier property we compare all items simply based on the index.
        if (!item.id) {
            const diffObject = getObjectChangeSet(b[index], c[index], entitySchemaName);

            if (type.isObject(diffObject) && !type.isEmpty(diffObject)) {
                diff.push(diffObject);
            }
        // If there is an identifier we compare exactly the corresponding items and generate a changeset.
        } else {
            const compareObject = b.find((compareItem) => {
                return item.id === compareItem.id;
            });

            // If the base array does not contain the item, it is an addition.
            if (!compareObject) {
                const addition = validateObjectSchema(item, entitySchemaName);

                if (!hasNoChanges(addition)) {
                    diff.push(addition);
                }
            // If both arrays contain the same item, we generate the changeset for them.
            } else {
                const diffObject = getObjectChangeSet(compareObject, item, entitySchemaName);

                if (type.isObject(diffObject) && !type.isEmpty(diffObject)) {
                    diff.push({ ...diffObject, id: item.id });
                }
            }
        }
    });

    return diff;
}

/**
 * Some properties are read-only because they are handled by the server.
 * They should not be set by the client and have to be removed from entity objects.
 *
 * @returns {string[]}
 */
function getPropertyBlacklist() {
    return ['createdAt', 'updatedAt'];
}

function hasNoChanges(diff) {
    return type.isObject(diff) && type.isEmpty(diff) && !type.isDate(diff);
}

function isJsonFieldProp(propertyType) {
    return ['json_object', 'json_array'].includes(propertyType);
}

function handleArrayProp(baseProp, compareProp, accumulator, propName, entitySchemaName = null) {
    const arrayDiff = getArrayChangeSet(baseProp, compareProp, entitySchemaName);

    if (type.isArray(arrayDiff) && arrayDiff.length === 0) {
        return { ...accumulator };
    }

    return { ...accumulator, [propName]: arrayDiff };
}

function handleJsonFieldProp(baseProp, compareProp, accumulator, propName) {
    if (type.isObject(baseProp)) {
        const jsonObjectDiff = getObjectChangeSet(baseProp, compareProp);

        if (hasNoChanges(jsonObjectDiff)) {
            return { ...accumulator };
        }

        const jsonObject = Object.assign(jsonObjectDiff, compareProp);

        return { ...accumulator, [propName]: jsonObject };
    }

    if (type.isArray(baseProp)) {
        return { ...accumulator, [propName]: [...compareProp] };
    }

    return { ...accumulator };
}

function validateObjectSchema(obj, entitySchemaName) {
    if (!type.isObject(obj) || !entitySchemaName) {
        return obj;
    }

    const entitySchema = Shopware.Entity.getDefinition(entitySchemaName);
    const entityProperties = Object.keys(entitySchema.properties);
    const blacklist = getPropertyBlacklist();

    return Object.keys(obj).reduce((acc, property) => {
        if (blacklist.includes(property) ||
            hasNoChanges(obj[property]) ||
            !entityProperties.includes(property)) {
            return { ...acc };
        }

        return { ...acc, [property]: obj[property] };
    }, {});
}
