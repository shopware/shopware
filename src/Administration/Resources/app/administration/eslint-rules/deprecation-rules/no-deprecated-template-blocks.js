/**
 * @sw-package framework
 */

const { loadRegistry } = require('./registry/load-registry');
const { isMigrationSelected, selectedUsages } = require('./registry/filter-migrations');
const { appendRegistryContext } = require('./registry/format-migration-message');
const { FIX_OPTION_SCHEMA, canAutoFix, getObjectOption } = require('./registry/fix-policy');
const { withRunLevel } = require('./registry/run-level');
const { getTemplateOwner } = require('./registry/template-owner');

const SHARED_NAME_HINT = 'Several core components use this block name. Check which one the template overrides.';

function buildMessage(migration, usage, hint = usage.message) {
    const replacement = usage.to ? `Use "${usage.to}" instead.` : 'Remove or move this customization.';

    return appendRegistryContext([`The Administration Twig block "${usage.from}" is deprecated. ${hint ?? replacement}`], migration);
}

/**
 * Blanks Twig comments, so blocks in them are not found and every other index stays the same.
 *
 * @example
 * withoutTwigComments('{# {% block a %} #}'); // '                  '
 */
function withoutTwigComments(sourceText) {
    return sourceText.replace(/\{#[\s\S]*?#\}/g, (comment) => ' '.repeat(comment.length));
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
            pair.close = token.match;
        }
    });

    return pairs;
}

/**
 * Finds every block definition in a template, Twig `{% block %}` and `<sw-block name|extends>`,
 * with the range of its name, the range the block spans and the name range of an `{% endblock name %}`.
 *
 * @example
 * collectBlockMatches('{% block a %}{% endblock %}');
 * // [{ name: 'a', start: 9, end: 10, rangeStart: 0, rangeEnd: 27 }]
 */
function collectBlockMatches(sourceText, { twig = true } = {}) {
    const matches = [];
    const twigBlockNamePattern = /^\{%\s*block\s+([A-Za-z0-9_]+)\s*%}$/;
    const swBlockAttributePattern = /\s(?:name|extends)\s*=\s*(["'])([^"']+)\1/g;

    // Only `.html.twig` templates have Twig blocks
    const twigPairs = twig ? pairTokenRanges(sourceText, /\{%-?\s*block\s[^%]*%}/g, /\{%-?\s*endblock\b[^%]*%}/g) : [];

    twigPairs.forEach((pair) => {
        const nameMatch = twigBlockNamePattern.exec(pair.open[0].replace(/^\{%-/, '{%').replace(/-%}$/, '%}'));
        if (!nameMatch) {
            return;
        }

        const nameStart = pair.open.index + pair.open[0].indexOf(nameMatch[1]);
        const endName = pair.close && /endblock\s+([A-Za-z0-9_]+)/.exec(pair.close[0])?.[1];
        const endNameStart = endName ? pair.close.index + pair.close[0].lastIndexOf(endName) : null;
        matches.push({
            name: nameMatch[1],
            start: nameStart,
            end: nameStart + nameMatch[1].length,
            rangeStart: pair.rangeStart,
            rangeEnd: pair.rangeEnd,
            endNameRange: endName === nameMatch[1] ? [endNameStart, endNameStart + endName.length] : null,
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
 * Finds the usage step that covers an override of a block name: the one of a component the template overrides,
 * the only one of a name no other core component uses, `'unknown'` for a shared name of a template whose override
 * target is unknown, otherwise null.
 *
 * @example
 * findScopedMatch(matchesOfName, ['sw-cms-el-config-buy-box']); // the step listing sw-cms-el-config-buy-box
 */
function findScopedMatch(matches, targets) {
    const scoped = matches.find(({ usage }) => usage.components?.some((component) => targets.includes(component)));

    if (scoped) {
        return scoped;
    }

    if (matches.length === 1 && !matches[0].usage.shared) {
        return matches[0];
    }

    return targets.length === 0 ? 'unknown' : null;
}

/**
 * This rule reports overrides of deprecated Administration Twig blocks and renames them, and the named
 * `{% endblock %}`, to their replacement, unless the replacement block is overridden in the same file as well.
 * A block name several core components use is only matched when the scripts next to the template tell which
 * component it overrides; templates of components the extension registers itself are skipped.
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
        const migrations = loadRegistry().templateBlockMigrations;
        const usages = selectedUsages(migrations, null, context);
        // In an extension, a deprecated block nested in its replacement overrides both, so an upgrade codemod reports it
        const allowsWrappers = !getObjectOption(context).ids;

        return {
            Program() {
                const sourceCode = context.sourceCode;
                const sourceText = sourceCode.getText();

                const twig = /\.twig$/.test(context.physicalFilename ?? context.filename ?? '');
                const blockMatches = collectBlockMatches(withoutTwigComments(sourceText), { twig });
                let owner = null;

                blockMatches.forEach((blockMatch) => {
                    const matchesOfName = usages.filter(({ usage }) => usage.from === blockMatch.name);

                    if (matchesOfName.length === 0) {
                        return;
                    }

                    owner ??= getTemplateOwner(context);

                    if (owner.ownComponent) {
                        return;
                    }

                    const match = findScopedMatch(matchesOfName, owner.targets);
                    const loc = {
                        start: sourceCode.getLocFromIndex(blockMatch.start),
                        end: sourceCode.getLocFromIndex(blockMatch.end),
                    };

                    if (match === 'unknown') {
                        // The first migration that lists the name reports it, so it is reported once
                        const first = migrations.find(({ usage }) => usage.some(({ from }) => from === blockMatch.name));

                        if (isMigrationSelected(first, context)) {
                            context.report({ loc, message: buildMessage(first, { from: blockMatch.name }, SHARED_NAME_HINT) });
                        }

                        return;
                    }

                    if (!match || (allowsWrappers && isBackwardCompatibilityWrapper(match, blockMatch, blockMatches))) {
                        return;
                    }

                    // Renaming a sibling override of the replacement block would define that block twice.
                    const replacementBlockExists = blockMatches.some(({ name }) => name === match.usage.to);

                    context.report({
                        loc,
                        message: buildMessage(
                            match.migration,
                            match.usage,
                            replacementBlockExists
                                ? `Use "${match.usage.to}" instead, which this template overrides already. Merge both overrides.`
                                : undefined,
                        ),
                        fix(fixer) {
                            if (!canAutoFix(context, match.usage) || !match.usage.to || replacementBlockExists) {
                                return null;
                            }

                            return [
                                [blockMatch.start, blockMatch.end],
                                ...(blockMatch.endNameRange ? [blockMatch.endNameRange] : []),
                            ].map((range) => fixer.replaceTextRange(range, match.usage.to));
                        },
                    });
                });
            },
        };
    },
});
