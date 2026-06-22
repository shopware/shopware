const { loadRegistry } = require('./registry/load-registry');
const { filterMigrations } = require('./registry/filter-migrations');

function formatReferences(migration) {
    if (!migration.references?.length) {
        return '';
    }

    return migration.references.map((reference) => `${reference.type}: ${reference.target}`).join('\n');
}

function appendRegistryContext(message, migration) {
    const references = formatReferences(migration);

    return [
        message,
        '',
        migration.description,
        `Removed in Shopware ${migration.removedIn}.`,
        references ? `References:\n${references}` : '',
    ]
        .filter(Boolean)
        .join('\n');
}

function collectFixOperations(fix) {
    const recorder = new Proxy(
        {},
        {
            get(_target, method) {
                return (...args) => ({ method, args });
            },
        },
    );

    const result = fix(recorder);

    if (!result) {
        return [];
    }

    if (typeof result[Symbol.iterator] === 'function') {
        return Array.from(result);
    }

    return [result];
}

function getStartTagFromReportNode(node) {
    if (node?.type === 'VElement') {
        return node.startTag;
    }

    if (node?.type === 'VAttribute') {
        return node.parent;
    }

    return null;
}

function normalizeAttributeName(name) {
    return name.toLowerCase();
}

function normalizeDescriptorName(name) {
    const normalizedName = normalizeTemplateName(name);

    if (normalizedName === 'vmodel:modelvalue') {
        return 'vmodel';
    }

    return normalizedName;
}

function getAttributeDescriptor(attribute) {
    const keyName = attribute?.key?.name;

    if (typeof keyName === 'string') {
        return {
            kind: 'prop',
            name: normalizeAttributeName(keyName),
        };
    }

    const directive = keyName?.name;
    const argumentName = attribute?.key?.argument?.name;

    if (directive === 'bind' && argumentName) {
        return {
            kind: 'prop',
            name: normalizeAttributeName(argumentName),
        };
    }

    if (directive === 'on' && argumentName) {
        return {
            kind: 'event',
            name: normalizeAttributeName(argumentName),
        };
    }

    if (directive === 'model') {
        return {
            kind: 'model',
            name: argumentName ? normalizeAttributeName(`v-model:${argumentName}`) : 'v-model',
        };
    }

    return null;
}

function getReplacementDescriptor(attribute, target, replacement) {
    if (attribute.key === target) {
        if (replacement === 'v-model' || replacement.startsWith('v-model:')) {
            return {
                kind: 'model',
                name: normalizeAttributeName(replacement),
            };
        }

        if (replacement.startsWith('@')) {
            return {
                kind: 'event',
                name: normalizeAttributeName(replacement.slice(1)),
            };
        }

        if (replacement.startsWith('v-on:')) {
            return {
                kind: 'event',
                name: normalizeAttributeName(replacement.slice('v-on:'.length)),
            };
        }

        return {
            kind: 'prop',
            name: normalizeAttributeName(replacement),
        };
    }

    if (attribute.key?.argument === target) {
        const directive = attribute.key?.name?.name;

        if (directive === 'bind') {
            return {
                kind: 'prop',
                name: normalizeAttributeName(replacement),
            };
        }

        if (directive === 'on') {
            return {
                kind: 'event',
                name: normalizeAttributeName(replacement),
            };
        }

        if (directive === 'model') {
            return {
                kind: 'model',
                name: normalizeAttributeName(`v-model:${replacement}`),
            };
        }
    }

    return null;
}

function hasDuplicateReplacementAttribute(descriptor) {
    if (!descriptor.fix) {
        return false;
    }

    const startTag = getStartTagFromReportNode(descriptor.node);
    const attributes = startTag?.attributes ?? [];

    if (!attributes.length) {
        return false;
    }

    return collectFixOperations(descriptor.fix).some((operation) => {
        if (operation.method !== 'replaceText') {
            return false;
        }

        const [
            target,
            replacement,
        ] = operation.args;

        if (typeof replacement !== 'string') {
            return false;
        }

        const replacedAttribute = attributes.find((attribute) => {
            return attribute.key === target || attribute.key?.argument === target;
        });

        if (!replacedAttribute) {
            return false;
        }

        const replacementDescriptor = getReplacementDescriptor(replacedAttribute, target, replacement);

        if (!replacementDescriptor) {
            return false;
        }

        return attributes.some((attribute) => {
            if (attribute === replacedAttribute) {
                return false;
            }

            const attributeDescriptor = getAttributeDescriptor(attribute);

            return (
                attributeDescriptor?.kind === replacementDescriptor.kind &&
                normalizeDescriptorName(attributeDescriptor.name) === normalizeDescriptorName(replacementDescriptor.name)
            );
        });
    });
}

function reportWithDuplicateReplacementGuard(context, descriptor) {
    context.report({
        ...descriptor,
        fix: descriptor.fix
            ? (fixer) => {
                  if (hasDuplicateReplacementAttribute(descriptor)) {
                      return null;
                  }

                  return descriptor.fix(fixer);
              }
            : undefined,
    });
}

function normalizeTemplateName(name) {
    return name.replace(/-/g, '').toLowerCase();
}

function matchesTemplateName(actual, expected) {
    return actual === expected || normalizeTemplateName(actual) === normalizeTemplateName(expected);
}

function getStaticAttributeName(attribute) {
    return typeof attribute?.key?.name === 'string' ? attribute.key.name : null;
}

function getDirectiveName(attribute) {
    return attribute?.key?.name?.name ?? null;
}

function getDirectiveArgumentName(attribute) {
    return attribute?.key?.argument?.name ?? null;
}

function findMatchingPropAttribute(node, propName) {
    return node.startTag.attributes.find((attribute) => {
        const staticName = getStaticAttributeName(attribute);

        if (staticName && matchesTemplateName(staticName, propName)) {
            return true;
        }

        const argumentName = getDirectiveArgumentName(attribute);

        return getDirectiveName(attribute) === 'bind' && argumentName && matchesTemplateName(argumentName, propName);
    });
}

function hasMatchingPropAttribute(node, propName) {
    return Boolean(findMatchingPropAttribute(node, propName));
}

function getPropAttributeValueKind(attribute) {
    if (!attribute?.value) {
        return 'static';
    }

    if (getDirectiveName(attribute) === 'bind') {
        return 'expression';
    }

    return 'static';
}

function getAttributeValueSource(context, attribute) {
    if (!attribute?.value) {
        return null;
    }

    if (getDirectiveName(attribute) === 'bind') {
        return context.sourceCode.getText(attribute.value.expression ?? attribute.value);
    }

    return attribute.value.value;
}

function getTransformResult(usage, node, attribute) {
    if (typeof usage.transform !== 'function') {
        return null;
    }

    return usage.transform({
        phase: 'fix',
        valueKind: attribute ? getPropAttributeValueKind(attribute) : 'unknown',
        hasObjectVBind: nodeHasObjectVBind(node),
    });
}

function nodeHasObjectVBind(node) {
    return Boolean(
        node?.startTag?.attributes?.some((attribute) => {
            return getDirectiveName(attribute) === 'bind' && !attribute.key.argument;
        }),
    );
}

function findMatchingEventAttribute(node, eventName) {
    return node.startTag.attributes.find((attribute) => {
        return getDirectiveName(attribute) === 'on' && getDirectiveArgumentName(attribute) === eventName;
    });
}

function findMatchingVModelAttribute(node, argumentName) {
    return node.startTag.attributes.find((attribute) => {
        if (getDirectiveName(attribute) !== 'model') {
            return false;
        }

        const currentArgumentName = getDirectiveArgumentName(attribute) ?? null;

        return currentArgumentName === argumentName;
    });
}

function findSlot(node, slotName) {
    return node.children.find((child) => {
        return (
            child.type === 'VElement' &&
            child.name === 'template' &&
            child.startTag?.attributes?.some((attribute) => {
                return getDirectiveName(attribute) === 'slot' && getDirectiveArgumentName(attribute) === slotName;
            })
        );
    });
}

function hasCodemodComment(context, node, text) {
    return context.sourceCode.ast?.templateBody?.comments?.some((comment) => {
        return (
            comment.value.includes(text) &&
            comment.loc.start.line >= node.loc.start.line &&
            comment.loc.end.line <= node.loc.end.line
        );
    });
}

function getCondensedTextContent(node) {
    return (node.children?.[0]?.value ?? '').replace(/\n/g, '').replace(/\s+/g, ' ');
}

function getFirstElementChildWithoutSlot(node) {
    return node.children.find((child) => {
        return child.type === 'VElement' && child.name !== 'template';
    });
}

function usageFixesAutomatically(usage) {
    return usage.fix !== 'manual';
}

function buildUsageMessage(componentName, migration, usage) {
    const apiName = usage.prop ?? usage.from ?? usage.event ?? usage.slot ?? usage.name;
    const replacement = usage.to ? ` Use "${usage.to}" instead.` : '';

    return appendRegistryContext(`[${componentName}] The "${apiName}" API is deprecated.${replacement}`, migration);
}

function reportRenameProp(context, node, migration, usage) {
    const attribute = findMatchingPropAttribute(node, usage.from);

    if (!attribute) {
        return;
    }

    reportWithDuplicateReplacementGuard(context, {
        node: attribute,
        message: buildUsageMessage(node.name, migration, usage),
        fix(fixer) {
            if (context.options.includes('disableFix') || !usageFixesAutomatically(usage)) {
                return null;
            }

            const transform = getTransformResult(usage, node, attribute);

            if (transform?.fix === 'manual') {
                return null;
            }

            if (transform?.kind === 'replace-with-static-value') {
                if (hasMatchingPropAttribute(node, usage.to)) {
                    return fixer.remove(attribute);
                }

                const conflictingDeprecatedProp = migration.usage.some((candidate) => {
                    return (
                        candidate !== usage &&
                        candidate.kind === 'rename-prop' &&
                        candidate.to === usage.to &&
                        findMatchingPropAttribute(node, candidate.from)
                    );
                });

                if (conflictingDeprecatedProp) {
                    return null;
                }

                return fixer.replaceText(attribute, `${usage.to}="${transform.value}"`);
            }

            if (transform?.kind === 'invert-boolean') {
                if (!attribute.value) {
                    return fixer.remove(attribute);
                }

                const value = getAttributeValueSource(context, attribute);
                const replacementName = usage.to;

                if (getDirectiveName(attribute) === 'bind') {
                    return fixer.replaceText(attribute, `:${replacementName}="!(${value})"`);
                }

                return fixer.replaceText(attribute, `${replacementName}="!(${value})"`);
            }

            if (getDirectiveName(attribute) === 'bind') {
                return fixer.replaceText(attribute.key.argument, usage.to);
            }

            return fixer.replaceText(attribute.key, usage.to);
        },
    });
}

function reportRemoveProp(context, node, migration, usage) {
    const attribute = findMatchingPropAttribute(node, usage.prop);

    if (!attribute) {
        return;
    }

    reportWithDuplicateReplacementGuard(context, {
        node: attribute,
        message: buildUsageMessage(node.name, migration, usage),
        fix(fixer) {
            if (context.options.includes('disableFix') || !usageFixesAutomatically(usage)) {
                return null;
            }

            const transform = getTransformResult(usage, node, attribute);

            if (transform?.fix === 'manual') {
                return null;
            }

            if (transform?.kind === 'router-link-to-click') {
                const routerLinkValue = getDirectiveName(attribute) === 'bind'
                    ? getAttributeValueSource(context, attribute)
                    : `'${getAttributeValueSource(context, attribute)}'`;

                return fixer.replaceText(attribute, `@click="$router.push(${routerLinkValue})"`);
            }

            if (transform?.kind === 'ai-badge-to-title-slot') {
                const indent = node.startTag.loc.start.column + 4;
                const aiBadgeCondition = getDirectiveName(attribute) === 'bind'
                    ? ` v-if="${getAttributeValueSource(context, attribute)}"`
                    : '';

                return [
                    fixer.remove(attribute),
                    fixer.insertTextAfter(
                        node.startTag,
                        `\n${' '.repeat(indent)}<slot name="title"><sw-ai-copilot-badge${aiBadgeCondition} /></slot>\n${' '.repeat(indent)}`,
                    ),
                ];
            }

            return fixer.remove(attribute);
        },
    });
}

function reportMapPropValue(context, node, migration, usage) {
    const attribute = findMatchingPropAttribute(node, usage.prop);

    if (!attribute || getDirectiveName(attribute) === 'bind' || attribute.value?.value !== usage.from) {
        return;
    }

    reportWithDuplicateReplacementGuard(context, {
        node: attribute,
        message: buildUsageMessage(node.name, migration, usage),
        fix(fixer) {
            if (context.options.includes('disableFix') || !usageFixesAutomatically(usage)) {
                return null;
            }

            const transform = getTransformResult(usage, node, attribute);
            const fixes = [
                fixer.replaceText(attribute.value, `"${usage.to}"`),
            ];

            if (transform?.fix === 'manual') {
                return null;
            }

            if (transform?.kind === 'add-boolean-prop' && !hasMatchingPropAttribute(node, transform.prop)) {
                fixes.push(fixer.insertTextAfterRange(attribute.range, ` ${transform.prop}`));
            }

            return fixes;
        },
    });
}

function reportRenameEvent(context, node, migration, usage) {
    const attribute = findMatchingEventAttribute(node, usage.from);

    if (!attribute) {
        return;
    }

    reportWithDuplicateReplacementGuard(context, {
        node: attribute,
        message: buildUsageMessage(node.name, migration, usage),
        fix(fixer) {
            if (context.options.includes('disableFix') || !usageFixesAutomatically(usage)) {
                return null;
            }

            return fixer.replaceText(attribute.key.argument, usage.to);
        },
    });
}

function reportRemoveEvent(context, node, migration, usage) {
    const attribute = findMatchingEventAttribute(node, usage.event);

    if (!attribute) {
        return;
    }

    reportWithDuplicateReplacementGuard(context, {
        node: attribute,
        message: buildUsageMessage(node.name, migration, usage),
        fix(fixer) {
            if (context.options.includes('disableFix') || !usageFixesAutomatically(usage)) {
                return null;
            }

            return fixer.remove(attribute);
        },
    });
}

function reportRenameVModelArgument(context, node, migration, usage) {
    const attribute = findMatchingVModelAttribute(node, usage.from ?? null);

    if (!attribute) {
        return;
    }

    reportWithDuplicateReplacementGuard(context, {
        node: attribute,
        message: buildUsageMessage(node.name, migration, usage),
        fix(fixer) {
            if (context.options.includes('disableFix') || !usageFixesAutomatically(usage)) {
                return null;
            }

            if (usage.to === null) {
                return fixer.replaceText(attribute.key, 'v-model');
            }

            if (!attribute.key.argument) {
                return fixer.replaceText(attribute.key, `v-model:${usage.to}`);
            }

            return fixer.replaceText(attribute.key.argument, usage.to);
        },
    });
}

function reportSlotToProp(context, node, migration, usage) {
    const slot = findSlot(node, usage.slot);

    if (!slot) {
        return;
    }

    const mtSelectComment = `Remove the "${usage.slot}" slot and use the "${usage.prop}" prop instead`;

    if (node.name === 'mt-select' && hasCodemodComment(context, node, mtSelectComment)) {
        return;
    }

    reportWithDuplicateReplacementGuard(context, {
        node: slot,
        message: buildUsageMessage(node.name, migration, usage),
        fix(fixer) {
            if (context.options.includes('disableFix') || !usageFixesAutomatically(usage)) {
                return null;
            }

            const slotValue = getCondensedTextContent(slot);

            if (node.name === 'mt-select') {
                return fixer.insertTextBefore(slot.startTag, `<!-- TODO Codemod: ${mtSelectComment} -->\n`);
            }

            return fixer.replaceText(
                slot,
                `<!-- Slot "${usage.slot}" was removed and should be replaced with "${usage.prop}" prop. Previous value was: ${slotValue} -->`,
            );
        },
    });
}

function reportRemoveSlot(context, node, migration, usage) {
    const slot = findSlot(node, usage.slot);

    if (!slot) {
        return;
    }

    if (
        node.name === 'mt-tabs' &&
        usage.slot === 'content' &&
        hasCodemodComment(context, node, 'The "content" slot is not used anymore')
    ) {
        return;
    }

    reportWithDuplicateReplacementGuard(context, {
        node: slot,
        message: buildUsageMessage(node.name, migration, usage),
        fix(fixer) {
            if (context.options.includes('disableFix') || !usageFixesAutomatically(usage)) {
                return null;
            }

            if (node.name === 'mt-tabs' && usage.slot === 'content') {
                const indentation = ' '.repeat(slot.startTag?.loc?.start?.column ?? 0);
                return fixer.insertTextBeforeRange(
                    slot.startTag.range,
                    `<!-- TODO Codemod: The "content" slot is not used anymore. Please set the content manually outside the component. -->\n${indentation}`,
                );
            }

            if (usage.slot === 'actions') {
                return fixer.replaceText(slot, `<!-- Slot "actions" was removed and has no replacement. -->`);
            }

            if (node.name === 'mt-switch' && usage.slot === 'hint') {
                return fixer.replaceText(slot, `<!-- Slot "hint" was removed with no replacement. -->`);
            }

            const slotValue = getCondensedTextContent(slot);

            return fixer.replaceText(
                slot,
                `<!-- Slot "${usage.slot}" was removed without replacement. Previous value was: ${slotValue} -->`,
            );
        },
    });
}

function buildTabsItems(context, slotChildren) {
    const items = slotChildren.map((child) => {
        const attributes = child.startTag.attributes;
        const nameAttribute = attributes.find((attribute) => getStaticAttributeName(attribute) === 'name');
        const routeAttribute = attributes.find((attribute) => getStaticAttributeName(attribute) === 'route');
        const routeAttributeExpression = attributes.find((attribute) => {
            return getDirectiveName(attribute) === 'bind' && getDirectiveArgumentName(attribute) === 'route';
        });
        const rawTextContent = child.children.find((itemChild) => itemChild.type === 'VText')?.value;
        const textContent = rawTextContent?.replace(/\n/g, '').trim();
        const rawLabel = textContent?.match(/\$tc\((.*)\)/)?.[1] ?? textContent?.match(/\$t\((.*)\)/)?.[1] ?? textContent;
        const label = rawLabel?.replace(/['"]+/g, '').trim();
        let name = nameAttribute?.value?.value ?? 'TODO: change this property';

        if (!nameAttribute && routeAttributeExpression) {
            name = context.sourceCode.text.slice(
                routeAttributeExpression.value.expression.range[0],
                routeAttributeExpression.value.expression.range[1],
            );
        } else if (!nameAttribute && routeAttribute) {
            name = routeAttribute.value.value;
        }

        return {
            label,
            name,
        };
    });

    return JSON.stringify(items, null, 4).replace(/[\/()']/g, "\\'").replace(/"/g, "'");
}

function reportTabsDefaultSlotToItems(context, node, migration, usage) {
    const defaultSlot = findSlot(node, 'default');
    const childWithoutSlot = getFirstElementChildWithoutSlot(node);
    const target = defaultSlot ?? childWithoutSlot;

    if (!target || hasMatchingPropAttribute(node, 'items')) {
        return;
    }

    reportWithDuplicateReplacementGuard(context, {
        node: target,
        message: buildUsageMessage(node.name, migration, usage),
        fix(fixer) {
            if (context.options.includes('disableFix') || !usageFixesAutomatically(usage)) {
                return null;
            }

            const slotChildren = (defaultSlot?.children ?? node.children).filter((child) => {
                return child.type === 'VElement' && child.name === 'sw-tabs-item';
            });
            const rangeAfterStartTag = node.startTag?.range[0] + '<mt-tabs'.length;
            const fixes = [
                fixer.insertTextAfterRange([rangeAfterStartTag, rangeAfterStartTag], ` :items="${buildTabsItems(context, slotChildren)}"`),
            ];

            if (defaultSlot) {
                fixes.push(
                    fixer.insertTextBeforeRange(
                        defaultSlot.startTag.range,
                        `<!-- TODO Codemod: This slot is not used anymore. Please use the "items" property instead. -->\n`,
                    ),
                );
            } else {
                fixes.push(
                    fixer.insertTextBeforeRange(
                        node.children[0].range,
                        `<!-- TODO Codemod: This slot is not used anymore. Please use the "items" property instead. -->`,
                    ),
                );
            }

            return fixes;
        },
    });
}

function reportSelectSlotComment(context, node, migration, usage) {
    const defaultSlot = findSlot(node, 'default');
    const childWithoutSlot = getFirstElementChildWithoutSlot(node);
    const target = defaultSlot ?? childWithoutSlot;
    const comment = 'Remove the "default" slot and use the "options" prop instead';

    if (!target || hasCodemodComment(context, node, comment)) {
        return;
    }

    reportWithDuplicateReplacementGuard(context, {
        node: target,
        message: buildUsageMessage(node.name, migration, usage),
        fix(fixer) {
            if (context.options.includes('disableFix') || !usageFixesAutomatically(usage)) {
                return null;
            }

            return fixer.insertTextBefore(target.startTag, `<!-- TODO Codemod: ${comment} -->\n`);
        },
    });
}

function reportButtonDefaultVariant(context, node, migration, usage) {
    if (hasMatchingPropAttribute(node, 'variant')) {
        return;
    }

    reportWithDuplicateReplacementGuard(context, {
        node,
        message: buildUsageMessage(node.name, migration, usage),
        fix(fixer) {
            if (context.options.includes('disableFix') || !usageFixesAutomatically(usage)) {
                return null;
            }

            return fixer.insertTextAfterRange([node.startTag.range[0], node.startTag.range[1] - 1], ' variant="secondary"');
        },
    });
}

function reportFloatingUiDefaultOpened(context, node, migration, usage) {
    if (hasMatchingPropAttribute(node, 'is-opened') || hasMatchingPropAttribute(node, 'open')) {
        return;
    }

    reportWithDuplicateReplacementGuard(context, {
        node,
        message: buildUsageMessage(node.name, migration, usage),
        fix(fixer) {
            if (context.options.includes('disableFix') || !usageFixesAutomatically(usage)) {
                return null;
            }

            const rangeAfterStartTag = node.startTag?.range[0] + '<mt-floating-ui'.length;

            return fixer.insertTextAfterRange([rangeAfterStartTag, rangeAfterStartTag], ` :is-opened="true"`);
        },
    });
}

function reportCustomUsage(context, node, migration, usage) {
    if (usage.name === 'tabs-default-slot-to-items') {
        reportTabsDefaultSlotToItems(context, node, migration, usage);
        return;
    }

    if (usage.name === 'select-options-name-id-to-label-value') {
        return;
    }

    if (usage.name === 'select-default-option-slot-to-options') {
        reportSelectSlotComment(context, node, migration, usage);
        return;
    }

    if (usage.name === 'button-default-variant-secondary') {
        reportButtonDefaultVariant(context, node, migration, usage);
        return;
    }

    if (usage.name === 'floating-ui-default-opened') {
        reportFloatingUiDefaultOpened(context, node, migration, usage);
    }
}

function runRegistryUsage(context, node, migration, usage) {
    if (usage.kind === 'rename-prop') {
        reportRenameProp(context, node, migration, usage);
        return;
    }

    if (usage.kind === 'remove-prop') {
        reportRemoveProp(context, node, migration, usage);
        return;
    }

    if (usage.kind === 'rename-event') {
        reportRenameEvent(context, node, migration, usage);
        return;
    }

    if (usage.kind === 'remove-event') {
        reportRemoveEvent(context, node, migration, usage);
        return;
    }

    if (usage.kind === 'map-prop-value') {
        reportMapPropValue(context, node, migration, usage);
        return;
    }

    if (usage.kind === 'slot-to-prop') {
        reportSlotToProp(context, node, migration, usage);
        return;
    }

    if (usage.kind === 'remove-slot') {
        reportRemoveSlot(context, node, migration, usage);
        return;
    }

    if (usage.kind === 'custom') {
        reportCustomUsage(context, node, migration, usage);
        return;
    }

    if (usage.kind === 'rename-v-model-argument') {
        reportRenameVModelArgument(context, node, migration, usage);
    }
}

/**
 * @sw-package framework
 *
 * This rule checks if converted components still use the old logic, props, etc.
 *
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = {
    meta: {
        type: 'problem',
        fixable: 'code',
        docs: {
            description: 'No usage of deprecated components',
            recommended: true,
        },
        schema: [
            {
                enum: [
                    'disableFix',
                    'enableFix',
                ],
            },
        ],
    },
    /** @param {RuleContext} context */
    create(context) {
        const registry = loadRegistry();
        const migrations = filterMigrations(registry.componentApiMigrations)
            .map((migration) => {
                return {
                    component: migration.handler ?? migration.component,
                    migration,
                };
            });

        return context.sourceCode.parserServices.defineTemplateBodyVisitor(
            // Event handlers for <template> tags
            {
                VElement(node) {
                    migrations
                        .filter(({ component }) => component === node.name)
                        .forEach(({ migration }) => {
                            migration.usage.forEach((usage) => {
                                runRegistryUsage(context, node, migration, usage);
                            });
                        });
                },
            },
        );
    },
};
