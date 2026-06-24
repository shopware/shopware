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

function getReplacementAttributeName(replacement) {
    return replacement.trim().match(/^[^\s=]+/)?.[0] ?? replacement;
}

function getReplacementDescriptor(attribute, target, replacement) {
    const replacementName = getReplacementAttributeName(replacement);

    if (attribute === target || attribute.key === target) {
        if (replacementName === 'v-model' || replacementName.startsWith('v-model:')) {
            return {
                kind: 'model',
                name: normalizeAttributeName(replacementName),
            };
        }

        if (replacementName.startsWith('@')) {
            return {
                kind: 'event',
                name: normalizeAttributeName(replacementName.slice(1)),
            };
        }

        if (replacementName.startsWith('v-on:')) {
            return {
                kind: 'event',
                name: normalizeAttributeName(replacementName.slice('v-on:'.length)),
            };
        }

        return {
            kind: 'prop',
            name: normalizeAttributeName(replacementName),
        };
    }

    if (attribute.key?.argument === target) {
        const directive = attribute.key?.name?.name;

        if (directive === 'bind') {
            return {
                kind: 'prop',
                name: normalizeAttributeName(replacementName),
            };
        }

        if (directive === 'on') {
            return {
                kind: 'event',
                name: normalizeAttributeName(replacementName),
            };
        }

        if (directive === 'model') {
            return {
                kind: 'model',
                name: normalizeAttributeName(`v-model:${replacementName}`),
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
            return attribute === target || attribute.key === target || attribute.key?.argument === target;
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
        const expression = attribute.value?.expression;

        if (expression?.type === 'Literal' && expression.value === true) {
            return 'static';
        }

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

function createComponentUsageRuleApi(context, node, migration, usage) {
    return {
        context,
        sourceCode: context.sourceCode,
        node,
        migration,
        usage,
        appendRegistryContext,
        reportWithDuplicateReplacementGuard(descriptor) {
            reportWithDuplicateReplacementGuard(context, descriptor);
        },
        isFixDisabled() {
            return context.options.includes('disableFix');
        },
        getTransformResult(usageConfig, usageNode, attribute) {
            return getTransformResult(usageConfig, usageNode, attribute);
        },
        ast: {
            findMatchingPropAttribute,
            hasMatchingPropAttribute,
            findMatchingEventAttribute,
            findMatchingVModelAttribute,
            findSlot,
            getCondensedTextContent,
            getDirectiveName,
            getFirstElementChildWithoutSlot,
            getStaticAttributeName,
            getDirectiveArgumentName,
            getAttributeValueSource(attribute) {
                return getAttributeValueSource(context, attribute);
            },
            hasCodemodComment(usageNode, text) {
                return hasCodemodComment(context, usageNode, text);
            },
        },
    };
}

function runRegistryUsage(context, node, migration, usage) {
    if (usage.eslint?.report) {
        usage.eslint.report(createComponentUsageRuleApi(context, node, migration, usage));
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
