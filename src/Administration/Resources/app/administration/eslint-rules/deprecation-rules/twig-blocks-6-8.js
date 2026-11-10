/**
 * @sw-package framework
 *
 * Codemods for the Administration Twig blocks that Shopware 6.8 renames or removes, listed in
 * `data/v6.8/template-blocks.json`. They look at block overrides in templates: `{% block name %}` in
 * `.html.twig` files and `<sw-block name|extends="name">` in `.html.twig` and `.vue` files.
 *
 * - `renamed`: renames the overridden block (`rename-template-block`). It reports the override for a
 *   manual migration instead when the template already overrides the new block, when another override
 *   is renamed to the same block, or when the override reads a slot variable the new block does not
 *   provide (`removedSlotVariables`).
 * - `removed`: reports an override of a removed block (`remove-template-block`), with the block to move
 *   it to when there is one.
 *
 * A usage with `component` only applies to templates of that component or of the components in
 * `extendedBy`, see `fileComponents()` in `registry/component-scope.js` for how a template is assigned
 * to a component.
 */

const entries = require('./data/v6.8/template-blocks.json');
const { FIX_OPTION_SCHEMA, canAutoFix } = require('./registry/fix-policy');
const { withRunLevel } = require('./registry/run-level');
const { fileComponents } = require('./registry/component-scope');
const { entryMessage } = require('./utils/entry-message');

const RENAME = 'rename-template-block';
const REMOVE = 'remove-template-block';

// The fix level of a usage that names none: renames keep the behaviour, removals need a person.
const DEFAULT_FIX_LEVELS = {
    [RENAME]: 'auto',
    [REMOVE]: 'manual',
};

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
 * Finds every block override in a template, Twig `{% block %}` and `<sw-block name|extends>`, with
 * the ranges of its name (also in a named `{% endblock name %}`) and the range the block spans.
 *
 * @example
 * collectBlocks('{% block a %}{% endblock a %}');
 * // [{ name: 'a', start: 9, end: 10, nameRanges: [[9, 10], [26, 27]], rangeStart: 0, rangeEnd: 29 }]
 */
function collectBlocks(sourceText) {
    const blocks = [];
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

        blocks.push({
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
                const name = attributeMatch[2];
                const start = pair.open.index + attributeMatch.index + attributeMatch[0].lastIndexOf(name);

                blocks.push({
                    name,
                    start,
                    end: start + name.length,
                    nameRanges: [[start, start + name.length]],
                    rangeStart: pair.rangeStart,
                    rangeEnd: pair.rangeEnd,
                });
            });
        },
    );

    return blocks;
}

/**
 * A renamed block nested inside its replacement block is the BC wrapper that keeps old extensions
 * working, not an extension usage. A replacement block elsewhere in the file (a sibling override) does
 * not count.
 *
 * @example
 * // {% block new_block %}{% block old_block %}{% endblock %}{% endblock %}
 * isBackwardCompatibilityWrapper({ type: 'rename-template-block', from: 'old_block', to: 'new_block' }, oldBlock, blocks); // true
 */
function isBackwardCompatibilityWrapper(usage, block, blocks) {
    if (usage.type !== RENAME) {
        return false;
    }

    return blocks.some((candidate) => {
        return (
            candidate.name === usage.to &&
            candidate.rangeStart <= block.rangeStart &&
            candidate.rangeEnd >= block.rangeEnd &&
            candidate !== block
        );
    });
}

/**
 * Whether a usage applies to the template: usages without `component` apply everywhere, the others
 * only to templates of the component or of one of the components that extend it.
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
 * The source offsets of every template expression that reads the variable without defining it, e.g.
 * `active` in `v-if="active === 'x'"`, but not in `'is--active'`, `tab.active` or inside a
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
 * describeUsage({ type: 'remove-template-block', from: 'sw_a', to: 'sw_b', component: 'sw-x' });
 * // 'The Administration Twig block "sw_a" of "sw-x" is deprecated. Move this customization to "sw_b".'
 */
function describeUsage(usage) {
    const block = `The Administration Twig block "${usage.from}"${usage.component ? ` of "${usage.component}"` : ''} is deprecated.`;

    if (usage.type === RENAME) {
        return `${block} Use "${usage.to}" instead.`;
    }

    return usage.to ? `${block} Move this customization to "${usage.to}".` : `${block} Remove or move this customization.`;
}

/**
 * The usages of one type in `template-blocks.json`, keyed by the block name they match, each with its
 * entry for the report message.
 */
function indexUsages(type) {
    const usagesByName = new Map();

    entries.forEach((entry) => {
        entry.usage
            .filter((usage) => usage.type === type)
            .forEach((usage) => {
                const match = { entry, usage: { ...usage, fix: usage.fix ?? DEFAULT_FIX_LEVELS[type] } };

                usagesByName.set(usage.from, [...(usagesByName.get(usage.from) ?? []), match]);
            });
    });

    return usagesByName;
}

/**
 * Creates the rule of one usage type of `template-blocks.json`.
 *
 * @type {(type: string, description: string) => import('eslint').Rule.RuleModule}
 */
function createTwigBlockRule(type, description) {
    const usagesByName = indexUsages(type);

    return withRunLevel({
        meta: {
            type: 'problem',
            docs: {
                description,
                recommended: false,
            },
            fixable: 'code',
            schema: FIX_OPTION_SCHEMA,
        },

        create(context) {
            const sourceCode = context.sourceCode;

            // Block overrides only exist in templates, which vue-eslint-parser parses.
            if (!sourceCode.parserServices?.defineTemplateBodyVisitor) {
                return {};
            }

            return {
                Program() {
                    const blocks = collectBlocks(sourceCode.getText());
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

                    const findings = blocks
                        .map((block) => ({
                            block,
                            match: (usagesByName.get(block.name) ?? []).find(({ usage }) => isInScope(usage, getComponents)),
                        }))
                        .filter(({ block, match }) => match && !isBackwardCompatibilityWrapper(match.usage, block, blocks));

                    const overriddenNames = new Set(blocks.map(({ name }) => name));
                    const renameTargets = findings.filter(({ match }) => match.usage.type === RENAME).map(({ match }) => match.usage.to);

                    /**
                     * Why a rename has to be done by hand, or null when it can be fixed.
                     */
                    const renameObstacle = (usage, block) => {
                        if (overriddenNames.has(usage.to)) {
                            return `The template already overrides "${usage.to}", merge this override into it.`;
                        }

                        if (renameTargets.filter((target) => target === usage.to).length > 1) {
                            return `Another override in the template is renamed to "${usage.to}" as well, merge the overrides.`;
                        }

                        const readVariable = (usage.removedSlotVariables ?? []).find((variable) => {
                            return getVariableOffsets(variable).some((offset) => {
                                return block.rangeStart <= offset && offset < block.rangeEnd;
                            });
                        });

                        return readVariable
                            ? `The override reads the slot variable "${readVariable}", which "${usage.to}" does not provide.`
                            : null;
                    };

                    findings.forEach(({ block, match: { entry, usage } }) => {
                        const isRename = usage.type === RENAME;
                        const obstacle = isRename ? renameObstacle(usage, block) : null;
                        const lines = [
                            describeUsage(usage),
                            obstacle,
                            usage.message,
                        ].filter(Boolean);
                        const canRename = isRename && !obstacle && canAutoFix(context, usage);

                        context.report({
                            loc: {
                                start: sourceCode.getLocFromIndex(block.start),
                                end: sourceCode.getLocFromIndex(block.end),
                            },
                            message: entryMessage(lines, entry),
                            fixLevel: canRename ? usage.fix : 'manual',
                            reviewComment: lines.join(' '),
                            fix: canRename ? (fixer) => block.nameRanges.map((range) => fixer.replaceTextRange(range, usage.to)) : null,
                        });
                    });
                },
            };
        },
    });
}

module.exports = {
    renamed: createTwigBlockRule(RENAME, 'Rename overrides of Administration Twig blocks that Shopware 6.8 renames'),
    removed: createTwigBlockRule(REMOVE, 'Report overrides of Administration Twig blocks that Shopware 6.8 removes'),
};
