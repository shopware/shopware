/**
 * @sw-package framework
 */

const negatedExtglobPattern = /!\(([^()]*)\)/;
const extglobPattern = /[!@?*+]\(/;

/**
 * @param {string} pattern
 * @returns {boolean}
 */
function hasExtglob(pattern) {
    return extglobPattern.test(pattern);
}

/**
 * `./**\/!(*.spec|*.test).ts` becomes `['./**\/*.ts', '!./**\/{*.spec,*.test}.ts']`. A negated pattern cannot add files
 * back, so `!./index!(*.spec).ts` excludes every file of `./index*.ts`.
 *
 * @param {string} pattern
 * @returns {string[]}
 */
function toNegatedGlobPatterns(pattern) {
    const isNegated = pattern.startsWith('!') && !pattern.startsWith('!(');
    const body = isNegated ? pattern.slice(1) : pattern;
    const extglob = negatedExtglobPattern.exec(body);

    if (!extglob) {
        return [pattern];
    }

    const allFiles = body.replace(extglob[0], '*');

    if (isNegated) {
        return [`!${allFiles}`];
    }

    const alternatives = extglob[1].split('|');
    const excludedFiles = alternatives.length > 1 ? `{${alternatives.join(',')}}` : extglob[1];

    return [
        allFiles,
        `!${body.replace(extglob[0], excludedFiles)}`,
    ];
}

module.exports = { hasExtglob, toNegatedGlobPatterns };
