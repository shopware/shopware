/**
 * @sw-package framework
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function createExampleSearchService() {
    const cache = new Map();

    return {
        search,
        getFields,
        reset,
    };

    function search(term, limit = 50, offset = 0) {
        return cache.get(`${term}:${limit}:${offset}`);
    }

    function getFields(entity) {
        return cache.get(entity);
    }

    async function reset() {
        cache.clear();
    }
}
