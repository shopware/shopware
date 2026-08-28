/**
 * @sw-package fundamentals@framework
 *
 * Pure utility functions for the mcp-allowlist component.
 * No Vue dependency — safe to unit-test in isolation.
 */

/**
 * Groups an array of items by a key derived from each item.
 *
 * @param {Array} items
 * @param {function} getGroupKey - receives an item, returns the group key string
 * @returns {Object} map of groupKey => items[]
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function buildGroups(items, getGroupKey) {
    const groups = {};
    items.forEach((item) => {
        const key = getGroupKey(item) ?? 'other';
        if (!groups[key]) groups[key] = [];
        groups[key].push(item);
    });
    return groups;
}

/**
 * Capitalizes each hyphen/underscore-separated word in a string.
 *
 * @param {string} str
 * @returns {string}
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function humanizeLabel(str) {
    return str
        .split(/[-_]/)
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
}

/**
 * Returns a human-readable label for the longest common hyphenated prefix shared by all names.
 *
 * @param {string[]} names
 * @returns {string}
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function humanizeCommonPrefix(names) {
    if (!names.length) return '';
    const firstSegs = names[0].split('-');
    const firstSegsLower = firstSegs.map((s) => s.toLowerCase());
    const otherSegsLower = names.slice(1).map((n) => n.toLowerCase().split('-'));
    let len = 0;
    for (let i = 0; i < firstSegs.length; i++) {
        if (otherSegsLower.every((s) => s[i] === firstSegsLower[i])) {
            len = i + 1;
        } else {
            break;
        }
    }
    return firstSegs
        .slice(0, len)
        .map((w) => {
            if (w === w.toLowerCase()) return w.charAt(0).toUpperCase() + w.slice(1);
            return w;
        })
        .join(' ');
}
