/**
 * @sw-package framework
 */

// eslint-disable-next-line import/no-extraneous-dependencies
const utils = require('eslint-plugin-vue/lib/utils');

function reportTc(context, node, isProperty) {
    const target = isProperty ? node.callee.property : node.callee;

    context.report({
        node: target,
        messageId: 'noTc',
        fix(fixer) {
            return fixer.replaceText(target, '$t');
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
            noTc: 'Use $t() instead of $tc(). $tc is deprecated — $t handles pluralization natively.',
        },
    },

    create(context) {
        function checkCallExpression(node) {
            // Matches something.$tc(...)
            if (
                node.callee.type === 'MemberExpression' &&
                node.callee.property.name === '$tc'
            ) {
                reportTc(context, node, true);
            }

            // Matches bare $tc(...)
            if (
                node.callee.type === 'Identifier' &&
                node.callee.name === '$tc'
            ) {
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
