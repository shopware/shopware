/**
 * @sw-package framework
 */

const { loadRegistry } = require('./registry/load-registry');
const { hasComponentCodemod, hasPropCodemod, toPropName } = require('./registry/component-deprecations');
const { getDeprecatedOptionIndex } = require('./registry/deprecated-option-index');
const { getSelectedMigrationIds } = require('./registry/filter-migrations');
const { withRunLevel } = require('./registry/run-level');

function toKebabCase(name) {
    return name.replace(/([a-z0-9])([A-Z])/g, '$1-$2').toLowerCase();
}

/**
 * Returns the prop an attribute passes: `foo-bar="…"`, `fooBar="…"`, `:foo-bar`, `v-bind:foo-bar`,
 * `v-model:foo-bar` (fooBar) or a plain `v-model` (modelValue).
 * Other directives and dynamic arguments pass no statically known prop.
 *
 * @example
 * getPassedPropName(attributeOf(':empty-icon="icon"')); // 'emptyIcon'
 */
function getPassedPropName(attribute) {
    if (!attribute.directive) {
        return toPropName(attribute.key.rawName ?? attribute.key.name);
    }

    const directiveName = attribute.key.name.name;
    const argument = attribute.key.argument;

    if (directiveName === 'model' && argument === null) {
        return 'modelValue';
    }

    if (!['bind', 'model'].includes(directiveName) || argument?.type !== 'VIdentifier') {
        return null;
    }

    return toPropName(argument.rawName ?? argument.name);
}

function buildMessage(subject, { version, comment }) {
    return [
        `${subject} is deprecated and will be removed in Shopware ${version}.`,
        comment,
    ]
        .filter(Boolean)
        .join('\n');
}

/**
 * Reports every template usage of a component or prop whose definition carries a `deprecated`
 * option, the static counterpart of the runtime warning. Usages the deprecation registry has a
 * codemod for are left to the registry rules, which report them together with their fix.
 *
 * Invalid, with `emptyIcon: { type: String, deprecated: 'v6.8.0.0' }` on sw-image:
 * <sw-image :empty-icon="icon" />
 *
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = withRunLevel({
    meta: {
        type: 'problem',
        docs: {
            description: 'No template usage of components or props marked with the `deprecated` option',
            recommended: true,
        },
        schema: [
            {
                type: 'object',
                properties: {
                    componentSources: {
                        type: 'string',
                    },
                },
                additionalProperties: false,
            },
        ],
    },

    create(context) {
        // Usages without a registry codemod have no migration id, so an --id run leaves them out.
        if (getSelectedMigrationIds().length > 0) {
            return {};
        }

        const defineTemplateBodyVisitor = context.sourceCode.parserServices?.defineTemplateBodyVisitor;

        if (!defineTemplateBodyVisitor) {
            return {};
        }

        const deprecatedComponents = getDeprecatedOptionIndex(context.options[0]?.componentSources);
        const { componentApiMigrations } = loadRegistry();

        return defineTemplateBodyVisitor({
            VElement(node) {
                const componentName = toKebabCase(node.rawName);
                const component = deprecatedComponents.get(componentName);

                if (!component) {
                    return;
                }

                if (hasComponentCodemod(componentApiMigrations, componentName)) {
                    return;
                }

                if (component.deprecation) {
                    context.report({
                        node: node.startTag,
                        message: buildMessage(`The component "${componentName}"`, component.deprecation),
                    });
                }

                node.startTag.attributes.forEach((attribute) => {
                    const propName = getPassedPropName(attribute);
                    const propDeprecation = propName && component.props.get(propName);

                    if (!propDeprecation || hasPropCodemod(componentApiMigrations, componentName, propName)) {
                        return;
                    }

                    context.report({
                        node: attribute,
                        message: buildMessage(`The prop "${propName}" of the component "${componentName}"`, propDeprecation),
                    });
                });
            },
        });
    },
});
