/**
 * @deprecated tag:v6.5.0 - will be removed, use `sw-promotion-v2` instead
 */
export default {
    generateCode,
    getPermutationCount,
    getRandomCharacter,
    getRandomNumber,
    getCharacters,
    getDigit,
};

/**
 * Generates a new and random code with the provided string pattern.
 * Use the following placeholders to generate random characters and numbers in your code:
 * - '%s': random character
 * - '%d' - random number
 *
 * @example my-code_%s%s-%d%d
 * @param {String} pattern
 */
function generateCode(pattern) {
    let code = pattern;

    while (code.includes('%s')) {
        code = code.replace(new RegExp('%s'), getRandomCharacter());
    }

    while (code.includes('%d')) {
        code = code.replace(new RegExp('%d'), getRandomNumber());
    }

    return code;
}

/**
 * Gets the number of possible permutations of the
 * provided code pattern.
 *
 * @param {String} pattern
 */
function getPermutationCount(pattern) {
    const stringCount = (pattern.split('%s').length - 1);
    const digitCount = (pattern.split('%d').length - 1);

    // if no wildcards exist, then
    // we have at least 1 static permutation
    if (stringCount <= 0 && digitCount <= 0) {
        return 1;
    }

    // if we dont have a wildcard
    // make sure to have at least a count of * 1.
    // to avoid results of 0 when multiplying with 0.
    let stringSum = 1;
    let digitSum = 1;

    if (stringCount > 0) {
        stringSum = 52 ** stringCount;
    }

    if (digitCount > 0) {
        digitSum = 10 ** digitCount;
    }

    return (stringSum * digitSum);
}

function getRandomCharacter() {
    return getCharacters().charAt(Math.floor(Math.random() * Math.floor(getCharacters().length)));
}

function getRandomNumber() {
    return getDigit().charAt(Math.floor(Math.random() * Math.floor(getDigit().length)));
}

function getCharacters() {
    return 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
}

function getDigit() {
    return '0123456789';
}
