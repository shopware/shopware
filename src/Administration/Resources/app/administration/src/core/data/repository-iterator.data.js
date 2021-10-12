import Criteria from './criteria.data';

/**
 * Callback for iterating entities
 *
 * @callback iterateEntitiesCallback
 * @param {Object} entities
 */

/**
 * Callback for iterating entities
 *
 * @callback iterateIdsCallback
 * @param {Array<String>} ids
 */

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
     * @param {iterateIdsCallback} callback
     * @returns {Promise<Array<String>>}
     */
    iterateIds(callback = null) {
        const fetch = (result) => {
            return this.fetch().then(response => {
                if (response === null) {
                    return Promise.resolve(result);
                }

                result.push(...response);

                if (callback !== null) {
                    callback(response);
                }

                return fetch(result);
            });
        };

        return fetch([]);
    }

    async * iterateIdsAsync() {
        const fetchIds = async () => this.fetchIds();

        do {
            // eslint-disable-next-line no-await-in-loop
            const ids = await fetchIds();

            if (ids === null) {
                break;
            }

            yield* ids;
        } while (true);
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

    /**
     * @param {iterateEntitiesCallback} callback
     * @returns {Promise<Array<Object>>}
     */
    iterate(callback = null) {
        const fetch = (result) => {
            return this.fetch().then(response => {
                if (response === null) {
                    return Promise.resolve(result);
                }

                result.push(...response);
                result.criteria = response.criteria;
                result.total = response.total;

                if (callback !== null) {
                    callback(response);
                }

                return fetch(result);
            });
        };

        return fetch([]);
    }

    async * iterateAsync() {
        const fetch = async () => this.fetch();

        do {
            // eslint-disable-next-line no-await-in-loop
            const items = await fetch();

            if (items === null) {
                break;
            }

            yield* items.data;
        } while (true);
    }
}
