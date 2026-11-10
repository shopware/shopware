/**
 * @sw-package framework
 */

const { loadRegistry } = require('./registry/load-registry');
const { selectedUsages } = require('./registry/filter-migrations');
const { appendRegistryContext } = require('./registry/format-migration-message');
const { FIX_OPTION_SCHEMA, canAutoFix } = require('./registry/fix-policy');
const { withRunLevel } = require('./registry/run-level');

// A `{{ … }}` segment of a template text
const MUSTACHE = /\{\{([\s\S]*?)}}/g;

// A JavaScript string literal: its quote and its content
const STRING_LITERAL = /(['"`])((?:\\.|(?!\1)[^\\])*)\1/g;

/**
 * Finds the selected step a string uses, and how: `exact` for the step's key itself, `child` for a key
 * below it, `prefix` for a string that ends with the parent of the key, which code completes at runtime,
 * e.g. `$t('global.sw-condition.condition.cartTaxDisplay.' + type)`.
 *
 * @example
 * matchSnippetKey('a.b', usages); // { migration, usage: { from: 'a.b', … }, relation: 'exact', keys: ['a.b'] }
 * matchSnippetKey('a.', usages); // { …, relation: 'prefix', keys: ['a.b', 'a.c'] }
 */
function matchSnippetKey(value, usages) {
    const used = usages.find(({ usage }) => value === usage.from || value.startsWith(`${usage.from}.`));

    if (used) {
        return { ...used, relation: value === used.usage.from ? 'exact' : 'child', keys: [used.usage.from] };
    }

    if (!value.endsWith('.')) {
        return null;
    }

    const completed = usages.filter(({ usage }) => {
        return usage.from.startsWith(value) && !usage.from.slice(value.length).includes('.');
    });

    return completed.length > 0
        ? { ...completed[0], relation: 'prefix', keys: completed.map(({ usage }) => usage.from) }
        : null;
}

function buildHeadline({ usage, relation, keys }, value) {
    if (relation === 'prefix') {
        const removedKeys = keys.map((key) => `"${key}"`).join(', ');

        return `The Administration snippet key built from "${value}" can resolve to ${
            keys.length > 1 ? 'one of the removed keys' : 'the removed key'
        } ${removedKeys}.`;
    }

    if (usage.kind === 'rename-snippet-key') {
        return relation === 'exact'
            ? `The Administration snippet key "${value}" is removed. Use "${usage.to}" instead.`
            : `The Administration snippet key "${value}" uses removed key "${usage.from}", which moved to "${usage.to}".`;
    }

    return `The Administration snippet key "${value}" uses removed key "${usage.from}".`;
}

/**
 * Lists the string literals inside the `{{ … }}` segments of a template text, with their range in the
 * text. vue-eslint-parser keeps mustaches of .html.twig templates as raw VText, so their snippet keys
 * never reach a Literal visitor.
 *
 * @example
 * findMustacheStringLiterals("Hi {{ $t('a.b') }}"); // [{ quote: "'", value: 'a.b', start: 9, end: 14 }]
 */
function findMustacheStringLiterals(text) {
    return [...text.matchAll(MUSTACHE)].flatMap((mustache) => {
        const expressionStart = mustache.index + 2;

        return [...mustache[1].matchAll(STRING_LITERAL)].map((literal) => ({
            quote: literal[1],
            value: literal[2],
            start: expressionStart + literal.index,
            end: expressionStart + literal.index + literal[0].length,
        }));
    });
}

/**
 * This rule reports snippet keys the registry lists as removed or renamed: in string literals of
 * scripts and template expressions, in static attribute values, and in the mustaches of .html.twig
 * templates. A string that is exactly a renamed key gets the new key, keeping its quotes; keys below a
 * removed or renamed key and keys built at runtime are only reported.
 *
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = withRunLevel({
    meta: {
        type: 'problem',
        docs: {
            description: 'No usage of removed Administration snippet keys',
            recommended: true,
        },
        fixable: 'code',
        schema: FIX_OPTION_SCHEMA,
    },

    create(context) {
        const usages = selectedUsages(loadRegistry().snippetKeyMigrations);
        const sourceCode = context.sourceCode;

        /**
         * Reports a string that uses a removed key. `literal` is the range and quote of the whole string,
         * for strings a fix can replace.
         */
        function check(node, value, literal = null) {
            const match = matchSnippetKey(value, usages);

            if (!match) {
                return;
            }

            const { migration, usage, relation } = match;
            const isRename = usage.kind === 'rename-snippet-key' && relation === 'exact' && literal !== null;
            const loc = literal && {
                start: sourceCode.getLocFromIndex(literal.range[0]),
                end: sourceCode.getLocFromIndex(literal.range[1]),
            };

            context.report({
                node,
                ...(loc ? { loc } : {}),
                message: appendRegistryContext([buildHeadline(match, value), usage.message ?? ''], migration),
                fixLevel: isRename ? usage.fix : 'manual',
                fix:
                    isRename && canAutoFix(context, usage)
                        ? (fixer) => fixer.replaceTextRange(literal.range, `${literal.quote}${usage.to}${literal.quote}`)
                        : null,
            });
        }

        function checkLiteral(node) {
            if (typeof node.value === 'string') {
                check(node, node.value, { range: node.range, quote: sourceCode.getText(node)[0] });
            }
        }

        // Only the first part of a template literal can start a key; with expressions it is a key prefix at most.
        function checkTemplateLiteral(node) {
            const value = node.quasis[0].value.cooked;

            if (typeof value === 'string') {
                check(node, value, node.expressions.length === 0 ? { range: node.range, quote: '`' } : null);
            }
        }

        function checkStaticAttributeValue(node) {
            if (node.parent?.type !== 'VAttribute' || node.parent.directive) {
                return;
            }

            const quote = sourceCode.getText(node)[0];

            check(node, node.value, { range: node.range, quote: /["']/.test(quote) ? quote : '' });
        }

        function checkTemplateText(node) {
            findMustacheStringLiterals(sourceCode.getText(node)).forEach(({ quote, value, start, end }) => {
                const dynamicStart = quote === '`' ? value.indexOf('${') : -1;

                if (dynamicStart !== -1) {
                    check(node, value.slice(0, dynamicStart));

                    return;
                }

                check(node, value, { range: [node.range[0] + start, node.range[0] + end], quote });
            });
        }

        const scriptVisitor = {
            Literal: checkLiteral,
            TemplateLiteral: checkTemplateLiteral,
        };
        // Plain .ts files run without vue-eslint-parser, so only templates get the template visitor.
        const defineTemplateBodyVisitor = sourceCode.parserServices?.defineTemplateBodyVisitor;

        if (!defineTemplateBodyVisitor) {
            return scriptVisitor;
        }

        return defineTemplateBodyVisitor(
            {
                ...scriptVisitor,
                VLiteral: checkStaticAttributeValue,
                VText: checkTemplateText,
            },
            scriptVisitor,
        );
    },
});
