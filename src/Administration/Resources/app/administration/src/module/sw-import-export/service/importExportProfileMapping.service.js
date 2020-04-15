export default class ImportExportProfileMappingService {
    constructor(EntityDefinition) {
        this.EntityDefinition = EntityDefinition;
    }

    validate(entityName, mapping) {
        const mappingKeys = mapping.map(field => field.key);

        // add translations to mappingKeys
        mappingKeys.forEach((key) => {
            const keyParts = key.split('.');

            if (keyParts.includes('translations')) {
                mappingKeys.push(keyParts.slice(2).join(''));
            }
        });

        const requiredFields = this.EntityDefinition.getRequiredFields(entityName);
        const missingRequiredFields = [];

        // check if mapping contains all required fields
        Object.keys(requiredFields).forEach((fieldName) => {
            // skip translations and price
            if (fieldName === 'translations' || fieldName === 'price') {
                return;
            }

            if (!mappingKeys.includes(fieldName)) {
                missingRequiredFields.push(fieldName);
            }
        });

        return { missingRequiredFields };
    }
}
