/**
 * @sw-package framework
 */

/**
 * Keeps the attribute parser independent from broader string parsing helpers.
 */
function isWhitespace(character: string): boolean {
    return /\s/.test(character);
}

module.exports = {
    isWhitespace,
};

export {
    isWhitespace,
};
