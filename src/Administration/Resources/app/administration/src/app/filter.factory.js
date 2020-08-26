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
            ) {
                continue;
            }

            const filter = {}

            filter.name = key

            if (settings.label) {
                filter.label = settings.label;
            } else {
                filter.label = key; // this.$tc('sw-product.list.filter.manufacturer.label') // make translation
            }

            if (settings.placeholder) {
                filter.placeholder = settings.placeholder;
            } else {
                filter.placeholder = ""; // this.$tc('sw-product.list.filter.manufacturer.placeholder') // make translation
            }

            if (property.localField) {
                filter.field = entityName + '.' + property.localField
            } else {
                filter.field = entityName + '.' + key
            }

            let skip = false
            switch (property.type) {
                case "association":
                    filter.inputType = 'multiSelect'
                    filter.criteriaType = 'equalsAny'

                    break;
                case "boolean":
                    filter.inputType = 'singleSelect';
                    filter.criteriaType = 'equals';

                    filter.options = [
                        {
                            name: 'All',
                            value: null
                        },
                        {
                            name: 'True',
                            value: true
                        },
                        {
                            name: 'False',
                            value: false
                        }
                    ]

                    break;
                case "string":
                    filter.inputType = 'input';
                    filter.criteriaType = 'contains';

                    break;
                case "int":
                    filter.inputType = 'range';
                    filter.criteriaType = 'range';

                    break;
                default:
                    skip = true;
                    break;
            }
            if (skip) continue;

            if (property.entity) {
                filter.repository = property.entity
            }

            filters.push(filter);
        }

        return filters;
    }
}

export default filterFactory
