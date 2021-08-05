import { types, object } from 'src/core/service/util.service';

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
        this.grouping = [];
        this.groupFields = [];
        this.totalCountMode = 1;
    }

    static fromCriteria(criteria) {
        return object.cloneDeep(criteria);
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
        if (this.groupFields.length > 0) {
            params.groupFields = this.groupFields;
        }
        if (this.grouping.length > 0) {
            params.grouping = this.grouping;
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
        return this;
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
        return this;
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
     * @see \Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery.
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
     * @param {Object} groupField
     */
    addGroupField(groupField) {
        this.groupFields.push(groupField);
        return this;
    }

    /**
     * Allows grouping the result by an specific field
     * @param {String} field
     * @returns {Criteria} - self
     */
    addGrouping(field) {
        this.grouping.push(field);

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
     * Ensures that a criterion is created for each segment of the passed path.
     * Existing Criteria objects are not overwritten.
     * Returns the own instance
     * @param {String} path
     * @returns {Criteria} - self
     */
    addAssociation(path) {
        const parts = path.split('.');

        let criteria = this;
        parts.forEach((part) => {
            criteria = criteria.getAssociation(part);
        });

        return this;
    }

    /**
     * Ensures that a criterion is created for each segment of the passed path.
     * Returns the criteria instance of the last path segment
     *
     * @param {String} path
     * @returns {Criteria}
     */
    getAssociation(path) {
        const parts = path.split('.');

        let criteria = this;
        parts.forEach((part) => {
            if (!criteria.hasAssociation(part)) {
                criteria.associations.push({
                    association: part,
                    criteria: new Criteria(null, null),
                });
            }

            criteria = criteria.getAssociationCriteria(part);
        });

        return criteria;
    }

    /**
     * @internal
     * @param {String} part
     */
    getAssociationCriteria(part) {
        let criteria = null;

        this.associations.forEach((association) => {
            if (association.association === part) {
                criteria = association.criteria;
            }
        });

        return criteria;
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
     * Resets the sorting parameter
     */
    resetSorting() {
        this.sortings = [];
    }

    /**
     * @see \Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\AvgAggregation
     * Allows to calculate the avg value for the provided field
     *
     * @param {String} name
     * @param {String} field
     * @returns {{field: *, name: *, type: string}}
     */
    static avg(name, field) {
        return { type: 'avg', name, field };
    }

    /**
     * @see \Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation
     * Allows to calculate the count value for the provided field
     *
     * @param {String} name
     * @param {String} field
     * @returns {{field: *, name: *, type: string}}
     */
    static count(name, field) {
        return { type: 'count', name, field };
    }

    /**
     * @see \Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation
     * Allows to calculate the max value for the provided field
     *
     * @param {String} name
     * @param {String} field
     * @returns {{field: *, name: *, type: string}}
     */
    static max(name, field) {
        return { type: 'max', name, field };
    }

    /**
     * @see \Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MinAggregation
     * Allows to calculate the min value for the provided field
     *
     * @param {String} name
     * @param {String} field
     * @returns {{field: *, name: *, type: string}}
     */
    static min(name, field) {
        return { type: 'min', name, field };
    }

    /**
     * @see \Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation
     * Allows to calculate the sum, max, min, avg, count values for the provided field
     *
     * @param {String} name
     * @param {String} field
     * @returns {{field: *, name: *, type: string}}
     */
    static stats(name, field) {
        return { type: 'stats', name, field };
    }

    /**
     * @see \Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\SumAggregation
     * Allows to calculate the sum value for the provided field
     *
     * @param {String} name
     * @param {String} field
     * @returns {{field: *, name: *, type: string}}
     */
    static sum(name, field) {
        return { type: 'sum', name, field };
    }

    /**
     * @see \Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation
     * Allows to fetch term buckets for the provided field
     *
     * @param {String} name
     * @param {String} field
     * @param {Integer|null} limit
     * @param {Object|null} sort
     * @param {Object|null} aggregation
     * @returns {Object}
     */
    static terms(name, field, limit = null, sort = null, aggregation = null) {
        return { type: 'terms', name, field, limit, sort, aggregation };
    }

    /**
     * @see \Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation
     * Allows to filter an aggregation result
     *
     * @param {String} name
     * @param {Array} filter
     * @param {Object} aggregation
     * @returns {Object}
     */
    static filter(name, filter, aggregation) {
        return { type: 'filter', name, filter, aggregation };
    }

    /**
     * @see \Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\DateHistogramAggregation
     * Allows to fetch date buckets for the provided date interval
     *
     * @param {String} name
     * @param {String} field
     * @param {String|null} interval
     * @param {String|null} format
     * @param {Object|null} aggregation
     * @param {String|null} timeZone
     * @returns {Object}
     */
    static histogram(name, field, interval, format, aggregation, timeZone) {
        return { type: 'histogram', name, field, interval, format, aggregation, timeZone };
    }

    /**
     * @see \Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting.
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
     * @see \Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting.
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
     * @see \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter.
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
     * @see \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter.
     * This allows to filter documents where the value marks the beginning of the provided field.
     *
     * Sql representation: `{field} LIKE {value}%`
     *
     * @param {string} field
     * @param {string} value
     *
     * @returns {Object}
     */
    static prefix(field, value) {
        return { type: 'prefix', field, value };
    }

    /**
     * @see \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter.
     * This allows to filter documents where the value marks the end of the provided field.
     *
     * Sql representation: `{field} LIKE %{value}`
     *
     * @param {string} field
     * @param {string} value
     *
     * @returns {Object}
     */
    static suffix(field, value) {
        return { type: 'suffix', field, value };
    }

    /**
     * @see \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter.
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
     * @see \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter.
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
     * @see \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter.
     * This allows to filter documents where the field matches a defined range
     *
     * Sql representation: `{field} = {value}`
     *
     * @param {string} field
     * @param {string|number|boolean|null} value
     *
     * @returns {Object}}
     */
    static equals(field, value) {
        return { type: 'equals', field, value };
    }

    /**
     * @see \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter.
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
     * @see \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter.
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
