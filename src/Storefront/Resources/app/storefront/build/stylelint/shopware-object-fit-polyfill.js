const stylelint = require('stylelint');

const ruleName = 'shopware/object-fit-polyfill';
const messages = stylelint.utils.ruleMessages(ruleName, {
    expected: (value) => value,
});

/**
 * This rule forces the right polyfill syntax for "object-fit"
 * https://github.com/fregante/object-fit-images
 *
 * You need to use 'font-family' on 'img'. This allows the plugin to read the correct object fit value.
 * Polyfill syntax:
 *     object-fit: contain;
 *     font-family: 'object-fit: contain;'; // additional
 *
 * This rule is auto fixable. Just run stylelint with "--fix" and the polyfill values are added automatically.
 */
module.exports = stylelint.createPlugin(ruleName, (primaryOption, secondaryOptionObject, context) => {
    return (postCssRoot, postCssResult) => {
        postCssRoot.walkDecls((declaration) => {
            if (declaration.prop === 'object-fit') {
                const polyfillDeclaration = declaration.parent.nodes.find(node => {
                    return node.type === 'decl' && node.prop === 'font-family';
                });

                if (!polyfillDeclaration) {
                    if (context.fix) {
                        declaration.after(`font-family: '${declaration.prop}: ${declaration.value};'`);
                        return;
                    }

                    stylelint.utils.report({
                        result: postCssResult,
                        ruleName: ruleName,
                        message: messages.expected(`Missing object-fit polyfill for "${declaration.toString()}"`),
                        node: declaration,
                        word: declaration.value,
                    });
                    return;
                }

                const polyfillValue = polyfillDeclaration.value
                    .replace(/;/g, '') // removes ;
                    .replace(/["']/g, ''); // removes " and '

                const valueIsMatching = declaration.toString() === polyfillValue;

                if (!valueIsMatching) {
                    if (context.fix) {
                        polyfillDeclaration.value = `'${declaration.prop}: ${declaration.value};'`;
                        return;
                    }

                    stylelint.utils.report({
                        result: postCssResult,
                        ruleName: ruleName,
                        message: messages.expected(`Expected "${polyfillValue}" to match "${declaration.toString()}"`),
                        node: polyfillDeclaration,
                        word: declaration.value,
                    });
                }
            }
        });
    };
});

module.exports.ruleName = ruleName;
module.exports.messages = messages;
