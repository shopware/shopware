/**
 * @sw-package framework
 */

const fs = require('fs');
const path = require('path');
const { parse } = require('@typescript-eslint/typescript-estree');
const {
    findComponentDefinitions,
    findProperty,
    getDeprecatedOption,
    getPropDefinitions,
    getStringLiteral,
} = require('./component-deprecations');

const ADMINISTRATION_SOURCES = path.resolve(__dirname, '../../../src');

const indexesBySourceRoot = new Map();

/**
 * Reads the value of a `deprecated` option in both of its forms: a version string, or an object
 * with `version` and `comment`.
 *
 * @example
 * readDeprecation(option); // { version: 'v6.8.0.0', comment: 'Use "mt-card" instead.' }
 */
function readDeprecation(option) {
    if (option.value.type === 'ObjectExpression') {
        return {
            version: getStringLiteral(findProperty(option.value, 'version')?.value) ?? '',
            comment: getStringLiteral(findProperty(option.value, 'comment')?.value) ?? '',
        };
    }

    return {
        version: option.value.type === 'Literal' ? String(option.value.value) : '',
        comment: '',
    };
}

function isComponentSourceCandidate(relativePath) {
    return /\.(js|ts)$/.test(relativePath) && !/\.(spec|d)\.(js|ts)$/.test(relativePath);
}

function readComponentDeprecations(filename) {
    const code = fs.readFileSync(filename, 'utf8');

    if (!/\bdeprecated\s*:/.test(code)) {
        return [];
    }

    let program;

    try {
        program = parse(code, { range: true });
    } catch {
        return [];
    }

    return findComponentDefinitions(program, filename).map(({ name, options }) => {
        const componentOption = getDeprecatedOption(options);
        const props = new Map(
            getPropDefinitions(options)
                .map((prop) => [prop.name, getDeprecatedOption(prop.definition)])
                .filter(([, option]) => option !== null)
                .map(([propName, option]) => [propName, readDeprecation(option)]),
        );

        return {
            name,
            deprecation: componentOption ? readDeprecation(componentOption) : null,
            props,
        };
    });
}

function buildDeprecatedOptionIndex(sourceRoot) {
    const index = new Map();

    fs.readdirSync(sourceRoot, { recursive: true })
        .filter(isComponentSourceCandidate)
        .flatMap((relativePath) => readComponentDeprecations(path.join(sourceRoot, relativePath)))
        .filter((component) => component.deprecation || component.props.size > 0)
        .forEach((component) => index.set(component.name, component));

    return index;
}

/**
 * Indexes the components under a source root whose definition, or one of whose props, carries a
 * `deprecated` option. Scanning runs once per root and ESLint process; later calls reuse the result.
 * The default root is the Administration's `src`, so extensions resolve the same index.
 *
 * @example
 * getDeprecatedOptionIndex().get('sw-image')?.props.get('emptyIcon');
 * // { version: 'v6.8.0.0', comment: 'Use "placeholderIcon" instead.' }
 */
function getDeprecatedOptionIndex(sourceRoot = ADMINISTRATION_SOURCES) {
    if (!indexesBySourceRoot.has(sourceRoot)) {
        indexesBySourceRoot.set(sourceRoot, buildDeprecatedOptionIndex(sourceRoot));
    }

    return indexesBySourceRoot.get(sourceRoot);
}

module.exports = {
    getDeprecatedOptionIndex,
};
