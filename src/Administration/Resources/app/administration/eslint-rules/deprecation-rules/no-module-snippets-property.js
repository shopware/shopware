/**
 * @sw-package framework
 *
 * Upgrade codemod `module-snippets`: 6.8 removes the `snippets` property of `Shopware.Module.register()`, and with
 * the `v6.8.0.0` feature flag a module that still passes it throws on registration. The server loads the snippet
 * files of a plugin's `Resources/app/administration/src` by name already in 6.7: `<locale>.json` (`de-DE.json`) and
 * `<language>.json` (`de.json`) in any folder below it.
 *
 * The rule removes the property, and the imports only it used, when every value is an identifier imported from such
 * a `.json` file below the `src` folder of the linted file, named after its key (`'de-DE': deDE` from `de-DE.json`
 * or `de.json`). In every other case the snippets would go missing, so the property gets a codemod comment:
 * inline objects, JS modules, other file names (`de_DE.json`) or files outside of `src`.
 */

const fs = require('fs');
const path = require('path');
const { reportTodo } = require('./upgrade-helpers/todo-comment');
const { unwrapOptions } = require('./upgrade-helpers/override-target');
const { propertyName } = require('./upgrade-helpers/member-usage');
const { shopwareAliases, memberPath } = require('./upgrade-helpers/global-calls');

const ID = 'module-snippets';
const SOURCE_ROOT = `${path.sep}Resources${path.sep}app${path.sep}administration${path.sep}src${path.sep}`;
// The order in which Vite resolves an import without extension
const EXTENSIONS = ['.mjs', '.js', '.mts', '.ts', '.jsx', '.tsx', '.json'];

const MESSAGES = {
    removed: 'Shopware.Module.register() no longer accepts snippets in 6.8 and throws with the v6.8.0.0 feature flag. '
        + 'The server loads these snippet files already, so the property is removed.',
    todo: 'Shopware.Module.register() no longer accepts snippets in 6.8 and throws with the v6.8.0.0 feature flag. '
        + 'Move the snippets to JSON files below the administration src folder, named after the language (de.json) '
        + 'or the locale (de-DE.json), then remove this property.',
};

function isFile(file) {
    try {
        return fs.statSync(file).isFile();
    } catch {
        return false;
    }
}

/** The file a relative import resolves to, the way Vite resolves it. */
function resolveImport(importer, source) {
    if (!source.startsWith('./') && !source.startsWith('../')) {
        return null;
    }

    const base = path.resolve(path.dirname(importer), source);

    if (path.extname(base) && isFile(base)) {
        return base;
    }

    return EXTENSIONS.map((extension) => `${base}${extension}`).find(isFile) ?? null;
}

/**
 * Whether the server finds the snippet file for this key: a `.json` file named after the locale or language, below
 * the `src` folder the linted file is in, and not in `node_modules` or a dot folder.
 */
function isServerLoaded(file, key, filename) {
    const rootEnd = filename.lastIndexOf(SOURCE_ROOT);

    if (!file || rootEnd === -1 || path.extname(file) !== '.json') {
        return false;
    }

    const root = filename.slice(0, rootEnd + SOURCE_ROOT.length);
    const relative = path.relative(root, file);
    const name = path.basename(file, '.json');

    return !relative.startsWith('..')
        && !path.isAbsolute(relative)
        && !relative.split(path.sep).some((segment) => segment === 'node_modules' || segment.startsWith('.'))
        && (name === key || name === key.split('-')[0]);
}

/** The range of a node and the line it stands on, when nothing else is on that line. */
function lineRange(text, range) {
    const lineStart = text.lastIndexOf('\n', range[0] - 1) + 1;
    const lineEnd = text.indexOf('\n', range[1]);

    if (text.slice(lineStart, range[0]).trim() !== '' || text.slice(range[1], lineEnd === -1 ? text.length : lineEnd).trim() !== '') {
        return null;
    }

    return [lineStart, lineEnd === -1 ? text.length : lineEnd + 1];
}

/** @type {import('eslint').Rule.RuleModule} */
module.exports = {
    meta: {
        type: 'problem',
        fixable: 'code',
        docs: {
            description: 'Remove the snippets property of Shopware.Module.register(), which throws with the v6.8.0.0 feature flag',
        },
        schema: [],
    },

    /** @param {import('eslint').Rule.RuleContext} context */
    create(context) {
        const sourceCode = context.sourceCode;
        const filename = context.physicalFilename ?? context.filename ?? '';
        let aliases = new Map();

        /** The import declarations to remove with the property, or null when the snippets would go missing. */
        function removableImports(property) {
            if (property.value.type !== 'ObjectExpression') {
                return null;
            }

            const declarations = [];

            for (const entry of property.value.properties) {
                const key = propertyName(entry);

                if (!key || entry.value.type !== 'Identifier') {
                    return null;
                }

                const declaration = sourceCode.ast.body.find(
                    (statement) => statement.type === 'ImportDeclaration'
                        && statement.specifiers.some((specifier) => specifier.type === 'ImportDefaultSpecifier' && specifier.local.name === entry.value.name),
                );

                if (!declaration || !isServerLoaded(resolveImport(filename, declaration.source.value), key, filename)) {
                    return null;
                }

                const specifier = declaration.specifiers.find(({ local }) => local.name === entry.value.name);
                const [variable] = sourceCode.getDeclaredVariables(specifier);
                const usedElsewhere = variable.references.some(
                    ({ identifier }) => identifier.range[0] < property.range[0] || identifier.range[1] > property.range[1],
                );

                if (declaration.specifiers.length === 1 && !usedElsewhere && !declarations.includes(declaration)) {
                    declarations.push(declaration);
                }
            }

            return declarations;
        }

        /** The ranges that remove the property with its comma, and its line when it stands alone. */
        function propertyRemoval(property, options) {
            const text = sourceCode.text;
            const after = sourceCode.getTokenAfter(property);
            const end = after.value === ',' ? after.range[1] : property.range[1];
            const ownLine = lineRange(text, [property.range[0], end]);

            if (ownLine) {
                // Between blank lines, or between a blank line and the end of the object, take one blank line along.
                const blankBefore = ownLine[0] > 0 && /\n[ \t]*\n$/.test(text.slice(0, ownLine[0]));
                const rest = text.slice(ownLine[1]);
                const closesNext = /^[ \t]*[}\]]/.test(rest);

                if (blankBefore && (/^[ \t]*\n/.test(rest) || closesNext)) {
                    return [text.lastIndexOf('\n', ownLine[0] - 2) + 1, ownLine[1]];
                }

                return ownLine;
            }

            if (after.value !== ',' && options.properties.length > 1) {
                return [sourceCode.getTokenBefore(property).range[0], property.range[1]];
            }

            return [property.range[0], end];
        }

        /** The ranges that remove the import lines, with a blank line after a block of them that is left empty. */
        function importRemovals(declarations) {
            const text = sourceCode.text;
            const ranges = declarations
                .map((declaration) => lineRange(text, declaration.range) ?? declaration.range)
                .sort((left, right) => left[0] - right[0]);
            const merged = [];

            for (const range of ranges) {
                const previous = merged[merged.length - 1];

                if (previous && previous[1] === range[0]) {
                    previous[1] = range[1];
                } else {
                    merged.push([...range]);
                }
            }

            return merged.map(([start, end]) => {
                const before = text.slice(0, start);
                const blankBefore = start === 0 || /\n[ \t]*\n$/.test(before);
                const blankAfter = /^[ \t]*\n/.exec(text.slice(end));

                return blankBefore && blankAfter ? [start, end + blankAfter[0].length] : [start, end];
            });
        }

        return {
            Program(node) {
                aliases = shopwareAliases(node);
            },
            CallExpression(node) {
                if (memberPath(node.callee, aliases) !== 'Shopware.Module.register') {
                    return;
                }

                const options = unwrapOptions(node.arguments[1] ?? null);
                const property = options?.properties.find((candidate) => candidate.type === 'Property' && propertyName(candidate) === 'snippets');

                if (!property) {
                    return;
                }

                const declarations = removableImports(property);

                if (!declarations) {
                    reportTodo(context, { id: ID, message: MESSAGES.todo, range: property.range, scope: 'script' });

                    return;
                }

                context.report({
                    node: property,
                    message: MESSAGES.removed,
                    fix: (fixer) => [
                        fixer.removeRange(propertyRemoval(property, options)),
                        ...importRemovals(declarations).map((range) => fixer.removeRange(range)),
                    ],
                });
            },
        };
    },
};
