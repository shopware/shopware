/**
 * @sw-package framework
 */

const { loadRegistry } = require('./registry/load-registry');
const { selectedUsages } = require('./registry/filter-migrations');
const { appendRegistryContext } = require('./registry/format-migration-message');
const { FIX_OPTION_SCHEMA, canAutoFix } = require('./registry/fix-policy');
const { withRunLevel } = require('./registry/run-level');
const { fileComponents } = require('./registry/component-scope');

// The name of a `{% block %}` or `{% endblock %}` tag without whitespace control. The name is required
// and shares no character with `\s`, so the two `\s` runs around it cannot backtrack into each other.
const TWIG_BLOCK_NAME = /^\{%\s*block\s+([A-Za-z0-9_]+)\s*%}$/;
const TWIG_END_BLOCK_NAME = /^\{%\s*endblock\s+([A-Za-z0-9_]+)\s*%}$/;

/**
 * Pairs every opening token with its closing token, so each block knows the source range it spans.
 * An opening token without a closing one spans to the end of its own tag.
 *
 * @example
 * pairTokenRanges('{% block a %}{% endblock %}', /\{%\s*block\s[^%]*%}/g, /\{%\s*endblock\b[^%]*%}/g);
 * // [{ open: <match of "{% block a %}">, close: <match of "{% endblock %}">, rangeStart: 0, rangeEnd: 27 }]
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
            const pair = { open: token.match, close: null, rangeStart: token.match.index, rangeEnd: tokenEnd };
            pairs.push(pair);

            if (!isSelfClosing(token.match)) {
                stack.push(pair);
            }

            return;
        }

        const pair = stack.pop();
        if (pair) {
            pair.close = token.match;
            pair.rangeEnd = tokenEnd;
        }
    });

    return pairs;
}

/**
 * The name of a Twig tag and where it starts in the source, or null when the tag has no plain name.
 *
 * @example
 * twigTagName({ 0: '{%- endblock a -%}', index: 10 }, TWIG_END_BLOCK_NAME); // { name: 'a', start: 23 }
 */
function twigTagName(tag, namePattern) {
    const nameMatch = namePattern.exec(tag[0].replace(/^\{%-/, '{%').replace(/-%}$/, '%}'));

    if (!nameMatch) {
        return null;
    }

    return { name: nameMatch[1], start: tag.index + tag[0].indexOf(nameMatch[1], tag[0].indexOf('block') + 5) };
}

/**
 * Finds every block definition in a template, Twig `{% block %}` and `<sw-block name|extends>`, with
 * the ranges of its name (also in a named `{% endblock name %}`) and the range the block spans.
 *
 * @example
 * collectBlockMatches('{% block a %}{% endblock a %}');
 * // [{ name: 'a', start: 9, end: 10, nameRanges: [[9, 10], [26, 27]], rangeStart: 0, rangeEnd: 29 }]
 */
function collectBlockMatches(sourceText) {
    const matches = [];
    const swBlockAttributePattern = /\s(?:name|extends)\s*=\s*(["'])([^"']+)\1/g;

    pairTokenRanges(sourceText, /\{%-?\s*block\s[^%]*%}/g, /\{%-?\s*endblock\b[^%]*%}/g).forEach((pair) => {
        const open = twigTagName(pair.open, TWIG_BLOCK_NAME);

        if (!open) {
            return;
        }

        const close = pair.close ? twigTagName(pair.close, TWIG_END_BLOCK_NAME) : null;
        const nameRanges = [[open.start, open.start + open.name.length]];

        if (close?.name === open.name) {
            nameRanges.push([close.start, close.start + close.name.length]);
        }

        matches.push({
            name: open.name,
            start: open.start,
            end: open.start + open.name.length,
            nameRanges,
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
                    nameRanges: [[blockNameStart, blockNameStart + blockName.length]],
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
 * isBackwardCompatibilityWrapper({ usage: { kind: 'rename-template-block', from: 'old_block', to: 'new_block' } }, oldBlockMatch, allBlockMatches); // true
 */
function isBackwardCompatibilityWrapper(match, blockMatch, blockMatches) {
    if (match.usage.kind !== 'rename-template-block') {
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
 * Whether a step applies to the template: steps without `component` apply everywhere, the others only
 * to templates of the component or of one of the components that extend it.
 *
 * @example
 * isInScope({ component: 'sw-x', extendedBy: ['sw-x-create'] }, () => ['sw-x-create']); // true
 */
function isInScope(usage, getComponents) {
    if (!usage.component) {
        return true;
    }

    return [
        usage.component,
        ...(usage.extendedBy ?? []),
    ].some((name) => getComponents().includes(name));
}

/**
 * The source offsets of every template expression that reads one of the variables without defining
 * it, e.g. `active` in `v-if="active === 'x'"`, but not in `'is--active'`, `tab.active` or inside a
 * `#content="{ active }"` slot of the override itself.
 *
 * @example
 * freeVariableOffsets(templateBody, 'active'); // [35]
 */
function freeVariableOffsets(templateBody, variable) {
    const offsets = [];
    const visit = (node) => {
        if (node.type === 'VExpressionContainer') {
            (node.references ?? []).forEach((reference) => {
                if (!reference.variable && reference.id.name === variable) {
                    offsets.push(reference.id.range[0]);
                }
            });

            return;
        }

        if (node.type !== 'VElement') {
            return;
        }

        node.startTag.attributes.forEach((attribute) => {
            if (attribute.directive && attribute.value) {
                visit(attribute.value);
            }
        });
        node.children.forEach(visit);
    };

    if (templateBody) {
        visit(templateBody);
    }

    return offsets;
}

/**
 * The headline of a report: the deprecated block and what to do with an override of it.
 *
 * @example
 * describeUsage({ kind: 'remove-template-block', from: 'sw_a', to: 'sw_b', component: 'sw-x' });
 * // 'The Administration Twig block "sw_a" of "sw-x" is deprecated. Move this customization to "sw_b".'
 */
function describeUsage(usage) {
    const block = `The Administration Twig block "${usage.from}"${usage.component ? ` of "${usage.component}"` : ''} is deprecated.`;

    if (usage.kind === 'rename-template-block') {
        return `${block} Use "${usage.to}" instead.`;
    }

    return usage.to ? `${block} Move this customization to "${usage.to}".` : `${block} Remove or move this customization.`;
}

/**
 * This rule reports overrides of deprecated Administration Twig blocks and renames them to their
 * replacement. A step with `component` only applies to templates of that component (or of the
 * components in `extendedBy`), see `fileComponents` for how a template is assigned to a component.
 *
 * A rename is reported for a manual migration instead when the template already overrides the new
 * block, when another override is renamed to the same block, or when the override reads a slot
 * variable the new block does not provide (`removedSlotVariables`). Removals are always manual.
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
        const usagesByName = new Map();

        selectedUsages(loadRegistry().templateBlockMigrations).forEach((match) => {
            usagesByName.set(match.usage.from, [...(usagesByName.get(match.usage.from) ?? []), match]);
        });

        return {
            Program() {
                const sourceCode = context.sourceCode;
                const blockMatches = collectBlockMatches(sourceCode.getText());
                let components = null;
                const getComponents = () => {
                    components ??= fileComponents(context.physicalFilename ?? context.filename);

                    return components;
                };
                const variableOffsets = new Map();
                const getVariableOffsets = (variable) => {
                    if (!variableOffsets.has(variable)) {
                        variableOffsets.set(variable, freeVariableOffsets(sourceCode.ast?.templateBody, variable));
                    }

                    return variableOffsets.get(variable);
                };

                const findings = blockMatches
                    .map((blockMatch) => ({
                        blockMatch,
                        match: (usagesByName.get(blockMatch.name) ?? []).find(({ usage }) =>
                            isInScope(usage, getComponents),
                        ),
                    }))
                    .filter(
                        ({ blockMatch, match }) => match && !isBackwardCompatibilityWrapper(match, blockMatch, blockMatches),
                    );

                const overriddenNames = new Set(blockMatches.map(({ name }) => name));
                const renameTargets = findings
                    .filter(({ match }) => match.usage.kind === 'rename-template-block')
                    .map(({ match }) => match.usage.to);

                /**
                 * Why a rename has to be done by hand, or null when it can be fixed.
                 */
                const renameObstacle = (usage, blockMatch) => {
                    if (overriddenNames.has(usage.to)) {
                        return `The template already overrides "${usage.to}", merge this override into it.`;
                    }

                    if (renameTargets.filter((target) => target === usage.to).length > 1) {
                        return `Another override in the template is renamed to "${usage.to}" as well, merge the overrides.`;
                    }

                    const readVariable = (usage.removedSlotVariables ?? []).find((variable) => {
                        return getVariableOffsets(variable).some((offset) => {
                            return blockMatch.rangeStart <= offset && offset < blockMatch.rangeEnd;
                        });
                    });

                    return readVariable
                        ? `The override reads the slot variable "${readVariable}", which "${usage.to}" does not provide.`
                        : null;
                };

                findings.forEach(({ blockMatch, match: { migration, usage } }) => {
                    const isRename = usage.kind === 'rename-template-block';
                    const obstacle = isRename ? renameObstacle(usage, blockMatch) : null;
                    const lines = [
                        describeUsage(usage),
                        obstacle,
                        usage.message,
                    ].filter(Boolean);
                    const canRename = isRename && !obstacle && canAutoFix(context, usage);

                    context.report({
                        loc: {
                            start: sourceCode.getLocFromIndex(blockMatch.start),
                            end: sourceCode.getLocFromIndex(blockMatch.end),
                        },
                        message: appendRegistryContext(lines, migration),
                        fixLevel: canRename ? usage.fix : 'manual',
                        reviewComment: lines.join(' '),
                        fix: canRename
                            ? (fixer) => blockMatch.nameRanges.map((range) => fixer.replaceTextRange(range, usage.to))
                            : null,
                    });
                });
            },
        };
    },
});
