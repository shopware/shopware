/**
 * @sw-package framework
 */

const { loadRegistry } = require('./registry/load-registry');
const { filterMigrations } = require('./registry/filter-migrations');
const { appendRegistryContext } = require('./registry/format-migration-message');

function buildMessage(migration, usage) {
    const replacement = usage.to ? ` Use ${usage.to} instead.` : '';

    return appendRegistryContext([`The "${usage.from}" API is deprecated.${replacement}`, usage.message ?? ''], migration);
}

function getPropertyName(node) {
    if (!node) {
        return null;
    }

    if (node.type === 'Identifier') {
        return node.name;
    }

    if (node.type === 'Literal' && typeof node.value === 'string') {
        return node.value;
    }

    return null;
}

function getMemberPropertyName(node) {
    if (node?.type !== 'MemberExpression') {
        return null;
    }

    return getPropertyName(node.property);
}

function getMemberName(node) {
    if (!node) {
        return null;
    }

    if (node.type === 'Identifier') {
        return node.name;
    }

    if (node.type === 'ThisExpression') {
        return 'this';
    }

    if (node.type !== 'MemberExpression') {
        return null;
    }

    const objectName = getMemberName(node.object);
    const propertyName = getPropertyName(node.property);

    if (!objectName || !propertyName) {
        return null;
    }

    return `${objectName}.${propertyName}`;
}

function getObjectOptionReplacement(sourceCode, node) {
    const valueText = sourceCode.getText(node.value);

    if (node.method || node.value.type !== 'ObjectExpression') {
        return null;
    }

    return `metaInfo() { return ${valueText}; }`;
}

function normalizeSource(text) {
    return text.replace(/\s+/g, ' ').trim();
}

function normalizeComparableSource(text) {
    return normalizeSource(text)
        .replace(/,\s*([}\]])/g, '$1')
        .replace(/\s+([}\])])/g, '$1')
        .replace(/"/g, "'");
}

function usageFixesAutomatically(usage) {
    return usage.fix !== 'manual';
}

/**
 * Builds the replacement text for a replace-api usage. An unsafe automatic fix gets a codemod
 * review comment in front, so the rewritten call is easy to find and check.
 *
 * @example
 * getReplaceApiText({ to: 'foo(element.id)', fix: 'auto' }); // 'foo(element.id)'
 * getReplaceApiText({ to: 'foo(element.id)', fix: 'unsafe-auto', message: 'Check element.' });
 * // 'foo(element.id)', preceded by a codemod review comment that says 'Check element.'
 */
function getReplaceApiText(usage) {
    if (usage.fix !== 'unsafe-auto') {
        return usage.to;
    }

    const hint = usage.message ?? 'Verify this automatic replacement.';

    return `/* TODO Codemod: ${hint} */ ${usage.to}`;
}

function matchesMemberCall(usage, propertyName, calleeName, calleeSource) {
    if (usage.from.includes('.')) {
        return (
            usage.from === calleeName || normalizeComparableSource(usage.from) === normalizeComparableSource(calleeSource)
        );
    }

    return usage.from === propertyName;
}

function parseSimpleObjectCall(source) {
    const match = source.match(/^([\w$.]+)\(\{\s*(.*)\s*}\)$/);

    if (!match) {
        return null;
    }

    const properties = new Map();
    const propertySources = match[2]
        .split(',')
        .map((propertySource) => propertySource.trim())
        .filter(Boolean);

    for (const propertySource of propertySources) {
        const separatorIndex = propertySource.indexOf(':');

        if (separatorIndex === -1) {
            return null;
        }

        properties.set(
            propertySource.slice(0, separatorIndex).trim(),
            normalizeComparableSource(propertySource.slice(separatorIndex + 1)),
        );
    }

    return {
        calleeName: match[1],
        properties,
    };
}

function getObjectExpressionProperties(sourceCode, node) {
    if (node?.type !== 'ObjectExpression') {
        return null;
    }

    const properties = new Map();

    for (const property of node.properties) {
        if (property.type !== 'Property') {
            return null;
        }

        const propertyName = getPropertyName(property.key);

        if (!propertyName) {
            return null;
        }

        properties.set(propertyName, normalizeComparableSource(sourceCode.getText(property.value)));
    }

    return properties;
}

function mapIncludesExpectedProperties(expectedProperties, actualProperties) {
    return Array.from(expectedProperties).every(
        ([
            propertyName,
            expectedValue,
        ]) => actualProperties.get(propertyName) === expectedValue,
    );
}

function matchesExactCall(usage, node, sourceCode, sourceText, calleeName) {
    if (normalizeComparableSource(usage.from) === normalizeComparableSource(sourceText)) {
        return {
            fixable: true,
        };
    }

    const expectedCall = parseSimpleObjectCall(usage.from);

    if (!expectedCall || expectedCall.calleeName !== calleeName || node.arguments.length !== 1) {
        return null;
    }

    const actualProperties = getObjectExpressionProperties(sourceCode, node.arguments[0]);

    if (!actualProperties || !mapIncludesExpectedProperties(expectedCall.properties, actualProperties)) {
        return null;
    }

    return {
        fixable: expectedCall.properties.size === actualProperties.size,
    };
}

module.exports = {
    meta: {
        type: 'suggestion',
        docs: {
            description: 'No usage of deprecated Administration JavaScript APIs',
            recommended: true,
        },
        fixable: 'code',
        schema: [
            {
                enum: [
                    'enableFix',
                    'disableFix',
                ],
            },
        ],
    },

    create(context) {
        const registry = loadRegistry();
        const usages = filterMigrations(registry.jsApiMigrations).flatMap((migration) => {
            return migration.usage.map((usage) => {
                return {
                    migration,
                    usage,
                };
            });
        });
        const packageUsages = filterMigrations(registry.packageMigrations).flatMap((migration) => {
            return migration.usage.map((usage) => {
                return {
                    migration,
                    usage,
                };
            });
        });

        const objectOptionUsages = usages.filter(({ usage }) => usage.kind === 'replace-object-option');
        const memberCallUsages = usages.filter(({ usage }) => usage.kind === 'member-call');
        const replaceApiUsages = usages.filter(({ usage }) => usage.kind === 'replace-api');
        const packageImportUsages = packageUsages.filter(({ usage }) => usage.kind === 'rename-package');

        /**
         * Finds the first replace-api usage a call matches. A bare API name replaces the callee only,
         * a full call signature replaces the whole call and may be unfixable when its arguments differ.
         *
         * @example
         * findReplaceApiMatch(node, 'Shopware.Utils.foo', 'Shopware.Utils.foo()');
         * // { match, fixable: true, target: node.callee }
         */
        function findReplaceApiMatch(node, calleeName, sourceText) {
            for (const candidate of replaceApiUsages) {
                const isFullCall = candidate.usage.from.includes('(');

                if (!isFullCall) {
                    if (candidate.usage.from === calleeName) {
                        return { match: candidate, fixable: true, target: node.callee };
                    }

                    continue;
                }

                const exactCallMatch = matchesExactCall(candidate.usage, node, context.sourceCode, sourceText, calleeName);

                if (exactCallMatch) {
                    return { match: candidate, fixable: exactCallMatch.fixable, target: node };
                }
            }

            return null;
        }

        /**
         * Finds the rename-package usage a module source names, either the package itself or one of its subpaths.
         * A package that only shares the name prefix does not match.
         *
         * @example
         * findPackageUsage({ type: 'Literal', value: '@shopware-ag/admin-extension-sdk/es/ui' });
         * // { migration, usage: { from: '@shopware-ag/admin-extension-sdk', to: '@shopware-ag/meteor-admin-sdk' } }
         */
        function findPackageUsage(sourceNode) {
            if (sourceNode?.type !== 'Literal' || typeof sourceNode.value !== 'string') {
                return null;
            }

            const source = sourceNode.value;

            return (
                packageImportUsages.find(({ usage }) => {
                    return source === usage.from || source.startsWith(`${usage.from}/`);
                }) ?? null
            );
        }

        /**
         * Reports a module source of a renamed package and rewrites only the package prefix,
         * keeping the subpath and the quote style.
         *
         * @example
         * // import { ui } from "@shopware-ag/admin-extension-sdk/es/ui";
         * // → import { ui } from "@shopware-ag/meteor-admin-sdk/es/ui";
         * reportPackageSource(node.source);
         */
        function reportPackageSource(sourceNode) {
            const match = findPackageUsage(sourceNode);

            if (!match) {
                return;
            }

            context.report({
                node: sourceNode,
                message: buildMessage(match.migration, match.usage),
                fix(fixer) {
                    if (context.options.includes('disableFix')) return null;

                    const quote = context.sourceCode.getText(sourceNode)[0];
                    const subpath = sourceNode.value.slice(match.usage.from.length);

                    return fixer.replaceText(sourceNode, `${quote}${match.usage.to}${subpath}${quote}`);
                },
            });
        }

        return {
            ImportDeclaration(node) {
                reportPackageSource(node.source);
            },

            ExportNamedDeclaration(node) {
                reportPackageSource(node.source);
            },

            ExportAllDeclaration(node) {
                reportPackageSource(node.source);
            },

            ImportExpression(node) {
                reportPackageSource(node.source);
            },

            Property(node) {
                const propertyName = getPropertyName(node.key);
                const match = objectOptionUsages.find(({ usage }) => usage.from === propertyName);

                if (!match || node.value.type !== 'ObjectExpression') {
                    return;
                }

                context.report({
                    node,
                    message: buildMessage(match.migration, match.usage),
                    fix(fixer) {
                        if (context.options.includes('disableFix')) return null;

                        const replacement = getObjectOptionReplacement(context.sourceCode, node);

                        if (!replacement) {
                            return null;
                        }

                        return fixer.replaceText(node, replacement);
                    },
                });
            },

            CallExpression(node) {
                if (node.callee.type === 'Identifier' && node.callee.name === 'require') {
                    reportPackageSource(node.arguments[0]);
                }

                const propertyName = getMemberPropertyName(node.callee);
                const calleeName = getMemberName(node.callee);
                const calleeSource = context.sourceCode.getText(node.callee);
                const match = memberCallUsages.find(({ usage }) => {
                    return matchesMemberCall(usage, propertyName, calleeName, calleeSource);
                });

                if (match) {
                    context.report({
                        node: node.callee.property,
                        message: buildMessage(match.migration, match.usage),
                    });
                }

                const result = findReplaceApiMatch(node, calleeName, context.sourceCode.getText(node));

                if (!result) {
                    return;
                }

                context.report({
                    node: result.target,
                    message: buildMessage(result.match.migration, result.match.usage),
                    fix(fixer) {
                        if (
                            context.options.includes('disableFix') ||
                            !usageFixesAutomatically(result.match.usage) ||
                            !result.fixable
                        ) {
                            return null;
                        }

                        return fixer.replaceText(result.target, getReplaceApiText(result.match.usage));
                    },
                });
            },
        };
    },
};
