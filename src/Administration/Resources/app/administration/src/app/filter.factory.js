import _ from 'lodash';

let filterFactory = {
    /**
     *
     * @param {string} entityName
     * @param {Object} filterSettings
     * @returns {[]}
     */
    create(entityName, filterSettings) {
        const entity = Shopware.EntityDefinition.get(entityName)

        const filters = [];

        for (const key in entity.properties) {
            const property = entity.properties[key];
            let settings = filterSettings[key];
            if (settings === undefined) settings = {};
            if (settings.hide) continue;

            if (property.type === "uuid"
                || property.type === "json_object"
                || property.localField === "id"
                || !['association', 'singleSelect', 'input', 'boolean', 'int'].includes(property.type)
            ) {
                continue;
            }

            const filter = {}

            filter.key = key;

            filter.property = property;

            if (settings.label) {
                filter.label = settings.label;
            } else {
                filter.label = _.startCase(key);
            }

            if (settings.placeholder) {
                filter.placeholder = settings.placeholder;
            } else {
                filter.placeholder = "";
            }

            if (property.localField) {
                filter.field = entityName + '.' + property.localField
            } else {
                filter.field = entityName + '.' + key
            }

            filters.push(filter);
        }

        return filters;
    }
}

export default filterFactory
