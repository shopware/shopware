/**
 * @sw-package framework
 */

/**
 * Keeps the attribute parser independent from broader string parsing helpers.
 *
 * @param {string} character
 * @returns {boolean}
 */
function isWhitespace(character) {
    return /\s/.test(character);
}

module.exports = {
    isWhitespace,
};
