import Criteria from './criteria.data';

export default class RepositoryIterator {
    /**
     * @param {Repository} repository
     * @param {Context} context
     * @param {Criteria} criteria
     */
    constructor(repository, criteria = null, context = null) {
        this.repository = repository;
        this.criteria = criteria || new Criteria();
        this.context = context || Shopware.Context.api;

        if (this.criteria.limit === null || this.criteria.limit < 1) {
            this.criteria.setLimit(25);
        }
    }

    /**
     * @return {Promise<number>}
     */
    getTotal() {
        const criteria = Criteria.fromCriteria(this.criteria)
            .setPage(1)
            .setLimit(1)
            .setTotalCountMode(Criteria.TOTAL_COUNT_MODE_EXACT);

        return this.repository.searchIds(criteria, this.context)
            .then(response => response.total);
    }

    /**
     * @return {Promise<Array<string>>}
     */
    fetchIds() {
        const criteria = Criteria.fromCriteria(this.criteria);
        criteria.setTotalCountMode(Criteria.TOTAL_COUNT_MODE_NONE);
        this.criteria.setPage(this.criteria.page + 1);
        return this.repository.searchIds(criteria, this.context)
            .then(response => (response.data.length > 0 ? response.data : null));
    }

    /**
     * @return {Promise<Object>}
     */
    fetch() {
        const criteria = Criteria.fromCriteria(this.criteria);
        criteria.setTotalCountMode(Criteria.TOTAL_COUNT_MODE_NONE);
        this.criteria.setPage(this.criteria.page + 1);
        return this.repository.search(criteria, this.context)
            .then(entities => (entities.length > 0 ? entities : null));
    }
}
