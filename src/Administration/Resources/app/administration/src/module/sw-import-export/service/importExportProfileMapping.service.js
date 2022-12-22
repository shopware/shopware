/**
 * @package system-settings
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class ImportExportProfileMappingService {
    constructor(EntityDefinition) {
        this.EntityDefinition = EntityDefinition;
    }

    validate(entityName, mapping, parentMapping = [], isOnlyUpdateProfile = false) {
        const mappingKeys = this.convertMappingKeys(mapping);

        const parentMappingKeys = this.convertMappingKeys(parentMapping);

        const requiredFields = this.EntityDefinition.getRequiredFields(entityName);
        let missingRequiredFields = [];

        // check if mapping contains all required fields
        Object.keys(requiredFields).forEach((fieldName) => {
            // skip translations and price
            if (fieldName === 'translations' || fieldName === 'price') {
                return;
            }

            // skip the required mapping key isn't existing in the default
            if (parentMappingKeys.length > 0) {
                const foundInParentField = parentMappingKeys.some((parentField) => {
                    return fieldName === parentField;
                });

                if (!foundInParentField) {
                    return;
                }
            }

            if (!mappingKeys.includes(fieldName)) {
                missingRequiredFields.push(fieldName);
            }
        });

        if (isOnlyUpdateProfile) {
            const entityDefinition = this.EntityDefinition.get(entityName);
            const primaryKeyFields = entityDefinition.getPrimaryKeyFields();

            missingRequiredFields = missingRequiredFields.filter(
                field => primaryKeyFields[field] !== undefined,
            );
        }

        return { missingRequiredFields };
    }

    convertMappingKeys(mapping) {
        const mappingKeys = mapping.map(field => field.key);

        mappingKeys.forEach((key) => {
            const keyParts = key.split('.');

            if (keyParts.includes('translations')) {
                mappingKeys.push(keyParts.slice(2).join(''));
            }

            // convert key.id to keyId
            const keyHasId = key.split('.id');
            if (keyHasId.length === 2 && keyParts.length === 2) {
                mappingKeys.push(`${keyHasId[0]}Id`);
            }
        });

        return mappingKeys;
    }

    /**
     * Get all the required fields for a given entity.
     * With depth 1 only the required fields of the entityName are given.
     * with greater depth also these of required associations.
     *
     * @param entityName
     * @param depth
     * @returns {{}}
     */
    getSystemRequiredFields(entityName, depth = 1) {
        const definition = this.EntityDefinition.get(entityName);

        return this._getSystemRequiredFieldsForEntityCreation(
            definition,
            depth,
        );
    }

    /**
     * Recursively walk all the EntityDefinition objects until a specified depth from a starting definition.
     * It only looks inside child definitions if there is either a required FkField with ManyToOneAssociation or
     * a required TranslationsAssociationField and it prevents loops of already visited definitions.
     *
     * It constructs all the field paths for each field which is required for
     * the creation of an entity in the starting definition.
     */
    _getSystemRequiredFieldsForEntityCreation(definition, depth = 1, prefix = '', visited = {}) {
        let fields = {};

        if (depth <= 0) {
            return fields;
        }

        if (visited[definition.getEntity()] !== undefined) {
            return fields; // definition already visited - prevent association cycles
        }

        if (prefix.endsWith('translations.DEFAULT.language.')) {
            // skip language associations that come from translations
            // because the default language is used always for required fields
            // otherwise this will show up 'translations.DEFAULT.language.id'
            return fields;
        }

        // remember this definition as visited
        visited[definition.getEntity()] = definition.getEntity();

        definition.forEachField((property, propertyName, properties) => {
            fields = {
                ...fields,
                ...this._handleField(property, propertyName, properties, depth, prefix, visited),
            };
        });

        return fields;
    }

    _handleField(property, propertyName, properties, depth, prefix, visited) {
        let fields = {};

        if (property.type === 'association' && property.relation === 'many_to_one' &&
            properties[property.localField].flags.required === true) {
            // association is many_to_one and required
            fields = {
                ...fields,
                ...this._handleAssociation(property, propertyName, properties, depth, prefix, visited),
            };

            return fields;
        }

        if (property.flags.required !== true) {
            return fields;
        }

        if (propertyName === 'createdAt' || propertyName.toLowerCase().includes('versionid')) {
            return fields; // skip fields that are not relevant for import export
        }

        // translatable fields are visited explicit as an association (see below)
        if (property.flags.translatable === true) {
            return fields;
        }

        // a foreign key can still be a primary key. This check may fail
        // it assumes that all primary keys have the propertyName of 'id' for now.
        if (property.type === 'uuid' && propertyName !== 'id' /* property.flags.primary_key !== true */) {
            // this is an association which will be checked later (see handleAssociations function)
            return fields;
        }

        if (propertyName === 'translations' && property.type === 'association' && property.flags.required === true) {
            // if the translation association is required also check the translation definition for required fields
            fields = {
                ...fields,
                ...this._getSystemRequiredFieldsForEntityCreation(
                    this.EntityDefinition.get(property.entity),
                    depth, // translations doesn't count to depth and are always included
                    `${prefix}${propertyName}.DEFAULT.`, // target the default language as required
                    visited,
                ),
            };

            return fields;
        }

        if (propertyName === 'price' && property.type === 'json_object') {
            fields = {
                ...fields,
                ...this._addPriceFields(prefix, propertyName),
            };

            return fields;
        }

        // push this field as required with it's property name
        const fieldName = `${prefix}${propertyName}`;
        fields[fieldName] = fieldName;

        return fields;
    }

    _handleAssociation(property, propertyName, properties, depth, prefix, visited) {
        const fields = {};
        const nextPrefix = `${prefix}${propertyName}.`;

        // always include the reference id if it was not visited before
        // and is not a language in an translation
        if (
            visited[property.entity] === undefined &&
            !nextPrefix.endsWith('translations.DEFAULT.language.')
        ) {
            const fieldName = `${nextPrefix}${property.referenceField}`;
            fields[fieldName] = fieldName;
        }

        // visit association entity
        const childFields = this._getSystemRequiredFieldsForEntityCreation(
            this.EntityDefinition.get(property.entity),
            depth - 1,
            nextPrefix,
            visited,
        );

        return {
            ...fields,
            ...childFields,
        };
    }

    _addPriceFields(prefix, propertyName) {
        const fields = {};

        // special case for price fields
        const netName = `${prefix}${propertyName}.DEFAULT.net`;
        fields[netName] = netName;
        const grossName = `${prefix}${propertyName}.DEFAULT.gross`;
        fields[grossName] = grossName;

        return fields;
    }
}
