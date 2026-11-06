/**
 * @sw-package framework
 */

const { loadRegistry } = require('./registry/load-registry');
const { selectedUsages } = require('./registry/filter-migrations');
const { appendRegistryContext } = require('./registry/format-migration-message');
const { FIX_OPTION_SCHEMA, canAutoFix } = require('./registry/fix-policy');
const { withRunLevel } = require('./registry/run-level');

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

/**
 * Turns an object option into a method that returns the object, e.g. for options that became functions.
 *
 * @example
 * getObjectOptionReplacement(sourceCode, metaInfoProperty, 'metaInfo');
 * // metaInfo: { title: 'x' } → metaInfo() { return { title: 'x' }; }
 */
function getObjectOptionReplacement(sourceCode, property, optionName) {
    return `${optionName}() { return ${sourceCode.getText(property.value)}; }`;
}

const COMPONENT_OPTION_CALLS = new Set([
    'register',
    'extend',
    'override',
    'wrapComponentConfig',
    'defineComponent',
]);

/**
 * Tells whether an object literal is the options object of a component: exported as default, or the
 * options argument of `Component.register` / `extend` / `override`, `wrapComponentConfig` or
 * `defineComponent`. Other objects can carry a property of the same name, such as a store payload.
 *
 * @example
 * isComponentOptions(objectOf("Shopware.Component.register('sw-foo', { metaInfo: {} })")); // true
 * isComponentOptions(objectOf('store.save({ metaInfo: {} })')); // false
 */
function isComponentOptions(objectNode) {
    const parent = objectNode.parent;

    if (parent?.type === 'ExportDefaultDeclaration') {
        return true;
    }

    if (parent?.type !== 'CallExpression' || parent.arguments[parent.arguments.length - 1] !== objectNode) {
        return false;
    }

    const callee = parent.callee;
    const calleeName = callee.type === 'MemberExpression' ? getPropertyName(callee.property) : callee.name;

    return COMPONENT_OPTION_CALLS.has(calleeName);
}

/**
 * Normalizes a callee's source text for comparison with a registry `from`, which may contain a call
 * such as `Shopware.Store.get('shopwareApps').setAppModules`: whitespace, trailing commas and quote
 * style do not change which API is called.
 *
 * @example
 * normalizeComparableSource('Shopware.Store.get("shopwareApps",\n).setAppModules');
 * // "Shopware.Store.get('shopwareApps').setAppModules"
 */
function normalizeComparableSource(text) {
    return text
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/,\s*([}\]])/g, '$1')
        .replace(/\s+([}\])])/g, '$1')
        .replace(/"/g, "'");
}

/**
 * Tells whether a call matches a member-call step: a dotted `from` names the whole callee, a plain
 * `from` only the called method.
 *
 * @example
 * matchesMemberCall({ from: 'this.computePath' }, 'computePath', 'this.computePath', 'this.computePath'); // true
 */
function matchesMemberCall(usage, propertyName, calleeName, calleeSource) {
    if (usage.from.includes('.')) {
        return (
            usage.from === calleeName || normalizeComparableSource(usage.from) === normalizeComparableSource(calleeSource)
        );
    }

    return usage.from === propertyName;
}

/**
 * This rule reports deprecated JavaScript APIs from the registry: object options that became methods,
 * deprecated member calls and imports of renamed packages, and fixes the first and the last.
 *
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = withRunLevel({
    meta: {
        type: 'suggestion',
        docs: {
            description: 'No usage of deprecated Administration JavaScript APIs',
            recommended: true,
        },
        fixable: 'code',
        schema: FIX_OPTION_SCHEMA,
    },

    create(context) {
        const registry = loadRegistry();
        const objectOptionUsages = selectedUsages(registry.jsApiMigrations, 'replace-object-option');
        const memberCallUsages = selectedUsages(registry.jsApiMigrations, 'member-call');
        const packageImportUsages = selectedUsages(registry.packageMigrations, 'rename-package');

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
                    if (!canAutoFix(context, match.usage)) {
                        return null;
                    }

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

                if (!match || node.method || node.value.type !== 'ObjectExpression' || !isComponentOptions(node.parent)) {
                    return;
                }

                context.report({
                    node,
                    message: buildMessage(match.migration, match.usage),
                    fix(fixer) {
                        if (!canAutoFix(context, match.usage)) {
                            return null;
                        }

                        return fixer.replaceText(
                            node,
                            getObjectOptionReplacement(context.sourceCode, node, match.usage.from),
                        );
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
            },
        };
    },
});
