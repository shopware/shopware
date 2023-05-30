/**
 * @package admin
 */

/* @private */
export interface Property {
    flags?: {
        primary_key?: boolean,
        required?: boolean,
        translatable?: boolean,
    },
    required?: boolean,
    type?: string,
    relation?: 'one_to_one' | 'one_to_many' | 'many_to_one' | 'many_to_many',
    entity?: string,
}

interface Properties {
    [key: string]: Property;
}

const scalarTypes = ['uuid', 'int', 'text', 'password', 'float', 'string', 'blob', 'boolean', 'date'];
const jsonTypes = ['json_list', 'json_object'];

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function getScalarTypes() {
    return scalarTypes;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function getJsonTypes() {
    return jsonTypes;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class EntityDefinition<EntityName extends keyof EntitySchema.Entities> {
    readonly entity: EntitySchema.Entity<EntityName>;

    readonly properties: Properties;

    constructor({ entity, properties }: { entity: EntitySchema.Entity<EntityName>, properties: Properties }) {
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
            return property.flags?.primary_key === true;
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

            return ['one_to_many', 'many_to_many'].includes(property.relation ?? '');
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

            return ['one_to_one', 'many_to_one'].includes(property.relation ?? '');
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
            return property.flags?.required === true;
        });
    }

    /**
     * Filter field definitions by a given predicate
     * @param {Function} filter
     */
    filterProperties(filter: (property: Property) => boolean) {
        if (typeof filter !== 'function') {
            return {};
        }

        const result: Properties = {};
        Object.keys(this.properties).forEach((propertyName) => {
            if (filter(this.properties[propertyName])) {
                result[propertyName] = this.properties[propertyName];
            }
        });

        return result;
    }

    getField(name: string) {
        return this.properties[name];
    }

    forEachField(callback: (property: Property, propertyName: string, properties: Properties) => void) {
        if (typeof callback !== 'function') {
            return;
        }

        Object.keys(this.properties).forEach((propertyName) => {
            callback(this.properties[propertyName], propertyName, this.properties);
        });
    }

    isScalarField(field: Property) {
        return scalarTypes.includes(field.type ?? '');
    }

    isJsonField(field: Property) {
        return jsonTypes.includes(field.type ?? '');
    }

    isJsonObjectField(field: Property) {
        return field.type === 'json_object';
    }

    isJsonListField(field: Property) {
        return field.type === 'json_list';
    }

    isToManyAssociation(field: Property) {
        return field.type === 'association' && ['one_to_many', 'many_to_many'].includes(field.relation ?? '');
    }

    isToOneAssociation(field: Property) {
        return field.type === 'association' && ['many_to_one', 'one_to_one'].includes(field.relation ?? '');
    }

    isTranslatableField(field: Property) {
        return (field.type === 'string' || field.type === 'text') && field.flags?.translatable === true;
    }
}
