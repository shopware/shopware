import { types } from 'src/core/service/util.service';

export default class Criteria {
    constructor(page = 1, limit = 25) {
        this.page = page;
        this.limit = limit;
        this.term = null;
        this.filters = [];
        this.ids = [];
        this.queries = [];
        this.associations = [];
        this.postFilter = [];
        this.sortings = [];
        this.aggregations = [];
        this.totalCountMode = null;
    }

    /**
     * Parses the current criteria and generates an object which can be provided to the api
     *
     * @return {Object}
     */
    parse() {
        const params = {};

        if (this.ids.length > 0) {
            params.ids = this.ids.join('|');
        }
        if (this.page !== null) {
            params.page = this.page;
        }
        if (this.limit !== null) {
            params.limit = this.limit;
        }
        if (this.term !== null) {
            params.term = this.term;
        }
        if (this.queries.length > 0) {
            params.query = this.queries;
        }
        if (this.filters.length > 0) {
            params.filter = this.filters;
        }
        if (this.postFilter.length > 0) {
            params['post-filter'] = this.postFilter;
        }
        if (this.sortings.length > 0) {
            params.sort = this.sortings;
        }
        if (this.aggregations.length > 0) {
            params.aggregations = this.aggregations;
        }
        if (this.associations.length > 0) {
            params.associations = {};

            this.associations.forEach((item) => {
                params.associations[item.association] = item.criteria.parse();
            });
        }
        if (this.totalCountMode !== null) {
            params['total-count-mode'] = this.totalCountMode;
        }

        return params;
    }

    /**
     * Allows to provide a list of ids which are used as a filter
     * @param {Array} ids
     */
    setIds(ids) {
        this.ids = ids;
    }

    /**
     * Allows to configure the total value of a search result.
     * 0 - no total count will be selected. Should be used if no pagination required (fastest)
     * 1 - exact total count will be selected. Should be used if an exact pagination is required (slow)
     * 2 - fetches limit * 5 + 1. Should be used if pagination can work with "next page exists" (fast)
     *
     * @param {int} mode
     */
    setTotalCountMode(mode) {
        if (!types.isNumber(mode)) {
            this.totalCountMode = null;
        }

        this.totalCountMode = (mode < 0 || mode > 2) ? null : mode;
    }

    /**
     * @param {int} page
     * @returns {Criteria}
     */
    setPage(page) {
        this.page = page;
        return this;
    }

    /**
     * @param {int} limit
     * @returns {Criteria}
     */
    setLimit(limit) {
        this.limit = limit;
        return this;
    }

    /**
     * @param {String} term
     * @returns {Criteria}
     */
    setTerm(term) {
        this.term = term;
        return this;
    }

    /**
     * @param {Object} filter
     * @returns {Criteria}
     */
    addFilter(filter) {
        this.filters.push(filter);

        return this;
    }

    /**
     * Adds the provided filter as post filter.
     * Post filter will be considered for the documents query but not for the aggregations.
     *
     * @param {Object} filter
     * @returns {Criteria}
     */
    addPostFilter(filter) {
        this.postFilter.push(filter);
        return this;
    }

    /**
     * Allows to add different sortings for the criteria, to sort the entity result.
     * @param {Object} sorting
     * @returns {Criteria}
     */
    addSorting(sorting) {
        this.sortings.push(sorting);
        return this;
    }

    /**
     * Creates an object for \Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery.
     * These queries are used to search for documents and score them with a ranking
     *
     * @param {Object} filter - a filter object like equals, contains, ...
     * @param {int} score - defines a score if the filter field match
     * @param {string} scoreField - Allows to define a storage field for the scoring which is used instead of the score
     *
     * @returns {Criteria}
     */
    addQuery(filter, score, scoreField = null) {
        const query = { score: score, query: filter };

        if (scoreField) {
            query[scoreField] = scoreField;
        }

        this.queries.push(query);

        return this;
    }

    /**
     * @param {Object} aggregation
     */
    addAggregation(aggregation) {
        this.aggregations.push(aggregation);
        return this;
    }

    /**
     * @param {String} association
     * @param {Object|null} criteria
     * @returns {Criteria}
     */
    addAssociation(association, criteria = null) {
        if (criteria === null) {
            criteria = new Criteria();
        }

        this.associations.push({ association, criteria });
        return this;
    }

    /**
     * Allows to add criteria objects for nested associations
     *
     * e.g. criteria.addAssociationPath(products.prices.rule)
     *
     * @param {String} path
     * @returns {Criteria}
     */
    addAssociationPath(path) {
        const parts = path.split('.');

        let criteria = this;
        parts.forEach((part) => {
            if (!criteria.hasAssociation(part)) {
                criteria.addAssociation(part);
            }

            criteria = criteria.getAssociation(part);
        });
        return this;
    }

    /**
     * Allows to add multiple association paths to the criteria
     * @param {Array} associations
     */
    addAssociationPaths(associations) {
        associations.forEach((association) => {
            this.addAssociationPath(association);
        });
    }

    /**
     * @param {String} property
     * @returns {boolean}
     */
    hasAssociation(property) {
        let exists = false;

        this.associations.forEach((association) => {
            if (association.association === property) {
                exists = true;
            }
        });

        return exists;
    }

    /**
     * @param {String} property
     * @returns {Criteria}
     */
    getAssociation(property) {
        let criteria = new Criteria();

        this.associations.forEach((association) => {
            if (association.association === property) {
                criteria = association.criteria;
            }
        });

        return criteria;
    }

    /**
     * Resets the sorting parameter
     */
    resetSorting() {
        this.sortings = [];
    }

    /**
     * Creates an object for \Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\AvgAggregation
     * Allows to calculate the avg value for the provided field
     *
     * @param {String} name
     * @param {String} field
     * @param {Array} [groupByFields]
     * @returns {{field: *, name: *, type: string}}
     */
    static avg(name, field, groupByFields = []) {
        return { type: 'avg', name, field, groupByFields };
    }

    /**
     * Creates an object for \Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\ValueAggregation
     * Allows to fetch all unique values for the provided field
     *
     * @param {String} name
     * @param {String} field
     * @param {Array} [groupByFields]
     * @returns {{field: *, name: *, type: string}}
     */
    static value(name, field, groupByFields = []) {
        return { type: 'value', name, field, groupByFields };
    }

    /**
     * Creates an object for \Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation
     * Allows to calculate the count value for the provided field
     *
     * @param {String} name
     * @param {String} field
     * @param {Array} [groupByFields]
     * @returns {{field: *, name: *, type: string}}
     */
    static count(name, field, groupByFields = []) {
        return { type: 'count', name, field, groupByFields };
    }

    /**
     * Creates an object for \Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation
     * Allows to calculate the max value for the provided field
     *
     * @param {String} name
     * @param {String} field
     * @param {Array} [groupByFields]
     * @returns {{field: *, name: *, type: string}}
     */
    static max(name, field, groupByFields = []) {
        return { type: 'max', name, field, groupByFields };
    }

    /**
     * Creates an object for \Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MinAggregation
     * Allows to calculate the min value for the provided field
     *
     * @param {String} name
     * @param {String} field
     * @param {Array} [groupByFields]
     * @returns {{field: *, name: *, type: string}}
     */
    static min(name, field, groupByFields = []) {
        return { type: 'min', name, field, groupByFields };
    }

    /**
     * Creates an object for \Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation
     * Allows to calculate the sum, max, min, avg, count values for the provided field
     *
     * @param {String} name
     * @param {String} field
     * @param {Array} [groupByFields]
     * @returns {{field: *, name: *, type: string}}
     */
    static stats(name, field, groupByFields = []) {
        return { type: 'stats', name, field, groupByFields };
    }

    /**
     * Creates an object for \Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\SumAggregation
     * Allows to calculate the sum value for the provided field
     *
     * @param {String} name
     * @param {String} field
     * @param {Array} [groupByFields]
     * @returns {{field: *, name: *, type: string}}
     */
    static sum(name, field, groupByFields = []) {
        return { type: 'sum', name, field, groupByFields };
    }

    /**
     * Creates an object for
     * \Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\ValueCountAggregation.
     *
     * Allows to calculate the unique value and the counts for the selected entities.
     *
     * @param {string} name
     * @param {string} field
     * @param {Array} [groupByFields]
     * @returns {Object}
     */
    static valueCount(name, field, groupByFields = []) {
        return { type: 'value_count', name, field, groupByFields };
    }

    /**
     * Creates an object for \Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting.
     * Allows to sort the documents by the provided field
     *
     * @param {string} field
     * @param {string} order - ASC/DESC
     * @param {boolean} naturalSorting
     *
     * @returns {Object}
     */
    static sort(field, order = 'ASC', naturalSorting = false) {
        return { field, order, naturalSorting };
    }

    /**
     * Creates an object for \Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting.
     * Allows to sort the documents by the provided field naturally
     *
     * @param {string} field
     * @param {string} order - ASC/DESC
     *
     * @returns {Object}
     */
    static naturalSorting(field, order = 'ASC') {
        return { field, order, naturalSorting: true };
    }

    /**
     * Creates an object for \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter.
     * This allows to filter documents where the value are contained in the provided field.
     *
     * Sql representation: `{field} LIKE %{value}%`
     *
     * @param {string} field
     * @param {string} value
     *
     * @returns {Object}
     */
    static contains(field, value) {
        return { type: 'contains', field, value };
    }

    /**
     * Creates an object for \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter.
     * This allows to filter documents where the field matches one of the provided values
     *
     * Sql representation: `{field} IN ({value}, {value})`
     *
     * @param {string} field
     * @param {array} value
     * @returns {Object}}
     */
    static equalsAny(field, value) {
        return { type: 'equalsAny', field, value: value.join('|') };
    }

    /**
     * Creates an object for \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter.
     * This allows to filter documents where the field matches a defined range
     *
     * Sql representation: `{field} >= {value}`, `{field} <= {value}`, ...
     *
     * @param {string} field
     * @param {object} range
     *
     * @returns {Object}}
     */
    static range(field, range) {
        return { type: 'range', field, parameters: range };
    }

    /**
     * Creates an object for \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter.
     * This allows to filter documents where the field matches a defined range
     *
     * Sql representation: `{field} = {value}`
     *
     * @param {string} field
     * @param {string|number} value
     *
     * @returns {Object}}
     */
    static equals(field, value) {
        return { type: 'equals', field, value };
    }

    /**
     * Creates an object for \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter.
     * This allows to filter documents which not matches for the provided filters
     * All above listed queries can be provided (equals, equalsAny, range, contains)
     *
     * Sql representation: `NOT({query} {operator} {query} {operator} {query})`
     *
     * @param {string} operator - and/or
     * @param {array} queries
     *
     * @returns {Object}}
     */
    static not(operator, queries = []) {
        return { type: 'not', operator: operator, queries: queries };
    }

    /**
     * Creates an object for \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter.
     * This allows to filter documents which matches for the provided filters
     * All above listed queries can be provided (equals, equalsAny, range, contains)
     *
     * Sql representation: `({query} {operator} {query} {operator} {query})`
     *
     * @param {string} operator - and/or
     * @param {array} queries
     *
     * @returns {Object}}
     */
    static multi(operator, queries = []) {
        return { type: 'multi', operator, queries };
    }
}
