export default class ImportExportProfileMappingService {
    constructor(EntityDefinition) {
        this.EntityDefinition = EntityDefinition;
    }

    validate(entityName, mapping, parentMapping = []) {
        const mappingKeys = this.convertMappingKeys(mapping);

        const parentMappingKeys = this.convertMappingKeys(parentMapping);

        const requiredFields = this.EntityDefinition.getRequiredFields(entityName);
        const missingRequiredFields = [];

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
}
