const { merge, cloneDeep } = Shopware.Utils.object;
const { Criteria } = Shopware.Data;
const { Service, Module } = Shopware;

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

export const KEY_USER_SEARCH_PREFERENCE = 'search.preferences';
/**
 * @memberOf module:app/service/search-ranking
 * @constructor
 * @method createSearchRankingService
 * @returns {Object}
 */
export default function createSearchRankingService() {
    const cacheDefaultSearchScore = {};
    const cacheModules = {};
    let cacheUserSearchConfiguration;
    let cacheDefaultUserSearchPreference;

    return {
        getSearchFieldsByEntity,
        buildSearchQueriesForEntity,
        getUserSearchPreference,
        buildGlobalSearchQueries,
        clearCacheUserSearchConfiguration,
    };

    /**
     * @param {Object} userSearchPreference
     * @param {String} searchTerm
     *
     * @returns {Object}
     */
    function buildGlobalSearchQueries(userSearchPreference, searchTerm) {
        if (!_isValidTerm(searchTerm) || _isEmptyObject(userSearchPreference)) {
            return {};
        }

        const query = {};

        Object.keys(userSearchPreference).forEach(entity => {
            const fields = userSearchPreference[entity];
            if (_isEmptyObject(fields)) {
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
        if (!_isValidTerm(searchTerm) || _isEmptyObject(searchRankingFields)) {
            return criteria;
        }

        const queryScores = _buildQueryScores(searchRankingFields, searchTerm);

        return _addSearchQueries(queryScores, criteria);
    }

    /**
     * @returns {Object}
     */
    async function getUserSearchPreference() {
        const userConfigSearchFields = await _fetchUserConfig();
        const defaultUserSearchPreference = _getDefaultUserSearchPreference();
        if (!userConfigSearchFields) {
            return defaultUserSearchPreference;
        }
        const result = {};
        Object.keys(defaultUserSearchPreference).forEach((entityName) => {
            if (!userConfigSearchFields[entityName]) {
                result[entityName] = defaultUserSearchPreference[entityName];
                return;
            }

            if (!_isEntitySearchable(userConfigSearchFields[entityName])) {
                return;
            }
            const currentModule = _getModule(entityName);
            result[entityName] = _scoring(userConfigSearchFields[entityName], currentModule.searchEntity ?? entityName);
        });

        return result;
    }

    /**
     * @param {String} entityName
     * @returns {Object}
     */
    async function getSearchFieldsByEntity(entityName) {
        const currentModule = _getModule(entityName);
        const userConfigSearchFieldsByEntity = await _fetchUserConfig(entityName);
        if (!userConfigSearchFieldsByEntity) {
            return _getDefaultSearchFieldsByEntity(currentModule);
        }

        if (_isEmptyObject(currentModule.defaultSearchConfiguration) ||
            !_isEntitySearchable(userConfigSearchFieldsByEntity)) {
            return {};
        }

        return _scoring(
            userConfigSearchFieldsByEntity,
            currentModule.searchEntity ?? entityName,
        );
    }

    function clearCacheUserSearchConfiguration() {
        cacheUserSearchConfiguration = undefined;
    }

    /**
     * @private
     * @param {Object}
     * @returns {Object}
     */
    function _getDefaultSearchFieldsByEntity({ defaultSearchConfiguration, entity, searchEntity = undefined }) {
        if (!_isEntitySearchable(defaultSearchConfiguration)) {
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
        if (cacheDefaultUserSearchPreference) {
            return cacheDefaultUserSearchPreference;
        }
        cacheDefaultUserSearchPreference = {};
        Module.getModuleRegistry().forEach(({ manifest }) => {
            if (!_isEntitySearchable(manifest.defaultSearchConfiguration)) {
                return;
            }

            cacheDefaultUserSearchPreference[manifest.entity] = _getDefaultSearchFieldsByEntity(manifest);
        });

        return cacheDefaultUserSearchPreference;
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
     * @param {undefined|Object} searchFields
     * @returns {Boolean}
     */
    function _isEmptyObject(searchFields) {
        return !(typeof searchFields === 'object' && Object.keys(searchFields).length > 0);
    }

    /**
     * @private
     * @param {Object} searchFields
     * @returns {Boolean}
     */
    function _isEntitySearchable(searchFields) {
        return searchFields && searchFields._searchable;
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
     * @param {undefined|Object} searchRankingFields
     * @param {String} root
     * @returns {Array}
     */
    function _scoring(searchRankingFields, root = '') {
        if (_isEmptyObject(searchRankingFields)) {
            return {};
        }

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

    /**
     * @private
     * @param {undefined|Object} entityName
     * @returns {undefined|Object}
     */
    function _fetchUserConfig(entityName = undefined) {
        if (cacheUserSearchConfiguration) {
            return entityName ? cacheUserSearchConfiguration[entityName] : cacheUserSearchConfiguration;
        }

        const userConfigService = Service('userConfigService');

        return userConfigService.search([KEY_USER_SEARCH_PREFERENCE]).then((response) => {
            const value = response.data[KEY_USER_SEARCH_PREFERENCE];
            if (!value) {
                return undefined;
            }

            cacheUserSearchConfiguration = Object.assign({}, ...value);
            if (entityName) {
                return cacheUserSearchConfiguration[entityName];
            }

            return cacheUserSearchConfiguration;
        });
    }


    /**
     * @param {String} entityName
     * @returns {Object}
     */
    function _getModule(entityName) {
        if (cacheModules[entityName]) {
            return cacheModules[entityName];
        }

        const module = Module.getModuleByEntityName(entityName);
        if (module === undefined) {
            throw new Error(`search-ranking.service - Can not get module by the entity name is ${entityName}`);
        }

        cacheModules[entityName] = module.manifest;

        return cacheModules[entityName];
    }
}
