/**
 * @sw-package framework
 */

// eslint-disable-next-line import/no-extraneous-dependencies
const utils = require('eslint-plugin-vue/lib/utils');

function getOverrideCalleePrefix(callee) {
    if (callee.type !== 'MemberExpression' || callee.property.name !== 'override') {
        return null;
    }

    const obj = callee.object;

    // Component.override(...)
    if (obj.type === 'Identifier' && obj.name === 'Component') {
        return 'Component';
    }

    // Shopware.Component.override(...)
    if (
        obj.type === 'MemberExpression' &&
        obj.property.name === 'Component' &&
        obj.object.type === 'Identifier' &&
        obj.object.name === 'Shopware'
    ) {
        return 'Shopware.Component';
    }

    return null;
}

module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'Require an explicit overrideIndex priority on Component.override calls',
            category: 'Best Practices',
            recommended: true,
        },
        hasSuggestions: true,
        schema: [],
        messages: {
            missingPriority:
                '{{prefix}}.override() must specify a priority as the third argument. ' +
                'Use {{prefix}}.CONST.OVERRIDE_PRIORITY.{CORE,STOREFRONT_ADMIN_MODULES,COMMERCIAL,DEFAULT}.',
            suggestCore: 'Use {{prefix}}.CONST.OVERRIDE_PRIORITY.CORE (Administration bundle override)',
            suggestStorefront:
                'Use {{prefix}}.CONST.OVERRIDE_PRIORITY.STOREFRONT_ADMIN_MODULES (Storefront-bundle admin module override)',
        },
    },

    create(context) {
        const filename = (context.getFilename && context.getFilename()) || '';
        const isStorefrontAdmin = filename.includes('/Storefront/Resources/app/administration/');

        function checkCallExpression(node) {
            const prefix = getOverrideCalleePrefix(node.callee);
            if (!prefix) return;
            if (node.arguments.length >= 3) return;

            // Only offer auto-fix suggestions when the call has the canonical 2 args
            // (component name + config). Other arities are reported without fixes.
            const canSuggest = node.arguments.length === 2;
            const lastArg = canSuggest ? node.arguments[1] : null;

            const coreSuggestion = {
                messageId: 'suggestCore',
                data: { prefix },
                fix(fixer) {
                    return fixer.insertTextAfter(
                        lastArg,
                        `, ${prefix}.CONST.OVERRIDE_PRIORITY.CORE`,
                    );
                },
            };

            const storefrontSuggestion = {
                messageId: 'suggestStorefront',
                data: { prefix },
                fix(fixer) {
                    return fixer.insertTextAfter(
                        lastArg,
                        `, ${prefix}.CONST.OVERRIDE_PRIORITY.STOREFRONT_ADMIN_MODULES`,
                    );
                },
            };

            // Surface the suggestion that matches this file's bundle first; the other stays
            // available so non-canonical layouts can still pick the alternative.
            const suggest = canSuggest
                ? isStorefrontAdmin
                    ? [storefrontSuggestion, coreSuggestion]
                    : [coreSuggestion, storefrontSuggestion]
                : [];

            context.report({
                node,
                messageId: 'missingPriority',
                data: { prefix },
                suggest,
            });
        }

        const visitors = {
            CallExpression: checkCallExpression,
        };

        if (context.parserServices && context.parserServices.defineTemplateBodyVisitor) {
            return utils.defineTemplateBodyVisitor(context, visitors, visitors);
        }

        return visitors;
    },
};
