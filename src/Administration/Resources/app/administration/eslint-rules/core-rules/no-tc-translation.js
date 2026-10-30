/**
 * @sw-package framework
 */

// eslint-plugin-vue 10 ships as ESM under dist/ (utils sit on the module's
// `default` export); older layouts exported them directly from lib/.
 
const utilsModule = require('eslint-plugin-vue/dist/utils');
const utils = utilsModule.default ?? utilsModule;

// Keys of vue-i18n's `TranslateOptions`, keep in sync with `src/core/helper/i18n-legacy-syntax.helper.ts`.
const TRANSLATE_OPTION_KEYS = new Set([
    'list',
    'named',
    'plural',
    'default',
    'locale',
    'missingWarn',
    'fallbackWarn',
    'escapeParameter',
    'resolvedMessage',
    'part',
]);

const NON_PLURAL_ARGUMENT_TYPES = new Set([
    'ObjectExpression',
    'ArrayExpression',
    'TemplateLiteral',
    'SpreadElement',
]);

const NON_NAMED_ARGUMENT_TYPES = new Set([
    'Literal',
    'TemplateLiteral',
    'ArrayExpression',
]);

function isSnippetObject(node) {
    return (node.type === 'Identifier' && node.name === 'Snippet') ||
        (node.type === 'MemberExpression' &&
            !node.computed &&
            node.property.name === 'Snippet' &&
            node.object.type === 'Identifier' &&
            node.object.name === 'Shopware');
}

/**
 * Returns the callee node to rename and its replacement name, or null when the call is no translation call.
 */
function getTranslateCallee(callee) {
    if (callee.type === 'Identifier' && ['$t', '$tc'].includes(callee.name)) {
        return { node: callee, name: callee.name, replacement: '$t' };
    }

    if (callee.type !== 'MemberExpression' || callee.computed) {
        return null;
    }

    const name = callee.property.name;
    if (['$t', '$tc'].includes(name)) {
        return { node: callee.property, name, replacement: '$t' };
    }

    if (['t', 'tc'].includes(name) && isSnippetObject(callee.object)) {
        return { node: callee.property, name, replacement: 't' };
    }

    return null;
}

function isPluralCandidate(node) {
    return !NON_PLURAL_ARGUMENT_TYPES.has(node.type) && !(node.type === 'Literal' && typeof node.value !== 'number');
}

function isNumberLiteral(node) {
    return node.type === 'Literal' && typeof node.value === 'number';
}

function isStringLiteral(node) {
    return (node.type === 'Literal' && typeof node.value === 'string') || node.type === 'TemplateLiteral';
}

/**
 * Returns `legacyArgumentOrder` for an autofixable vue-i18n 8 argument order, `legacyArgumentOrderManual` when the named
 * parameters are no object literal or contain spread or computed keys and could also be vue-i18n 10 options, or null.
 */
function getLegacyArgumentOrder(args) {
    if (args.length !== 3) {
        return null;
    }

    const [, plural, named] = args;
    if (!isPluralCandidate(plural)) {
        return null;
    }

    if (named.type !== 'ObjectExpression') {
        return isNumberLiteral(plural) && !NON_NAMED_ARGUMENT_TYPES.has(named.type) ? 'legacyArgumentOrderManual' : null;
    }

    let hasUnknownKey = false;
    const hasNamedParameter = named.properties.some((property) => {
        if (property.type !== 'Property' || property.computed) {
            hasUnknownKey = true;

            return false;
        }

        const keyName = property.key.type === 'Identifier' ? property.key.name : String(property.key.value);

        return !TRANSLATE_OPTION_KEYS.has(keyName);
    });

    if (hasNamedParameter) {
        return 'legacyArgumentOrder';
    }

    return hasUnknownKey && isNumberLiteral(plural) ? 'legacyArgumentOrderManual' : null;
}

/**
 * vue-i18n 8 accepted a locale in `$tc(key, choice, locale)` and `$tc(key, choice, locale, named)`. `$t` reads a string
 * after the plural count as default message and ignores a fourth argument, so the locale has to become an option.
 */
function getLegacyTcLocale(args) {
    if (args.some((arg) => arg.type === 'SpreadElement')) {
        return null;
    }

    if (args.length === 4) {
        return 'legacyTcLocaleNamed';
    }

    if (args.length === 3 && isPluralCandidate(args[1]) && isStringLiteral(args[2])) {
        return 'legacyTcLocale';
    }

    return null;
}

module.exports = {
    meta: {
        type: 'suggestion',
        docs: {
            description: 'Disallow $tc() and the vue-i18n 8 argument order in favor of $t(key, named, plural)',
            category: 'Best Practices',
            recommended: true,
        },
        fixable: 'code',
        schema: [],
        messages: {
            noTc: 'Use {{replacement}}() instead of {{name}}(). {{name}} is deprecated — {{replacement}} handles pluralization natively.',
            legacyArgumentOrder: 'Pass the named parameters before the plural count: {{name}}(key, named, plural). ' +
                'The vue-i18n 8 order {{name}}(key, plural, named) is deprecated.',
            legacyArgumentOrderManual: 'The third argument of {{name}}(key, plural, …) could be named parameters. ' +
                'If it holds named parameters, pass them before the plural count: {{name}}(key, named, plural). ' +
                'If it holds a locale, pass it as option: {{name}}(key, plural, { locale }). ' +
                'The vue-i18n 8 order {{name}}(key, plural, named) is deprecated.',
            legacyTcLocale: 'Pass the locale as option: {{name}}(key, plural, { locale }). ' +
                '{{name}}() reads a string after the plural count as default message.',
            legacyTcLocaleNamed: 'Pass the locale and the plural count as options: {{name}}(key, named, { plural, locale }). ' +
                '{{name}}() ignores a fourth argument.',
        },
    },

    create(context) {
        const sourceCode = context.sourceCode ?? context.getSourceCode();

        function checkArguments(node, callee) {
            const args = node.arguments;
            const data = { name: callee.replacement };
            const legacyTcLocale = callee.name !== callee.replacement ? getLegacyTcLocale(args) : null;

            if (legacyTcLocale === 'legacyTcLocaleNamed') {
                const [, plural, locale, named] = args;
                const text = `${sourceCode.getText(named)}, { plural: ${sourceCode.getText(plural)}, locale: ${sourceCode.getText(locale)} }`;

                context.report({
                    node,
                    messageId: legacyTcLocale,
                    data,
                    fix(fixer) {
                        return fixer.replaceTextRange([plural.range[0], named.range[1]], text);
                    },
                });

                return;
            }

            if (legacyTcLocale === 'legacyTcLocale') {
                const locale = args[2];

                context.report({
                    node,
                    messageId: legacyTcLocale,
                    data,
                    fix(fixer) {
                        return fixer.replaceText(locale, `{ locale: ${sourceCode.getText(locale)} }`);
                    },
                });

                return;
            }

            const legacyArgumentOrder = getLegacyArgumentOrder(args);
            if (legacyArgumentOrder === 'legacyArgumentOrderManual') {
                context.report({
                    node,
                    messageId: legacyArgumentOrder,
                    data,
                });
            } else if (legacyArgumentOrder) {
                const [, plural, named] = args;

                context.report({
                    node,
                    messageId: legacyArgumentOrder,
                    data,
                    fix(fixer) {
                        return [
                            fixer.replaceText(plural, sourceCode.getText(named)),
                            fixer.replaceText(named, sourceCode.getText(plural)),
                        ];
                    },
                });
            }
        }

        function checkCallExpression(node) {
            const callee = getTranslateCallee(node.callee);
            if (!callee) {
                return;
            }

            if (callee.name !== callee.replacement) {
                context.report({
                    node: callee.node,
                    messageId: 'noTc',
                    data: { name: callee.name, replacement: callee.replacement },
                    fix(fixer) {
                        return fixer.replaceText(callee.node, callee.replacement);
                    },
                });
            }

            checkArguments(node, callee);
        }

        const scriptVisitors = {
            CallExpression: checkCallExpression,
        };

        if (sourceCode.parserServices?.defineTemplateBodyVisitor) {
            return utils.defineTemplateBodyVisitor(context, { CallExpression: checkCallExpression }, scriptVisitors);
        }

        return scriptVisitors;
    },
};
