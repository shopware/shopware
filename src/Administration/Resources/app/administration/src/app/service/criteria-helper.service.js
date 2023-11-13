import Criteria from '@shopware-ag/admin-extension-sdk/es/data/Criteria';

/**
 * @private
 * @method createCriteriaFromArray
 * @returns Criteria
 */
export default function createCriteriaFromArray(params) {
    const { associations, filters, sortings } = params;

    const criteria = new Criteria();

    if (associations) {
        associations.forEach(association => {
            criteria.addAssociation(association);
        });
    }

    if (filters) {
        const parsedFilters = parseFilters(filters);

        parsedFilters.forEach(filter => {
            criteria.addFilter(filter);
        });
    }

    if (sortings) {
        sortings.forEach(sort => {
            if (!sort.field) {
                return;
            }

            criteria.addSorting(Criteria.sort(sort.field, sort.order, sort.naturalSorting));
        });
    }

    return criteria;
}

function parseFilters(filters) {
    return filters.reduce((parsed, filter) => {
        if (['contains', 'prefix', 'suffix', 'equalsAny', 'equals'].includes(filter.type)
            && (!filter.field || !filter.value)) {
            return parsed;
        }

        switch (filter.type) {
            case 'contains':
                return [...parsed, Criteria.contains(filter.field, filter.value)];
            case 'prefix':
                return [...parsed, Criteria.prefix(filter.field, filter.value)];
            case 'suffix':
                return [...parsed, Criteria.suffix(filter.field, filter.value)];
            case 'equalsAny':
                return [...parsed, Criteria.equalsAny(filter.field, filter.value)];
            case 'equals':
                return [...parsed, Criteria.equals(filter.field, filter.value)];
            case 'range':
                if (!filter.field || !filter.parameters) {
                    return parsed;
                }
                return [...parsed, Criteria.range(filter.field, filter.parameters)];
            case 'not':
                if (!filter.operator || !Array.isArray(filter.queries)) {
                    return parsed;
                }
                return [...parsed, Criteria.not(filter.operator, parseFilters(filter.queries))];
            case 'multi':
                if (!filter.operator || !Array.isArray(filter.queries)) {
                    return parsed;
                }
                return [...parsed, Criteria.multi(filter.operator, parseFilters(filter.queries))];
            default:
                return parsed;
        }
    }, []);
}
