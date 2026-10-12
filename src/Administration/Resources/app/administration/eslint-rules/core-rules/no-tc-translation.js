/**
 * @sw-package framework
 */

// eslint-plugin-vue 10 ships as ESM under dist/ (utils sit on the module's
// `default` export); older layouts exported them directly from lib/.
const utilsModule = require('eslint-plugin-vue/dist/utils');
const utils = utilsModule.default ?? utilsModule;
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

function reportTc(context, node, isProperty) {
    const target = isProperty ? node.callee.property : node.callee;

    context.report({
        node: target,
        messageId: 'noTc',
        fix(fixer) {
            if (renameUsage.fix === 'manual') {
                return null;
            }

            return fixer.replaceText(target, renameUsage.to);
        },
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

        // If vue parser is available, register both script and template visitors
        if (context.parserServices && context.parserServices.defineTemplateBodyVisitor) {
            return utils.defineTemplateBodyVisitor(context, templateVisitors, scriptVisitors);
        }

        // Fallback for non-vue files (plain JS/TS)
        return scriptVisitors;
    },
};
