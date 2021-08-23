const { merge, cloneDeep } = Shopware.Utils.object;
const { Criteria } = Shopware.Data;
const { Module } = Shopware;

/**
 * @module app/service/search-ranking
 */

/**
 * Define search ranking point
 *
 * @memberOf module:app/service/search-ranking
 * @type {Object}
 */
export const searchRankingPoint = Object.freeze({
    HIGH_SEARCH_RANKING: 500,
    LOW_SEARCH_RANKING: 80,
    MIDDLE_SEARCH_RANKING: 250,
});

/**
 * @memberOf module:app/service/search-ranking
 * @constructor
 * @method createSearchRankingService
 * @returns {Object}
 */
export default function createSearchRankingService() {
    const cacheDefaultSearchScore = {};

    return {
        getSearchFieldsByEntity,
        buildSearchQueriesForEntity,
        getUserSearchPreference,
        buildGlobalSearchQueries,
    };

    /**
     * @param {Object} userSearchPreference
     * @param {String} searchTerm
     *
     * @returns {Object}
     */
    function buildGlobalSearchQueries(userSearchPreference, searchTerm) {
        if (!_isValidTerm(searchTerm)) {
            return {};
        }

        const query = {};

        Object.keys(userSearchPreference).forEach(entity => {
            const fields = userSearchPreference[entity];
            if (Object.keys(fields).length === 0) {
                return;
            }

            const queryScores = _buildQueryScores(fields, searchTerm);
            query[entity] = _addSearchQueries(queryScores, new Criteria()).parse();
        });

        return query;
    }

    /**
     * @param {Object} searchRankingFields
     * @param {String} searchTerm
     * @param {Criteria} criteria
     *
     * @returns {Object}
     */
    function buildSearchQueriesForEntity(searchRankingFields, searchTerm, criteria) {
        const queryScores = _buildQueryScores(searchRankingFields, searchTerm);

        return _addSearchQueries(queryScores, criteria);
    }

    /**
     * @returns {Object}
     */
    function getUserSearchPreference() {
        // TODO: Implement getting User Search Preference from user_config later (would be implemented in
        //  ticket NEXT-15903 and ticket NEXT-15926)
        return _getDefaultUserSearchPreference();
    }

    /**
     * @param {String} entityName
     * @returns {Object}
     */
    function getSearchFieldsByEntity(entityName) {
        // TODO: Implement getting User Search Preference from user_config later (would be implemented in
        //  ticket NEXT-15903 and ticket NEXT-15926)
        const module = Module.getModuleByEntityName(entityName);
        if (module === undefined) {
            throw new Error('search-ranking.service - Can not get module by the entity name');
        }

        return _getDefaultSearchFieldsByEntity(module.manifest);
    }

    /**
     * @private
     * @param {Object}
     * @returns {Object}
     */
    function _getDefaultSearchFieldsByEntity({ defaultSearchConfiguration, entity, searchEntity = undefined }) {
        if (!defaultSearchConfiguration) {
            return {};
        }

        if (cacheDefaultSearchScore[entity]) {
            return cacheDefaultSearchScore[entity];
        }

        cacheDefaultSearchScore[entity] = _scoring(
            defaultSearchConfiguration,
            searchEntity ?? entity,
        );

        return cacheDefaultSearchScore[entity];
    }

    /**
     * @private
     * @returns {Object}
     */
    function _getDefaultUserSearchPreference() {
        const entityScoringFields = {};
        Module.getModuleRegistry().forEach(({ manifest }) => {
            if (!manifest.hasOwnProperty('defaultSearchConfiguration')) {
                return;
            }

            entityScoringFields[manifest.entity] = _getDefaultSearchFieldsByEntity(manifest);
        });

        return entityScoringFields;
    }

    /**
     * @private
     * @param {String} searchTerm
     * @returns {Boolean}
     */
    function _isValidTerm(searchTerm) {
        return searchTerm && searchTerm.trim().length > 1;
    }

    /**
     * @private
     * @param {Array} queryScores
     * @param {Criteria} oldCriteria
     * @returns {Criteria}
     */
    function _addSearchQueries(queryScores, oldCriteria) {
        if (queryScores.length < 1) {
            return oldCriteria;
        }
        const cloneCriteria = cloneDeep(oldCriteria);

        queryScores.forEach(queryScore => {
            cloneCriteria.addQuery(...queryScore);
        });

        return cloneCriteria.setTerm(null);
    }

    /**
     * @private
     * @param {Object} fieldScores
     * @param {String} searchTerm
     * @returns {Array}
     */
    function _buildQueryScores(fieldScores, searchTerm) {
        if (!_isValidTerm(searchTerm)) {
            return [];
        }

        let terms = searchTerm.split(' ').filter(term => {
            return term.length > 1;
        });
        terms = [...new Set(terms)];

        const queryScores = [];
        const originalTerm = searchTerm.trim();

        Object.keys(fieldScores).forEach(field => {
            queryScores.push(
                [Criteria.equals(field, originalTerm), fieldScores[field]],
                [Criteria.contains(field, originalTerm), fieldScores[field] * 0.75],
            );

            if (terms.length === 0 || (terms.length === 1 && terms[0] === originalTerm)) {
                return;
            }

            terms.forEach((term) => {
                queryScores.push(
                    [Criteria.equals(field, term), fieldScores[field] * 0.5],
                    [Criteria.contains(field, term), fieldScores[field] * 0.5 * 0.75],
                );
            });
        });

        return queryScores;
    }

    /**
     * @private
     * @param {Object} searchRankingFields
     * @param {String} root
     * @returns {Array}
     */
    function _scoring(searchRankingFields, root = '') {
        let scores = {};

        Object.keys(searchRankingFields).forEach(field => {
            const nested = searchRankingFields[field];
            const select = root ? `${root}.${field}` : field;

            if (!nested.hasOwnProperty('_searchable')) {
                scores = merge(scores, _scoring(nested, select));
                return;
            }
            if (nested._searchable === false) {
                return;
            }

            scores[select] = nested._score;
        });

        return scores;
    }
}
