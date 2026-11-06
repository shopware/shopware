/**
 * @sw-package framework
 *
 * Shopware 6.8 throws for the `snippets` property of `Module.register()`. The Administration
 * loads snippet files of an active plugin on its own: every `<language>.json` and
 * `<language>-<COUNTRY>.json` below `Resources/app/administration/src`.
 *
 * The codemod removes the property, and the imports only it used, when every snippet file it
 * names is such a file. Otherwise it writes a TODO comment.
 */

const path = require('path');
const { createTodoReporter, todoMessages } = require('./utils/todo-comment');
const { dottedPath, propertyName, staticString } = require('./utils/component-context');

const SNIPPET_FILE = /^[a-z]{2,3}(-[A-Z]{2})?\.json$/;
const ADMINISTRATION_SOURCE = `${path.sep}Resources${path.sep}app${path.sep}administration${path.sep}src${path.sep}`;

const HINT = 'Module.register() no longer accepts snippets in 6.8. Remove the property and put the snippet files ' +
    'below Resources/app/administration/src, named like en.json or de-DE.json, so the Administration loads them on its own.';

/**
 * The `Resources/app/administration/src/` folder of a file, or null.
 *
 * @param {string} filename
 */
function administrationSource(filename) {
    const index = filename.lastIndexOf(ADMINISTRATION_SOURCE);

    return index === -1 ? null : filename.slice(0, index + ADMINISTRATION_SOURCE.length);
}

/**
 * The import source of a snippet value: a default import, `() => import('…')` or `require('…')`.
 *
 * @param {import('eslint').Scope.Scope} scope
 * @param {import('eslint').Rule.Node} value
 * @returns {{ source: string, declaration?: import('eslint').Rule.Node, variable?: import('eslint').Scope.Variable } | null}
 */
function snippetSource(scope, value) {
    if (value.type === 'Identifier') {
        let variable = null;

        for (let current = scope; current && !variable; current = current.upper) {
            variable = current.set.get(value.name) ?? null;
        }

        const definition = variable?.defs[0];

        if (definition?.type !== 'ImportBinding' || definition.node.type !== 'ImportDefaultSpecifier') {
            return null;
        }

        return { source: definition.parent.source.value, declaration: definition.parent, variable };
    }

    const body = value.type === 'ArrowFunctionExpression' && value.params.length === 0 ? value.body : value;

    if (body.type === 'ImportExpression') {
        const source = staticString(body.source);

        return source === null ? null : { source };
    }

    if (body.type === 'CallExpression' && body.callee.type === 'Identifier' && body.callee.name === 'require') {
        const source = staticString(body.arguments[0]);

        return source === null ? null : { source };
    }

    return null;
}

/**
 * Whether the Administration loads the file on its own.
 *
 * @param {string} filename the file with the `Module.register()` call
 * @param {string} source
 */
function loadsOnItsOwn(filename, source) {
    const root = administrationSource(filename);

    if (!root || !source.startsWith('.')) {
        return false;
    }

    const resolved = path.resolve(path.dirname(filename), path.extname(source) === '' ? `${source}.json` : source);

    return resolved.startsWith(root) && SNIPPET_FILE.test(path.basename(resolved));
}

/**
 * The range of a property including the comma behind it, or before it when it is the last one.
 *
 * @param {import('eslint').SourceCode} sourceCode
 * @param {import('eslint').Rule.Node} property
 */
function propertyRemovalRange(sourceCode, property) {
    const after = sourceCode.getTokenAfter(property);

    if (after?.value === ',') {
        const next = sourceCode.getTokenAfter(after, { includeComments: true });

        return [lineStartOrSelf(sourceCode, property), lineStartOrSelf(sourceCode, next)];
    }

    const before = sourceCode.getTokenBefore(property);

    return before?.value === ',' ? [before.range[0], property.range[1]] : property.range;
}

/**
 * The start of the line of a token when only whitespace precedes it, so the whole line goes away.
 *
 * @param {import('eslint').SourceCode} sourceCode
 * @param {{ range: [number, number] }} token
 */
function lineStartOrSelf(sourceCode, token) {
    const text = sourceCode.text;
    const lineStart = text.lastIndexOf('\n', token.range[0] - 1) + 1;

    return text.slice(lineStart, token.range[0]).trim() === '' ? lineStart : token.range[0];
}

/**
 * The range of a whole import declaration line.
 *
 * @param {import('eslint').SourceCode} sourceCode
 * @param {import('eslint').Rule.Node} declaration
 */
function importRemovalRange(sourceCode, declaration) {
    const text = sourceCode.text;
    const start = lineStartOrSelf(sourceCode, declaration);
    const end = text[declaration.range[1]] === '\n' ? declaration.range[1] + 1 : declaration.range[1];

    return [start, end];
}

/** @type {import('eslint').Rule.RuleModule} */
module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'Remove the snippets property of Module.register() that Shopware 6.8 rejects',
            category: 'Shopware 6.8 Migration',
            recommended: false,
        },
        fixable: 'code',
        schema: [],
        messages: {
            ...todoMessages,
            remove: 'Module.register() no longer accepts snippets in 6.8. The Administration loads the snippet files on its own.',
        },
    },
    create(context) {
        const sourceCode = context.sourceCode;
        const reporter = createTodoReporter(context);
        const filename = context.physicalFilename ?? context.filename;

        return {
            CallExpression(node) {
                const callee = dottedPath(node.callee);
                const options = node.arguments[1];

                if (!['Module.register', 'Shopware.Module.register'].includes(callee) || options?.type !== 'ObjectExpression') {
                    return;
                }

                const snippets = options.properties.find((property) => {
                    return property.type === 'Property' && propertyName(property) === 'snippets';
                });

                if (!snippets) {
                    return;
                }

                const scope = sourceCode.getScope(node);
                const values = snippets.value.type === 'ObjectExpression' ? snippets.value.properties : null;
                const sources = values?.map((property) => {
                    return property.type === 'Property' ? snippetSource(scope, property.value) : null;
                });

                if (!sources || sources.length === 0 || sources.some((source) => !source || !loadsOnItsOwn(filename, source.source))) {
                    reporter.reportScript(snippets, HINT);

                    return;
                }

                // An import used anywhere else in the file stays.
                const unusedImports = sources
                    .filter((source) => source.declaration && source.declaration.specifiers.length === 1)
                    .filter((source) => source.variable.references.every((reference) => {
                        return reference.identifier.range[0] >= snippets.range[0] && reference.identifier.range[1] <= snippets.range[1];
                    }))
                    .map((source) => source.declaration);

                context.report({
                    node: snippets,
                    messageId: 'remove',
                    fix: (fixer) => [
                        fixer.removeRange(propertyRemovalRange(sourceCode, snippets)),
                        ...[...new Set(unusedImports)].map((declaration) => fixer.removeRange(importRemovalRange(sourceCode, declaration))),
                    ],
                });
            },
        };
    },
};
