/**
 * @sw-package framework
 */

// eslint-plugin-vue 10 ships as ESM under dist/ (utils sit on the module's
// `default` export); older layouts exported them directly from lib/.
// eslint-disable-next-line import/no-extraneous-dependencies
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

/**
 * Returns `legacyArgumentOrder` for an autofixable vue-i18n 8 argument order, `legacyArgumentOrderManual` when the named
 * parameters are no object literal and could also be vue-i18n 10 options, or null.
 */
function getLegacyArgumentOrder(args) {
    if (args.length !== 3) {
        return null;
    }

    const [, plural, named] = args;
    if (NON_PLURAL_ARGUMENT_TYPES.has(plural.type) || (plural.type === 'Literal' && typeof plural.value !== 'number')) {
        return null;
    }

    if (named.type !== 'ObjectExpression') {
        const isPluralLiteral = plural.type === 'Literal' && typeof plural.value === 'number';

        return isPluralLiteral && !NON_NAMED_ARGUMENT_TYPES.has(named.type) ? 'legacyArgumentOrderManual' : null;
    }

    const hasNamedParameter = named.properties.some((property) => {
        if (property.type !== 'Property' || property.computed) {
            return true;
        }

        const keyName = property.key.type === 'Identifier' ? property.key.name : String(property.key.value);

        return !TRANSLATE_OPTION_KEYS.has(keyName);
    });

    return hasNamedParameter ? 'legacyArgumentOrder' : null;
}

// Shopware's vue-eslint-parser patch keeps `{{ }}` in `.twig` templates as opaque text, so mustache calls are only
// visible as VText and have to be matched textually.
const TWIG_MUSTACHE_PATTERN = /{{([\s\S]*?)}}/g;
const TWIG_TC_CALL_PATTERN = /(?<![\w$])\$tc(?=\s*\()/g;

function* findTcInTwigText(text) {
    for (const mustache of text.matchAll(TWIG_MUSTACHE_PATTERN)) {
        const expressionStart = mustache.index + 2;

        for (const call of mustache[1].matchAll(TWIG_TC_CALL_PATTERN)) {
            yield expressionStart + call.index;
        }
    }
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
            legacyArgumentOrderManual: 'The third argument of {{name}}(key, plural, …) is no object literal. ' +
                'If it holds named parameters, pass them before the plural count: {{name}}(key, named, plural). ' +
                'The vue-i18n 8 order {{name}}(key, plural, named) is deprecated.',
        },
    },

    create(context) {
        const sourceCode = context.sourceCode ?? context.getSourceCode();

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

            const legacyArgumentOrder = getLegacyArgumentOrder(node.arguments);
            if (legacyArgumentOrder === 'legacyArgumentOrderManual') {
                context.report({
                    node,
                    messageId: legacyArgumentOrder,
                    data: { name: callee.replacement },
                });
            } else if (legacyArgumentOrder) {
                const [, plural, named] = node.arguments;

                context.report({
                    node,
                    messageId: legacyArgumentOrder,
                    data: { name: callee.replacement },
                    fix(fixer) {
                        return [
                            fixer.replaceText(plural, sourceCode.getText(named)),
                            fixer.replaceText(named, sourceCode.getText(plural)),
                        ];
                    },
                });
            }
        }

        function checkTwigText(node) {
            const text = sourceCode.getText(node);

            for (const offset of findTcInTwigText(text)) {
                const start = node.range[0] + offset;
                const range = [start, start + '$tc'.length];

                context.report({
                    loc: {
                        start: sourceCode.getLocFromIndex(range[0]),
                        end: sourceCode.getLocFromIndex(range[1]),
                    },
                    messageId: 'noTc',
                    data: { name: '$tc', replacement: '$t' },
                    fix(fixer) {
                        return fixer.replaceTextRange(range, '$t');
                    },
                });
            }
        }

        const scriptVisitors = {
            CallExpression: checkCallExpression,
        };

        if (sourceCode.parserServices?.defineTemplateBodyVisitor) {
            const templateVisitors = { CallExpression: checkCallExpression };

            if (context.filename.endsWith('.twig')) {
                templateVisitors.VText = checkTwigText;
            }

            return utils.defineTemplateBodyVisitor(context, templateVisitors, scriptVisitors);
        }

        return scriptVisitors;
    },
};
