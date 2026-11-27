/**
 * @sw-package framework
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function createExampleSearchService() {
    const cache = new Map();

    return {
        maxResults: 100,
        search,
        getFields,
        reset,
    };

    function search(term, limit = 25) {
        return cache.get(`${term}:${limit}`);
    }

    function getFields(entity, forceReload) {
        return forceReload ? [] : cache.get(entity);
    }

    function reset() {
        cache.clear();
    }
}
