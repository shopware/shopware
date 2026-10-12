/**
 * @sw-package framework
 */

const fs = require('fs');
const path = require('path');
const { parse } = require('@typescript-eslint/typescript-estree');
const { loadRegistry } = require('./load-registry');
const {
    findComponentDefinitions,
    getDeprecatedOption,
    getPropCodemodNames,
    getPropDefinitions,
} = require('./component-deprecations');

const adminRoot = path.resolve(__dirname, '../../..');

/**
 * Maps every component directory under `src` to its index file, the way the Administration
 * names its components.
 *
 * @example
 * findComponentIndexFiles().get('sw-alert'); // ['/…/src/app/component/base/sw-alert/index.ts']
 */
function findComponentIndexFiles() {
    const indexFiles = new Map();

    fs.readdirSync(path.join(adminRoot, 'src'), { recursive: true }).forEach((relativePath) => {
        if (!/(^|\/)index\.(js|ts)$/.test(relativePath) || /(^|\/)(_mocks_|node_modules)\//.test(relativePath)) {
            return;
        }

        const directoryName = path.basename(path.dirname(relativePath));
        indexFiles.set(directoryName, [
            ...(indexFiles.get(directoryName) ?? []),
            path.join(adminRoot, 'src', relativePath),
        ]);
    });

    return indexFiles;
}

/**
 * Tells whether a node carries a `@deprecated` doc block right above it; line comments such as
 * eslint directives may sit in between.
 *
 * @example
 * hasDeprecatedDocBlock(propertyNode, source); // true for "/** @deprecated tag:v6.8.0 *\/ items: {…}"
 */
function hasDeprecatedDocBlock(node, { comments, code }) {
    return comments.some((comment) => {
        return (
            comment.type === 'Block' &&
            comment.value.includes('@deprecated') &&
            comment.range[1] <= node.range[0] &&
            code
                .slice(comment.range[1], node.range[0])
                .replace(/\/\/[^\n]*/g, '')
                .trim() === ''
        );
    });
}

// The doc-block fallback goes once the components carry the `deprecated` option.
function isMarkedDeprecated(objectExpression, leadingNode, source) {
    return getDeprecatedOption(objectExpression) !== null || hasDeprecatedDocBlock(leadingNode, source);
}

/**
 * Collects how a component's source marks deprecations: the `deprecated` option or a
 * `@deprecated` doc block, on the component itself and on each of its props.
 *
 * @example
 * readComponentDeprecations('sw-entity-listing', indexFiles);
 * // { found: true, componentDeprecated: true, deprecatedProps: ['items'] }
 */
function readComponentDeprecations(componentName, indexFiles) {
    const definitions = (indexFiles.get(componentName) ?? []).flatMap((filename) => {
        const code = fs.readFileSync(filename, 'utf8');
        const { body, comments } = parse(code, { comment: true, range: true });

        return findComponentDefinitions({ body }, filename)
            .filter(({ name }) => name === componentName)
            .map((definition) => ({ ...definition, source: { code, comments } }));
    });

    return {
        found: definitions.length > 0,
        componentDeprecated: definitions.some(({ options, statement, source }) => {
            return isMarkedDeprecated(options, statement, source);
        }),
        deprecatedProps: definitions.flatMap(({ options, source }) => {
            return getPropDefinitions(options)
                .filter((prop) => isMarkedDeprecated(prop.definition, prop.property, source))
                .map((prop) => prop.name);
        }),
    };
}

/**
 * Lists every registry component or prop codemod whose target is not marked deprecated in its source.
 * Prop codemods on a replacement component adapt the replaced component's API, so only codemods on
 * the component's own props need a prop marker.
 *
 * @example
 * findUnmarkedCodemodTargets(); // ['component.sw-foo: "sw-foo" is not marked deprecated']
 */
function findUnmarkedCodemodTargets() {
    const indexFiles = findComponentIndexFiles();

    return loadRegistry().componentApiMigrations.flatMap((migration) => {
        const deprecations = readComponentDeprecations(migration.component, indexFiles);

        if (!deprecations.found) {
            return [`${migration.id}: no source defines "${migration.component}"`];
        }

        const migratesOwnProps = !migration.replacement || migration.replacement === migration.component;

        if (!migratesOwnProps) {
            return deprecations.componentDeprecated
                ? []
                : [`${migration.id}: "${migration.component}" is not marked deprecated`];
        }

        return getPropCodemodNames(migration)
            .filter((propName) => !deprecations.deprecatedProps.includes(propName))
            .map((propName) => `${migration.id}: prop "${propName}" of "${migration.component}" is not marked deprecated`);
    });
}

describe('deprecation registry codemod targets', () => {
    it('counts only a @deprecated doc block that sits above the node', () => {
        const code = 'const first = 1;\n/** @deprecated tag:v6.8.0 */\nconst second = 2;';
        const { body, comments } = parse(code, { comment: true, range: true });

        expect(hasDeprecatedDocBlock(body[0], { code, comments })).toBe(false);
        expect(hasDeprecatedDocBlock(body[1], { code, comments })).toBe(true);
    });

    it('only migrates components and props that their source marks as deprecated', () => {
        expect(findUnmarkedCodemodTargets()).toEqual([]);
    });
});
