/**
 * @sw-package framework
 */

const { loadRegistry } = require('../deprecation-rules/registry/load-registry');
const { isMigrationSelected } = require('../deprecation-rules/registry/filter-migrations');
const { appendRegistryContext } = require('../deprecation-rules/registry/format-migration-message');

const tcMigration = loadRegistry().globalApiMigrations.find((migration) => migration.id === 'global.$tc');
if (!tcMigration) {
    throw new Error('no-tc-translation needs the "global.$tc" migration in the deprecation registry.');
}

const renameUsage = tcMigration.usage.find((usage) => usage.kind === 'rename-call');
if (!renameUsage) {
    throw new Error('no-tc-translation needs a rename-call usage on the "global.$tc" deprecation registry migration.');
}

/**
 * Builds the fix that renames the called API. An unsafe automatic fix also puts a codemod review
 * comment in front of the whole call, so the rewritten call is easy to find and check.
 *
 * @example
 * getRenameFix(fixer, callNode, callNode.callee.property);
 * // this.$tc('a.b', count) with fix 'unsafe-auto' becomes this.$t('a.b', count),
 * // preceded by a codemod review comment that carries the registry hint
 */
function getRenameFix(fixer, callNode, target) {
    if (renameUsage.fix === 'manual') {
        return null;
    }

    const rename = fixer.replaceText(target, renameUsage.to);

    if (renameUsage.fix !== 'unsafe-auto') {
        return rename;
    }

    return [
        fixer.insertTextBefore(callNode, `/* TODO Codemod: ${renameUsage.message} */ `),
        rename,
    ];
}

function reportTc(context, node, isProperty) {
    const target = isProperty ? node.callee.property : node.callee;

    context.report({
        node: target,
        messageId: 'noTc',
        fix: (fixer) => getRenameFix(fixer, node, target),
    });
}

module.exports = {
    meta: {
        type: 'suggestion',
        docs: {
            description: 'Disallow $tc() in favor of $t() for translations',
            category: 'Best Practices',
            recommended: true,
        },
        fixable: 'code',
        schema: [],
        messages: {
            noTc: appendRegistryContext(
                [
                    `The "${renameUsage.from}" API is deprecated. Use ${renameUsage.to} instead.`,
                    renameUsage.message ?? '',
                ],
                tcMigration,
            ),
        },
    },

    create(context) {
        if (!isMigrationSelected(tcMigration)) {
            return {};
        }

        function checkCallExpression(node) {
            // Matches something.<from>(...)
            if (node.callee.type === 'MemberExpression' && node.callee.property.name === renameUsage.from) {
                reportTc(context, node, true);
            }

            // Matches bare <from>(...)
            if (node.callee.type === 'Identifier' && node.callee.name === renameUsage.from) {
                reportTc(context, node, false);
            }
        }

        // Script visitors (JS/TS files)
        const scriptVisitors = {
            CallExpression: checkCallExpression,
        };

        // Template visitors (Vue/Twig files)
        const templateVisitors = {
            CallExpression: checkCallExpression,
        };

        // Without vue-eslint-parser (e.g. .ts files) only the script visitors apply.
        const defineTemplateBodyVisitor = context.sourceCode.parserServices?.defineTemplateBodyVisitor;
        if (defineTemplateBodyVisitor) {
            return defineTemplateBodyVisitor(templateVisitors, scriptVisitors);
        }

        return scriptVisitors;
    },
};
