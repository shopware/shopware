/**
 * @sw-package framework
 */

const { loadRegistry } = require('./registry/load-registry');
const { filterMigrations } = require('./registry/filter-migrations');
const { appendRegistryContext } = require('./registry/format-migration-message');
const { canAutoFix } = require('./registry/fix-policy');
const { withRunLevel } = require('./registry/run-level');

// The asset filter takes paths with or without `bundles/` and the doubled `administration/` folder.
const ASSET_PATH = /^(\/?(?:bundles\/)?administration\/(?:administration\/)?)((?:static|src)\/[\w./-]+)$/;

/**
 * Indexes the deprecated files of the registry by their path relative to the Administration root.
 *
 * @example
 * indexAssets(migrations).get('/static/img/sw-login-background.png'); // { migration, usage }
 */
function indexAssets(migrations) {
    const index = new Map();

    migrations.forEach((migration) => {
        const [usage] = migration.usage;

        migration.files.forEach((file) => index.set(file, { migration, usage }));
    });

    return index;
}

/**
 * The path of a converted file, e.g. `.png` → `.webp`, or null for a step without replacement.
 *
 * @example
 * convertedPath({ kind: 'replace-extension', from: ['.png'], to: '.webp' }, 'a/b.png'); // 'a/b.webp'
 */
function convertedPath(usage, value) {
    if (usage.kind !== 'replace-extension') {
        return null;
    }

    const extension = usage.from.find((candidate) => value.endsWith(candidate));

    return extension ? `${value.slice(0, -extension.length)}${usage.to}` : null;
}

/**
 * This rule reports string literals and static template attributes that point to a deprecated
 * Administration asset, e.g. `assetFilter('/administration/administration/static/img/cms/preview_plant_small.jpg')`,
 * and points them to the converted file. The image-deprecation vite plugin covers imports.
 *
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = withRunLevel({
    meta: {
        type: 'problem',
        docs: {
            description: 'No paths to deprecated Administration assets',
            recommended: true,
        },
        fixable: 'code',
        schema: [],
    },
    create(context) {
        const assets = indexAssets(filterMigrations(loadRegistry().assetMigrations));
        const sourceCode = context.sourceCode;

        if (assets.size === 0) {
            return {};
        }

        /**
         * @param {{ range: [number, number] }} node the literal, quotes included
         * @param {string} value
         */
        const check = (node, value) => {
            const path = ASSET_PATH.exec(value);
            const match = path ? assets.get(`/${path[2]}`) : null;

            if (!match) {
                return;
            }

            const replacement = convertedPath(match.usage, value);
            const replacementText = replacement
                ? ` Use "${replacement}" instead.`
                : ' Remove it, there is no replacement.';
            const quote = sourceCode.text[node.range[0]];

            context.report({
                node,
                message: appendRegistryContext([`The asset "${value}" is deprecated.${replacementText}`], match.migration),
                fixLevel: match.usage.fix,
                fix: replacement && canAutoFix(context, match.usage)
                    ? (fixer) => fixer.replaceTextRange(node.range, `${quote}${replacement}${quote}`)
                    : null,
            });
        };

        const scriptVisitor = {
            Literal(node) {
                if (typeof node.value === 'string') {
                    check(node, node.value);
                }
            },

            TemplateLiteral(node) {
                if (node.expressions.length === 0) {
                    check(node, node.quasis[0].value.cooked);
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

                // A static attribute, e.g. `src="/administration/static/img/sw-login-background.png"`.
                VLiteral(node) {
                    const isQuoted = /^["']/.test(sourceCode.text[node.range[0]]);

                    if (node.parent.type === 'VAttribute' && !node.parent.directive && isQuoted) {
                        check(node, node.value);
                    }
                },
            },
            scriptVisitor,
        );
    },
});
