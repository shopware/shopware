/**
 * @module app/recently-search-service
 * @package buyers-experience
 */

/**
 * A service for RecentlySearch feature
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class RecentlySearchService {
    /**
     * Get user's stack of recently searched items
     *
     * @param userId
     * @return Array
     */
    get(userId) {
        const items = localStorage.getItem(this._key(userId));

        if (!items) {
            return [];
        }

        return JSON.parse(items);
    }

    /**
     * Add a recently seach entities into the localStorage queue
     *
     * @param userId
     * @param entity
     * @param id
     * @param payload
     * @return void
     */
    add(userId, entity, id, payload = {}) {
        let stack = this.get(userId);

        // Remove already existed entity in stack
        stack = stack.filter((item) => {
            return item.entity !== entity || item.id !== id;
        });

        const newItem = {
            entity,
            id,
            payload,
            timestamp: Date.now(),
        };

        stack.unshift(newItem);

        // If stack size exceeds the maximum size, pop the last item out
        if (stack.length > this._maxStackSize()) {
            stack.pop();
        }

        localStorage.setItem(this._key(userId), JSON.stringify(stack));
    }

    /**
     * @param userId
     * @return {string}
     * @private
     */
    _key(userId) {
        return `recently-search.${userId}`;
    }

    /**
     * @return {number}
     * @private
     */
    _maxStackSize() {
        return 5;
    }
}
