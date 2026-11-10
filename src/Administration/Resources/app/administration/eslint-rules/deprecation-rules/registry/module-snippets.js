/**
 * @sw-package framework
 *
 * Checks the `snippets` property of `Module.register()`. The Administration loads the snippet files
 * of an active extension on its own: every `<language>.json` and `<language>-<COUNTRY>.json` below
 * `Resources/app/administration/src`, e.g. `en.json` or `de-DE.json`. The property can only go away
 * when every file it names is such a file.
 */

const fs = require('fs');
const path = require('path');
const { propertyName, staticString, unwrapTypeScript } = require('./component-scope');

const SNIPPET_FILE_NAME = /^[a-z]{2,3}(-[A-Z]{2})?\.json$/;
const ADMINISTRATION_SOURCE = path.join('Resources', 'app', 'administration', 'src');
// The extensions the Administration's bundler tries for an import without one, in its order.
const RESOLVED_EXTENSIONS = [
    '.mjs',
    '.js',
    '.mts',
    '.ts',
    '.jsx',
    '.tsx',
    '.json',
];

/**
 * The `Resources/app/administration/src` folder a file lies in, or null.
 *
 * @example
 * administrationSource('/ext/src/Resources/app/administration/src/main.js'); // '/ext/src/Resources/app/administration/src'
 */
function administrationSource(filename) {
    const marker = `${path.sep}${ADMINISTRATION_SOURCE}${path.sep}`;
    const index = filename.lastIndexOf(marker);

    return index === -1 ? null : filename.slice(0, index + marker.length - 1);
}

function isFile(candidate) {
    try {
        return fs.statSync(candidate).isFile();
    } catch {
        return false;
    }
}

/**
 * The file a relative import resolves to, like the bundler does: the path itself, then with each
 * extension, then the `index` file of a folder. Null for package imports and missing files.
 *
 * @example
 * resolveImport('/ext/src/module/index.js', './snippet/de-DE'); // '/ext/src/module/snippet/de-DE.js' when that file exists
 */
function resolveImport(filename, source) {
    if (!source.startsWith('./') && !source.startsWith('../')) {
        return null;
    }

    const base = path.resolve(path.dirname(filename), source);
    const candidates = [
        base,
        ...RESOLVED_EXTENSIONS.map((extension) => `${base}${extension}`),
        ...RESOLVED_EXTENSIONS.map((extension) => path.join(base, `index${extension}`)),
    ];

    return candidates.find(isFile) ?? null;
}

/**
 * Where a snippet value comes from: a default import (`import deDE from './de-DE.json'`), a dynamic
 * import (`() => import('./de-DE.json')`) or `require('./de-DE.json')`. Null for anything else, such
 * as an inline object.
 */
function snippetSource(sourceCode, value) {
    const node = unwrapTypeScript(value);

    if (node?.type === 'Identifier') {
        let variable = null;

        for (let scope = sourceCode.getScope(node); scope && !variable; scope = scope.upper) {
            variable = scope.set.get(node.name) ?? null;
        }

        const definition = variable?.defs[0];

        if (definition?.type !== 'ImportBinding' || definition.node.type !== 'ImportDefaultSpecifier') {
            return null;
        }

        return { source: definition.parent.source.value, declaration: definition.parent, variable };
    }

    const body = node?.type === 'ArrowFunctionExpression' && node.params.length === 0 ? unwrapTypeScript(node.body) : node;

    if (body?.type === 'ImportExpression') {
        const source = staticString(body.source);

        return source === null ? null : { source };
    }

    if (body?.type === 'CallExpression' && body.callee.type === 'Identifier' && body.callee.name === 'require') {
        const source = staticString(body.arguments[0]);

        return source === null ? null : { source };
    }

    return null;
}

/**
 * Why the Administration would not load a snippet file on its own, or null when it would.
 */
function snippetFileProblem(filename, root, source) {
    const resolved = resolveImport(filename, source);

    if (!resolved) {
        return `"${source}" is no snippet file of the extension; move its snippets into a <language>.json file below ${ADMINISTRATION_SOURCE}`;
    }

    const base = path.basename(resolved);

    if (path.extname(resolved) !== '.json') {
        return `"${source}" resolves to ${base}; the Administration only loads .json snippet files, so convert it to a <language>.json file`;
    }

    if (!root || !resolved.startsWith(`${root}${path.sep}`)) {
        return `move ${base} below ${ADMINISTRATION_SOURCE}`;
    }

    if (!SNIPPET_FILE_NAME.test(base)) {
        const suggestion = base.replace('_', '-');

        return SNIPPET_FILE_NAME.test(suggestion)
            ? `rename ${base} to ${suggestion}`
            : `rename ${base} to <language>.json or <language>-<COUNTRY>.json, e.g. de-DE.json`;
    }

    return null;
}

/**
 * The start of the line of `index` when only whitespace precedes it there, so the whole line goes.
 */
function lineStartOrSelf(text, index) {
    const lineStart = text.lastIndexOf('\n', index - 1) + 1;

    return text.slice(lineStart, index).trim() === '' ? lineStart : index;
}

/**
 * The range of an object property with the comma behind it, or before it when it is the last one,
 * and the indentation of its line.
 */
function propertyRemovalRange(sourceCode, property) {
    const after = sourceCode.getTokenAfter(property);

    if (after?.value === ',') {
        const next = sourceCode.getTokenAfter(after, { includeComments: true });

        return [
            lineStartOrSelf(sourceCode.text, property.range[0]),
            lineStartOrSelf(sourceCode.text, next.range[0]),
        ];
    }

    const before = sourceCode.getTokenBefore(property);

    return before?.value === ',' ? [before.range[0], property.range[1]] : property.range;
}

/**
 * The range of an import declaration with its line break.
 */
function importRemovalRange(sourceCode, declaration) {
    const text = sourceCode.text;
    const end = text[declaration.range[1]] === '\n' ? declaration.range[1] + 1 : declaration.range[1];

    return [lineStartOrSelf(text, declaration.range[0]), end];
}

/**
 * When the removed imports are all that precedes the code, the blank lines after them go as well, so
 * the file does not start with an empty line.
 *
 * @example
 * withoutLeadingBlankLines("import a from './a';\n\nfoo();", [[0, 21]]); // [[0, 22]]
 */
function withoutLeadingBlankLines(text, ranges) {
    const sorted = [...ranges].sort((left, right) => left[0] - right[0]);
    let position = 0;

    for (const range of sorted) {
        if (text.slice(position, range[0]).trim() !== '') {
            return sorted;
        }

        position = range[1];
    }

    const blankLines = sorted.length > 0 ? text.slice(position).match(/^(?:[ \t]*\n)+/) : null;

    if (!blankLines) {
        return sorted;
    }

    const last = sorted[sorted.length - 1];

    return [
        ...sorted.slice(0, -1),
        [
            last[0],
            last[1] + blankLines[0].length,
        ],
    ];
}

/**
 * Checks the `snippets` property of a `Module.register()` call. `problems` says for each snippet
 * file what to move, rename or convert; without problems, `fix` removes the property and the
 * imports nothing else uses.
 *
 * @example
 * const { problems, fix } = analyzeModuleSnippets(context, snippetsProperty);
 */
function analyzeModuleSnippets(context, property) {
    const sourceCode = context.sourceCode;
    const filename = context.physicalFilename ?? context.filename;
    const root = administrationSource(filename);
    const value = unwrapTypeScript(property.value);

    if (value?.type !== 'ObjectExpression' || value.properties.length === 0) {
        return {
            problems: [
                `put the snippet files below ${ADMINISTRATION_SOURCE}, named <language>.json or <language>-<COUNTRY>.json`,
            ],
            fix: null,
        };
    }

    const sources = [];
    const problems = [];

    value.properties.forEach((entry) => {
        const source = entry.type === 'Property' ? snippetSource(sourceCode, entry.value) : null;
        const locale = entry.type === 'Property' ? propertyName(entry) : null;

        if (!source) {
            problems.push(
                `move the snippets of ${locale ?? 'this entry'} into a ${locale ?? '<language>'}.json file below ${ADMINISTRATION_SOURCE}`,
            );

            return;
        }

        const problem = snippetFileProblem(filename, root, source.source);

        if (problem) {
            problems.push(problem);
        }

        sources.push(source);
    });

    if (problems.length > 0) {
        return { problems, fix: null };
    }

    // An import that is used anywhere else in the file stays.
    const unusedImports = sources
        .filter((source) => source.declaration?.specifiers.length === 1)
        .filter((source) => {
            return source.variable.references.every((reference) => {
                return reference.identifier.range[0] >= property.range[0] && reference.identifier.range[1] <= property.range[1];
            });
        })
        .map((source) => source.declaration);

    return {
        problems,
        fix: (fixer) => [
            fixer.removeRange(propertyRemovalRange(sourceCode, property)),
            ...withoutLeadingBlankLines(
                sourceCode.text,
                [...new Set(unusedImports)].map((declaration) => importRemovalRange(sourceCode, declaration)),
            ).map((range) => fixer.removeRange(range)),
        ],
    };
}

module.exports = {
    analyzeModuleSnippets,
};
