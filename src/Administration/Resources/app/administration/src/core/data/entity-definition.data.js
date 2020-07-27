const scalarTypes = ['uuid', 'int', 'text', 'password', 'float', 'string', 'blob', 'boolean', 'date'];
const jsonTypes = ['json_list', 'json_object'];

export function getScalarTypes() {
    return scalarTypes;
}

export function getJsonTypes() {
    return jsonTypes;
}

export default class EntityDefinition {
    constructor({ entity, properties }) {
        this.entity = entity;
        this.properties = properties;
    }

    getEntity() {
        return this.entity;
    }

    /**
     * returns an Object containing all primary key fields of the definition
     * @returns {Object}
     */
    getPrimaryKeyFields() {
        return this.filterProperties((property) => {
            return property.flags.primary_key === true;
        });
    }

    /**
     * returns an Object containing all associations fields of this definition
     * @returns {Object}
     */
    getAssociationFields() {
        return this.filterProperties((property) => {
            return property.type === 'association';
        });
    }

    /**
     * returns all toMany associationFields
     * @returns {Object}
     */
    getToManyAssociations() {
        return this.filterProperties((property) => {
            if (property.type !== 'association') {
                return false;
            }

            return ['one_to_many', 'many_to_many'].includes(property.relation);
        });
    }

    /**
     * returns all toMany associationFields
     * @returns {Object}
     */
    getToOneAssociations() {
        return this.filterProperties((property) => {
            if (property.type !== 'association') {
                return false;
            }

            return ['one_to_one', 'many_to_one'].includes(property.relation);
        });
    }

    /**
     * returns all translatable fields
     * @returns {Object}
     */
    getTranslatableFields() {
        return this.filterProperties((property) => {
            return this.isTranslatableField(property);
        });
    }

    /**
     *
     * @returns {Object}
     */
    getRequiredFields() {
        return this.filterProperties((property) => {
            return property.flags.required === true;
        });
    }

    /**
     * Filter field definitions by a given predicate
     * @param {Function} filter
     */
    filterProperties(filter) {
        if (typeof filter !== 'function') {
            return {};
        }

        const result = {};
        Object.keys(this.properties).forEach((propertyName) => {
            if (filter(this.properties[propertyName]) === true) {
                result[propertyName] = this.properties[propertyName];
            }
        });

        return result;
    }

    getField(name) {
        return this.properties[name];
    }

    forEachField(callback) {
        if (typeof callback !== 'function') {
            return;
        }

        Object.keys(this.properties).forEach((propertyName) => {
            callback(this.properties[propertyName], propertyName, this.properties);
        });
    }

    isScalarField(field) {
        return scalarTypes.includes(field.type);
    }

    isJsonField(field) {
        return jsonTypes.includes(field.type);
    }

    isToManyAssociation(field) {
        return field.type === 'association' && ['one_to_many', 'many_to_many'].includes(field.relation);
    }

    isToOneAssociation(field) {
        return field.type === 'association' && ['many_to_one', 'one_to_one'].includes(field.relation);
    }

    isTranslatableField(field) {
        return (field.type === 'string' || field.type === 'text') && field.flags.translatable === true;
    }
}
