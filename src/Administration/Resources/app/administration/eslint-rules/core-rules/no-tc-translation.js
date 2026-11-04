/**
 * @sw-package framework
 */

const { loadRegistry } = require('../deprecation-rules/registry/load-registry');
const { isMigrationSelected } = require('../deprecation-rules/registry/filter-migrations');
const { appendRegistryContext } = require('../deprecation-rules/registry/format-migration-message');
const { scriptCodemodComment } = require('../deprecation-rules/registry/codemod-comment');
const espree = require('espree');

// vue-eslint-parser keeps mustaches of .html.twig templates as raw text, so their calls are parsed here.
const MUSTACHE_PATTERN = /{{([\s\S]*?)}}/g;

/**
 * Returns the registry's `global.$tc` migration and its rename step, which decide the fix level, the
 * review hint and the message context of this rule.
 *
 * @example
 * getTcMigration().renameUsage; // { kind: 'rename-call', from: '$tc', to: '$t', fix: 'unsafe-auto', … }
 */
function getTcMigration() {
    const migration = loadRegistry().globalApiMigrations.find((candidate) => candidate.id === 'global.$tc');
    const renameUsage = migration?.usage.find((usage) => usage.kind === 'rename-call');

    if (!renameUsage) {
        throw new Error('no-tc-translation needs the "global.$tc" migration with a rename-call usage in the deprecation registry.');
    }

    return { migration, renameUsage };
}

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

/**
 * Builds the fix that renames the translate callee. The registry decides whether the rename is fixed at all.
 * An unsafe automatic fix puts a codemod review comment in front of a call that passes more than the key:
 * only a plural count can select a different message, a key alone resolves the same in `$t`.
 *
 * @example
 * getRenameFix(fixer, { start: call.range[0], argumentCount: 2 }, callee.node.range, '$t', renameUsage);
 * // this.$tc('a.b', count) with fix 'unsafe-auto' becomes this.$t('a.b', count),
 * // preceded by a codemod review comment that carries the registry hint
 */
function getRenameFix(fixer, call, calleeRange, replacement, renameUsage) {
    if (renameUsage.fix === 'manual') {
        return null;
    }

    const rename = fixer.replaceTextRange(calleeRange, replacement);

    if (renameUsage.fix !== 'unsafe-auto' || call.argumentCount < 2) {
        return rename;
    }

    return [
        fixer.insertTextBeforeRange(
            [call.start, call.start],
            `${scriptCodemodComment(renameUsage.message ?? 'Verify this automatic rename.')} `,
        ),
        rename,
    ];
}

/**
 * Tells whether a script defines its own `$tc` — a method, an assigned property, a parameter or a
 * variable — so its `$tc` calls may not reach vue-i18n and are only reported.
 *
 * @example
 * definesOwnTc(parse('class Service { $tc(key) { return root.$tc(key); } }')); // true
 */
function definesOwnTc(ast) {
    const isTcKey = (key) => key?.type === 'Identifier' && key.name === '$tc';
    let found = false;

    (function visit(node) {
        if (found || !node || typeof node.type !== 'string') {
            return;
        }

        if (
            ((node.type === 'MethodDefinition' || node.type === 'Property') && !node.computed && isTcKey(node.key)) ||
            (node.type === 'AssignmentExpression' && node.left.type === 'MemberExpression' && isTcKey(node.left.property)) ||
            (node.type === 'VariableDeclarator' && isTcKey(node.id)) ||
            (/Function/.test(node.type) && node.params.some(isTcKey))
        ) {
            found = true;

            return;
        }

        Object.entries(node).forEach(([key, value]) => {
            if (key === 'parent') {
                return;
            }

            (Array.isArray(value) ? value : [value]).forEach((child) => {
                if (child && typeof child === 'object') {
                    visit(child);
                }
            });
        });
    })(ast);

    return found;
}

/**
 * Lists the translate calls inside the mustaches of a template text node with their source ranges. Twig
 * syntax that is no JavaScript is skipped.
 *
 * @example
 * findMustacheTranslateCalls(sourceCode, textNode);
 * // [{ callee: { name: '$tc', replacement: '$t', range: [12, 15] }, start: 12, argumentCount: 1 }]
 */
function findMustacheTranslateCalls(sourceCode, textNode) {
    const text = sourceCode.text.slice(textNode.range[0], textNode.range[1]);
    const calls = [];

    [...text.matchAll(MUSTACHE_PATTERN)].forEach((match) => {
        const expressionStart = textNode.range[0] + match.index + 2;
        let program;

        try {
            program = espree.parse(`(${match[1]})`, { ecmaVersion: 'latest', range: true });
        } catch {
            return;
        }

        (function visit(node) {
            if (!node || typeof node.type !== 'string') {
                return;
            }

            if (node.type === 'CallExpression') {
                const callee = getTranslateCallee(node.callee);

                if (callee) {
                    calls.push({
                        callee: { ...callee, range: callee.node.range.map((offset) => expressionStart + offset - 1) },
                        start: expressionStart + node.range[0] - 1,
                        argumentCount: node.arguments.length,
                    });
                }
            }

            Object.values(node).forEach((value) => {
                (Array.isArray(value) ? value : [value]).forEach((child) => {
                    if (child && typeof child === 'object') {
                        visit(child);
                    }
                });
            });
        })(program);
    });

    return calls;
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
            // The registry context is passed as data, so the registry loads only when the rule runs.
            noTc: 'Use {{replacement}}() instead of {{name}}(). {{name}} is deprecated — {{replacement}} handles pluralization natively.\n{{registryContext}}',
            noTcOwnDefinition: 'Use {{replacement}}() instead of {{name}}() where it calls vue-i18n. This file defines its own {{name}}, ' +
                'so the call is not renamed automatically.\n{{registryContext}}',
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
        const { migration, renameUsage } = getTcMigration();

        if (!isMigrationSelected(migration)) {
            return {};
        }

        const sourceCode = context.sourceCode;
        const registryContext = appendRegistryContext([renameUsage.message ?? ''], migration);
        const hasOwnTc = definesOwnTc(sourceCode.ast);

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
                const data = { name: callee.name, replacement: callee.replacement, registryContext };

                context.report(
                    hasOwnTc
                        ? { node: callee.node, messageId: 'noTcOwnDefinition', data }
                        : {
                              node: callee.node,
                              messageId: 'noTc',
                              data,
                              fix: (fixer) => {
                                  const call = { start: node.range[0], argumentCount: node.arguments.length };

                                  return getRenameFix(fixer, call, callee.node.range, callee.replacement, renameUsage);
                              },
                          },
                );
            }

            if (!hasOwnTc) {
                checkArguments(node, callee);
            }
        }

        function checkMustaches(textNode) {
            findMustacheTranslateCalls(sourceCode, textNode)
                .filter(({ callee }) => callee.name !== callee.replacement)
                .forEach((call) => {
                    context.report({
                        loc: {
                            start: sourceCode.getLocFromIndex(call.callee.range[0]),
                            end: sourceCode.getLocFromIndex(call.callee.range[1]),
                        },
                        messageId: 'noTc',
                        data: { name: call.callee.name, replacement: call.callee.replacement, registryContext },
                        fix: (fixer) => getRenameFix(fixer, call, call.callee.range, call.callee.replacement, renameUsage),
                    });
                });
        }

        const scriptVisitors = {
            CallExpression: checkCallExpression,
        };

        // Without vue-eslint-parser (e.g. .ts files) only the script visitors apply.
        const defineTemplateBodyVisitor = sourceCode.parserServices?.defineTemplateBodyVisitor;
        if (defineTemplateBodyVisitor) {
            return defineTemplateBodyVisitor({ CallExpression: checkCallExpression, VText: checkMustaches }, scriptVisitors);
        }

        return scriptVisitors;
    },
};
