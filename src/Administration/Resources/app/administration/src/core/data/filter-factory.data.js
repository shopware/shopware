/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 */
export default class FilterFactory {
    constructor() {
        this.STRING_FILTER_INPUT = 'string-filter';
        this.NUMBER_FILTER_INPUT = 'number-filter';
        this.DATE_FILTER_INPUT = 'date-filter';
        this.ASSOCIATION_FILTER_INPUT = 'multi-select-filter';
        this.BOOLEAN_FILTER_INPUT = 'boolean-filter';
        this.PRICE_FILTER_INPUT = 'price-filter';
        this.EXISTENCE_FILTER_INPUT = 'existence-filter';
    }

    /**
     * Creates a filter objects for each module.
     *
     * @param {String} entityName
     * @param {Object|null} filters
     * @returns {Array} filters
     */
    create(entityName, filters) {
        return Object.entries(filters).map(([key, filter]) => {
            filter.name = key;

            const property = this.getFilterProperties(entityName, filter.property);

            if (filter.type || !property) {
                return filter;
            }

            filter.schema = property;

            switch (property.type) {
                case 'string':
                    filter.type = this.STRING_FILTER_INPUT;
                    break;
                case 'int':
                    filter.type = this.NUMBER_FILTER_INPUT;
                    break;
                case 'date':
                    filter.type = this.DATE_FILTER_INPUT;
                    break;
                case 'association':
                    filter.type = (property.relation === 'many_to_many' || property.relation === 'many_to_one')
                        ? this.ASSOCIATION_FILTER_INPUT
                        : this.EXISTENCE_FILTER_INPUT;
                    break;
                case 'boolean':
                    filter.type = this.BOOLEAN_FILTER_INPUT;
                    break;
                default:
                    filter.type = this.STRING_FILTER_INPUT;
            }

            if (filter.property === 'price') {
                filter.type = this.NUMBER_FILTER_INPUT;
            }

            return filter;
        });
    }

    /**
     * Get filter entity properties
     *
     * @param {String} entityName
     * @param {string} accessor
     * @returns {Object}
     */
    getFilterProperties(entityName, accessor) {
        const { properties } = Shopware.EntityDefinition.get(entityName);

        const parts = accessor.split('.');

        // Get the first accessor element
        const first = parts.shift();

        const property = properties[first];

        if (!property) {
            throw new Error(`No definition found for property ${first}`);
        }

        // If there are more parts remaining
        if (parts.length > 0 && property.entity) {
            // recursion call for nested associations
            return this.getFilterProperties(property.entity, parts.join('.'));
        }

        let returnProperty = { ...property };

        // Check for foreign key association
        if (property.type === 'uuid') {
            Object.keys(properties).forEach(key => {
                if (properties[key].type === 'association' && properties[key].localField === first) {
                    returnProperty = properties[key];
                }
            });
        }

        return returnProperty;
    }
}
