/**
 * @sw-package framework
 */

const path = require('path');
const { loadRegistry } = require('./registry/load-registry');
const { selectedUsages } = require('./registry/filter-migrations');
const { appendRegistryContext } = require('./registry/format-migration-message');
const { FIX_OPTION_SCHEMA, canAutoFix } = require('./registry/fix-policy');
const { withRunLevel } = require('./registry/run-level');

// The `files` of asset migrations are relative to this folder.
const ADMINISTRATION_ROOT = path.resolve(__dirname, '../..');

/**
 * The public URL of a file of the Administration bundle, as the asset filter and links take it:
 * `[/][bundles/]administration/[administration/]static/…`; Vite builds double the `administration` folder.
 * An extension's own `bundles/<name>/administration/static/…` does not match.
 */
const PUBLIC_URL = /^(\/?(?:bundles\/)?administration\/(?:administration\/)?)(static\/.+)$/;

const RELATIVE_PATH = /^\.\.?\//;

// Vite resolves `/static/…` and `/src/…` against the Administration root, `src/…` with an alias.
const ROOT_PATH = /^\/(?:static|src)\//;
const SRC_ALIAS = /^src\//;

function isInAdministration(absolutePath) {
    return absolutePath === ADMINISTRATION_ROOT || absolutePath.startsWith(`${ADMINISTRATION_ROOT}${path.sep}`);
}

function toAdministrationFile(absolutePath) {
    return `/${path.relative(ADMINISTRATION_ROOT, absolutePath).split(path.sep).join('/')}`;
}

/**
 * Resolves a path to the Administration file it references, relative to the Administration root, and
 * how to write another Administration file in the same form; null for a path to no Administration file.
 * Root and alias paths only reference the Administration in its own files, an extension resolves them
 * against its own folder.
 *
 * @example
 * resolveReference('/bundles/administration/static/img/a.png', '/ext/src/main.js');
 * // { file: '/static/img/a.png', format: (file) => '/bundles/administration/static/…' }
 * resolveReference('./assets/a.png', '<admin>/src/module/sw-x/index.js');
 * // { file: '/src/module/sw-x/assets/a.png', format: (file) => '../../…' }
 */
function resolveReference(pathPart, filename) {
    const publicUrl = PUBLIC_URL.exec(pathPart);

    if (publicUrl) {
        const [, prefix, file] = publicUrl;

        return {
            file: `/${file}`,
            format: (newFile) => (newFile.startsWith('/static/') ? `${prefix}${newFile.slice(1)}` : null),
        };
    }

    const directory = path.dirname(path.resolve(filename));

    if (RELATIVE_PATH.test(pathPart)) {
        const absolutePath = path.resolve(directory, pathPart);

        if (!isInAdministration(absolutePath)) {
            return null;
        }

        return {
            file: toAdministrationFile(absolutePath),
            format: (newFile) => {
                const relativePath = path
                    .relative(directory, path.join(ADMINISTRATION_ROOT, newFile))
                    .split(path.sep)
                    .join('/');

                return relativePath.startsWith('.') ? relativePath : `./${relativePath}`;
            },
        };
    }

    if (!isInAdministration(directory)) {
        return null;
    }

    if (ROOT_PATH.test(pathPart)) {
        return { file: pathPart, format: (newFile) => newFile };
    }

    if (SRC_ALIAS.test(pathPart)) {
        return {
            file: `/${pathPart}`,
            format: (newFile) => (SRC_ALIAS.test(newFile.slice(1)) ? newFile.slice(1) : newFile),
        };
    }

    return null;
}

/**
 * Indexes the selected asset steps by file: a `replace-extension` step only applies to the files with
 * one of its extensions, the other steps to every file of their migration.
 *
 * @example
 * indexAssetUsages(registry.assetMigrations).get('/static/img/a.png'); // { migration, usage: { kind: 'replace-extension', … } }
 */
function indexAssetUsages(assetMigrations) {
    const index = new Map();

    selectedUsages(assetMigrations).forEach(({ migration, usage }) => {
        migration.files.forEach((file) => {
            const applies = usage.kind !== 'replace-extension' || usage.from.includes(path.posix.extname(file));

            if (applies && !index.has(file)) {
                index.set(file, { migration, usage });
            }
        });
    });

    return index;
}

/**
 * The path that replaces a reference, or null when the step has no replacement or it cannot be written
 * in the form of the reference, such as a module file behind a public URL.
 */
function getReplacement(usage, reference, pathPart) {
    if (usage.kind === 'replace-extension') {
        return `${pathPart.slice(0, -path.posix.extname(reference.file).length)}${usage.to}`;
    }

    if (usage.kind === 'replace-asset') {
        return reference.format(usage.to);
    }

    return null;
}

function buildHeadline(usage, pathPart, replacement) {
    if (replacement) {
        return `The Administration asset "${pathPart}" is removed. Use "${replacement}" instead.`;
    }

    if (usage.kind === 'remove-asset') {
        return `The Administration asset "${pathPart}" is removed without replacement.`;
    }

    return `The Administration asset "${pathPart}" is removed. Use the Administration file "${usage.to}" instead.`;
}

/**
 * This rule reports references to asset files that the registry's asset migrations remove, and points
 * them to the replacement: string literals in scripts and template expressions (imports, `assetFilter()`
 * arguments, URLs) and static attribute values in templates. It matches the public URL of the
 * Administration bundle (`/bundles/administration/static/…`, `administration/static/…`), and in files
 * of the Administration itself also root (`/static/…`), alias (`src/…`) and relative paths. A query or
 * hash after the path stays. SCSS files and `url()` in styles are not checked; update them by hand.
 *
 * - `replace-extension` → the same path with the new extension
 * - `replace-asset` → the replacement file, written in the form of the reference
 * - `remove-asset` → reported only
 *
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = withRunLevel({
    meta: {
        type: 'problem',
        docs: {
            description: 'No references to removed Administration asset files',
            recommended: true,
        },
        fixable: 'code',
        schema: FIX_OPTION_SCHEMA,
    },

    create(context) {
        const usagesByFile = indexAssetUsages(loadRegistry().assetMigrations);
        const sourceCode = context.sourceCode;
        const filename = context.physicalFilename ?? context.filename;

        /**
         * Reports a string that references a removed asset. `quote` is written around the replacement,
         * which replaces the whole node.
         */
        function check(node, value, quote) {
            const queryStart = value.search(/[?#]/);
            const pathPart = queryStart === -1 ? value : value.slice(0, queryStart);
            const query = queryStart === -1 ? '' : value.slice(queryStart);
            const reference = resolveReference(pathPart, filename);
            const match = reference && usagesByFile.get(reference.file);

            if (!match) {
                return;
            }

            const { migration, usage } = match;
            const replacement = getReplacement(usage, reference, pathPart);

            context.report({
                node,
                message: appendRegistryContext(
                    [buildHeadline(usage, pathPart, replacement), usage.message ?? ''],
                    migration,
                ),
                fixLevel: replacement ? usage.fix : 'manual',
                reviewComment: [buildHeadline(usage, pathPart, replacement), usage.message].filter(Boolean).join(' '),
                fix:
                    replacement && canAutoFix(context, usage)
                        ? (fixer) => fixer.replaceText(node, `${quote}${replacement}${query}${quote}`)
                        : null,
            });
        }

        const scriptVisitor = {
            Literal(node) {
                if (typeof node.value === 'string') {
                    check(node, node.value, sourceCode.getText(node)[0]);
                }
            },

            TemplateLiteral(node) {
                if (node.expressions.length === 0) {
                    check(node, node.quasis[0].value.cooked ?? '', '`');
                }
            },
        };
        const defineTemplateBodyVisitor = sourceCode.parserServices?.defineTemplateBodyVisitor;

        if (!defineTemplateBodyVisitor) {
            return scriptVisitor;
        }

        return defineTemplateBodyVisitor(
            {
                ...scriptVisitor,

                // A static attribute, e.g. `src="/administration/static/img/sw-login-background.png"`
                VLiteral(node) {
                    if (node.parent?.type !== 'VAttribute' || node.parent.directive) {
                        return;
                    }

                    const quote = sourceCode.getText(node)[0];

                    check(node, node.value, /["']/.test(quote) ? quote : '');
                },
            },
            scriptVisitor,
        );
    },
});
