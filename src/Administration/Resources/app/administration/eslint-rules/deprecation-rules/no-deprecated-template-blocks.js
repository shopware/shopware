/**
 * @sw-package framework
 */

const { loadRegistry } = require('./registry/load-registry');
const { selectedUsages } = require('./registry/filter-migrations');
const { appendRegistryContext } = require('./registry/format-migration-message');
const { FIX_OPTION_SCHEMA, canAutoFix } = require('./registry/fix-policy');
const { withRunLevel } = require('./registry/run-level');

function buildMessage(migration, usage) {
    const replacement = usage.to ? ` Use "${usage.to}" instead.` : ' Remove or move this customization.';

    return appendRegistryContext([`The Administration Twig block "${usage.from}" is deprecated.${replacement}`], migration);
}

/**
 * Pairs every opening token with its closing token, so each block knows the source range it spans.
 * An opening token without a closing one spans to the end of its own tag.
 *
 * @example
 * pairTokenRanges('{% block a %}{% endblock %}', /\{%\s*block\s[^%]*%}/g, /\{%\s*endblock\b[^%]*%}/g);
 * // [{ open: <RegExp match for "{% block a %}">, rangeStart: 0, rangeEnd: 27 }]
 */
function pairTokenRanges(sourceText, openPattern, closePattern, isSelfClosing = () => false) {
    const tokens = [
        ...[...sourceText.matchAll(openPattern)].map((match) => ({ type: 'open', match })),
        ...[...sourceText.matchAll(closePattern)].map((match) => ({ type: 'close', match })),
    ].sort((a, b) => a.match.index - b.match.index);

    const pairs = [];
    const stack = [];

    tokens.forEach((token) => {
        const tokenEnd = token.match.index + token.match[0].length;

        if (token.type === 'open') {
            const pair = { open: token.match, rangeStart: token.match.index, rangeEnd: tokenEnd };
            pairs.push(pair);

            if (!isSelfClosing(token.match)) {
                stack.push(pair);
            }

            return;
        }

        const pair = stack.pop();
        if (pair) {
            pair.rangeEnd = tokenEnd;
        }
    });

    return pairs;
}

/**
 * Finds every block definition in a template, Twig `{% block %}` and `<sw-block name|extends>`,
 * with the range of its name and the range the block spans.
 *
 * @example
 * collectBlockMatches('{% block a %}{% endblock %}');
 * // [{ name: 'a', start: 9, end: 10, rangeStart: 0, rangeEnd: 27 }]
 */
function collectBlockMatches(sourceText) {
    const matches = [];
    const twigBlockNamePattern = /^\{%\s*block\s+([A-Za-z0-9_]+)\s*%}$/;
    const swBlockAttributePattern = /\s(?:name|extends)\s*=\s*(["'])([^"']+)\1/g;

    pairTokenRanges(sourceText, /\{%-?\s*block\s[^%]*%}/g, /\{%-?\s*endblock\b[^%]*%}/g).forEach((pair) => {
        const nameMatch = twigBlockNamePattern.exec(pair.open[0].replace(/^\{%-/, '{%').replace(/-%}$/, '%}'));
        if (!nameMatch) {
            return;
        }

        const nameStart = pair.open.index + pair.open[0].indexOf(nameMatch[1]);
        matches.push({
            name: nameMatch[1],
            start: nameStart,
            end: nameStart + nameMatch[1].length,
            rangeStart: pair.rangeStart,
            rangeEnd: pair.rangeEnd,
        });
    });

    pairTokenRanges(sourceText, /<sw-block\b[^>]*>/g, /<\/sw-block\s*>/g, (open) => open[0].endsWith('/>')).forEach(
        (pair) => {
            [...pair.open[0].matchAll(swBlockAttributePattern)].forEach((attributeMatch) => {
                const blockName = attributeMatch[2];
                const blockNameStart = pair.open.index + attributeMatch.index + attributeMatch[0].lastIndexOf(blockName);

                matches.push({
                    name: blockName,
                    start: blockNameStart,
                    end: blockNameStart + blockName.length,
                    rangeStart: pair.rangeStart,
                    rangeEnd: pair.rangeEnd,
                });
            });
        },
    );

    return matches;
}

/**
 * A deprecated block nested inside its replacement block is the BC wrapper that keeps old extensions working,
 * not an extension usage. A replacement block elsewhere in the file (a sibling override) does not count.
 *
 * @example
 * // {% block new_block %}{% block old_block %}{% endblock %}{% endblock %}
 * isBackwardCompatibilityWrapper({ usage: { from: 'old_block', to: 'new_block' } }, oldBlockMatch, allBlockMatches); // true
 */
function isBackwardCompatibilityWrapper(match, blockMatch, blockMatches) {
    if (!match.usage.to) {
        return false;
    }

    return blockMatches.some((candidate) => {
        return (
            candidate.name === match.usage.to &&
            candidate.rangeStart <= blockMatch.rangeStart &&
            candidate.rangeEnd >= blockMatch.rangeEnd &&
            candidate !== blockMatch
        );
    });
}

/**
 * This rule reports overrides of deprecated Administration Twig blocks and renames them to their
 * replacement, unless the replacement block is overridden in the same file as well.
 *
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = withRunLevel({
    meta: {
        type: 'suggestion',
        docs: {
            description: 'No usage of deprecated Administration Twig blocks',
            recommended: true,
        },
        fixable: 'code',
        schema: FIX_OPTION_SCHEMA,
    },

    create(context) {
        const usages = selectedUsages(loadRegistry().templateBlockMigrations);

        return {
            Program() {
                const sourceCode = context.sourceCode;
                const sourceText = sourceCode.getText();

                const blockMatches = collectBlockMatches(sourceText);

                blockMatches.forEach((blockMatch) => {
                    const match = usages.find(({ usage }) => usage.from === blockMatch.name);

                    if (!match || isBackwardCompatibilityWrapper(match, blockMatch, blockMatches)) {
                        return;
                    }

                    // Renaming a sibling override of the replacement block would define that block twice.
                    const replacementBlockExists = blockMatches.some(({ name }) => name === match.usage.to);

                    context.report({
                        loc: {
                            start: sourceCode.getLocFromIndex(blockMatch.start),
                            end: sourceCode.getLocFromIndex(blockMatch.end),
                        },
                        message: buildMessage(match.migration, match.usage),
                        fix(fixer) {
                            if (!canAutoFix(context, match.usage) || !match.usage.to || replacementBlockExists) {
                                return null;
                            }

                            return fixer.replaceTextRange(
                                [
                                    blockMatch.start,
                                    blockMatch.end,
                                ],
                                match.usage.to,
                            );
                        },
                    });
                });
            },
        };
    },
});
