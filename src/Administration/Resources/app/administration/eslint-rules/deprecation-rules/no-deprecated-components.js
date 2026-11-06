/**
 * @sw-package framework
 */

const { loadRegistry } = require('./registry/load-registry');
const { filterMigrations } = require('./registry/filter-migrations');
const { appendRegistryContext } = require('./registry/format-migration-message');
const { canAutoFix } = require('./registry/fix-policy');
const { commentBefore, convertedFromText, templateCodemodComment } = require('./registry/codemod-comment');
const { defineTemplateBodyVisitor, findObjectVBindAttribute, findObjectVOnAttribute } = require('./registry/template-ast');
const { createComponentUsageRuleApi } = require('./registry/component-usage-api');
const {
    RANGE_FIXER,
    classifyReport,
    collectFixes,
    getRunLevel,
    haveOverlappingRanges,
    withRunLevel,
} = require('./registry/run-level');

/**
 * Plans the follow-up steps of a migration on the element before it is converted: runs every step as
 * if the element was already converted and returns each report with what its fix is. The fixes are
 * built against the current source, so they can go into the conversion's own fix.
 *
 * @example
 * planConversion(context, swAlertNode, migration);
 * // [{ kind: 'manual', headline: '[sw-alert] The "actions" slot is removed.\n…', fixes: [] }]
 */
function planConversion(context, node, migration) {
    const parts = [];
    const planningContext = {
        sourceCode: context.sourceCode,
        options: [],
        report(descriptor) {
            const kind = classifyReport(descriptor);

            parts.push({
                kind,
                headline: descriptor.message.split(`\n${migration.description}`)[0],
                fixes: kind === 'manual' ? [] : collectFixes(descriptor.fix),
                note: descriptor.conversionNote,
            });
        },
    };

    migration.usage
        .filter((usage) => usage.eslint?.report)
        .forEach((usage) => {
            usage.eslint.report(
                createComponentUsageRuleApi(planningContext, node, migration, usage, { assumeConverted: true }),
            );
        });

    return parts;
}

/**
 * Lists why the run level does not let the element be converted: at `safe` every part has to keep the
 * behaviour, at `review` no part may need a manual migration. `partial` and `manual` convert, or
 * comment, every element. A manual rename never converts, so its parts are never the reason.
 */
function findBlockingParts(level, node, renameUsage, parts) {
    if (renameUsage.fix === 'manual') {
        return [];
    }

    if (level === 'review') {
        return parts.filter((part) => part.kind === 'manual').map((part) => part.headline);
    }

    if (level !== 'safe') {
        return [];
    }

    const blockers = parts.filter((part) => part.kind !== 'auto').map((part) => part.headline);

    if (renameUsage.fix !== 'auto') {
        blockers.unshift(renameUsage.message ?? 'The conversion itself needs a review.');
    }

    if (findObjectVBindAttribute(node) || findObjectVOnAttribute(node)) {
        blockers.unshift('An object v-bind or v-on can pass deprecated props or listeners.');
    }

    return blockers;
}

/**
 * Builds the comment above a converted element. Object `v-bind` / `v-on` can still pass deprecated
 * props or listeners, so they get an extra review sentence. `notes` are reviews of the conversion
 * itself, which steps can only judge on the old element.
 *
 * @example
 * getConversionComment('sw-icon', node, []); // the review comment carrying convertedFromText('sw-icon')
 */
function getConversionComment(componentName, node, notes) {
    const warnings = [
        findObjectVBindAttribute(node) &&
            'Dynamic v-bind props may still contain deprecated API usage and need manual review',
        findObjectVOnAttribute(node) &&
            'Dynamic v-on listeners may still contain deprecated event usage and need manual review',
    ].filter(Boolean);
    const sentences = [
        ...warnings,
        ...notes.map((note) => note.replace(/\.$/, '')),
    ];
    const closingPeriod = notes.length === 0 && warnings.length > 0 ? '.' : '';

    return templateCodemodComment(
        `${convertedFromText(componentName)}${sentences.map((sentence) => `. ${sentence}`).join('')}${closingPeriod}`,
    );
}

/**
 * Finds broken markup where the parser gave the element no end tag, but an end tag with its name
 * follows, for example after a start tag that misses its `>`. The Vue compiler pairs that end tag
 * with the element, so renaming only the start tag would break the template.
 *
 * @example
 * // <sw-text-field :label="x"
 * // </sw-text-field>
 * hasUnmatchedEndTag(sourceCode, swTextFieldNode, 'sw-text-field'); // true
 */
function hasUnmatchedEndTag(sourceCode, node, name) {
    if (node.startTag.selfClosing || node.endTag) {
        return false;
    }

    return sourceCode.text.slice(node.startTag.range[0]).includes(`</${name}`);
}

/**
 * This rule reports deprecated components and renames them to their replacement. The run level decides
 * which elements it converts. At `safe` an element is converted together with all of its follow-up
 * fixes. At the other levels it gets a review comment, which `no-deprecated-component-usage` uses to find
 * the conversions its follow-up steps apply to.
 *
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = withRunLevel({
    meta: {
        type: 'problem',
        fixable: 'code',
        docs: {
            description: 'No usage of deprecated components',
            recommended: true,
        },
        schema: [
            {
                type: 'object',
                properties: {
                    fix: {
                        type: 'boolean',
                    },
                    activatedComponents: {
                        type: 'array',
                        items: {
                            type: 'string',
                        },
                    },
                },
            },
        ],
    },

    create(context) {
        const migrations = filterMigrations(loadRegistry().componentApiMigrations);
        const activatedComponents =
            context.options[0]?.activatedComponents ?? migrations.map((migration) => migration.component);
        const level = getRunLevel(context);
        const renameMigrations = migrations
            .filter((migration) => activatedComponents.includes(migration.component))
            .map((migration) => ({
                migration,
                renameUsage: migration.usage.find((usage) => usage.kind === 'rename-component'),
            }))
            .filter(({ renameUsage }) => renameUsage);

        return defineTemplateBodyVisitor(context, {
            VElement(node) {
                const match = renameMigrations.find(({ migration }) => migration.component === node.name);

                if (!match) {
                    return;
                }

                const { migration, renameUsage } = match;
                const oldName = migration.component;
                const newName = migration.replacement;
                const headline = `"${oldName}" is deprecated. Please use "${newName}" instead.`;

                if (hasUnmatchedEndTag(context.sourceCode, node, oldName)) {
                    context.report({
                        node,
                        message: appendRegistryContext(
                            [headline, 'It is not converted, because its start and end tags do not pair up.'],
                            migration,
                        ),
                        fixLevel: 'manual',
                    });

                    return;
                }

                const parts = renameUsage.fix === 'manual' ? [] : planConversion(context, node, migration);
                const blockingParts = findBlockingParts(level, node, renameUsage, parts);
                const nameRange = [
                    node.startTag.range[0],
                    node.startTag.range[0] + 1 + oldName.length,
                ];
                const renameFixes = (fixer) => [
                    fixer.replaceTextRange(nameRange, `<${newName}`),
                    ...(node.endTag ? [fixer.replaceTextRange(node.endTag.range, `</${newName}>`)] : []),
                ];

                if (blockingParts.length > 0) {
                    context.report({
                        node,
                        message: appendRegistryContext(
                            [
                                headline,
                                level === 'safe'
                                    ? 'It is not converted, because these parts need a review or a manual migration:'
                                    : 'It is not converted, because these parts need a manual migration first:',
                                ...blockingParts.map((part) => `- ${part}`),
                            ],
                            migration,
                        ),
                        fixLevel: 'manual',
                    });

                    return;
                }

                if (level === 'safe') {
                    const partFixes = parts.flatMap((part) => part.fixes);
                    // A part that turns the element self-closing replaces the end tag itself
                    const replacesEndTag =
                        node.endTag &&
                        partFixes.some((fix) => {
                            return fix.range[0] <= node.endTag.range[0] && node.endTag.range[1] <= fix.range[1];
                        });
                    const fixes = [
                        ...renameFixes(RANGE_FIXER).slice(0, replacesEndTag ? 1 : undefined),
                        ...partFixes,
                    ];
                    const overlapping = haveOverlappingRanges(fixes);

                    context.report({
                        node,
                        message: appendRegistryContext(
                            [
                                headline,
                                overlapping ? 'It is not converted, because two of its fixes change the same code.' : '',
                            ],
                            migration,
                        ),
                        fix: overlapping || !canAutoFix(context, renameUsage) ? null : () => fixes,
                        fixLevel: overlapping ? 'manual' : 'auto',
                    });

                    return;
                }

                context.report({
                    node,
                    message: appendRegistryContext([headline, renameUsage.message ?? ''], migration),
                    fixLevel: renameUsage.fix,
                    *fix(fixer) {
                        if (!canAutoFix(context, renameUsage)) {
                            return;
                        }

                        yield fixer.insertTextBeforeRange(
                            nameRange,
                            commentBefore(
                                context.sourceCode,
                                node,
                                getConversionComment(oldName, node, parts.map((part) => part.note).filter(Boolean)),
                            ),
                        );
                        yield* renameFixes(fixer);
                    },
                });
            },
        });
    },
});
