/**
 * @sw-package framework
 */

const { loadRegistry } = require('./registry/load-registry');
const { hasComponentCodemod, hasPropCodemod } = require('./registry/component-deprecations');
const { getDeprecatedOptionIndex } = require('./registry/deprecated-option-index');

function toKebabCase(name) {
    return name.replace(/([a-z0-9])([A-Z])/g, '$1-$2').toLowerCase();
}

function toCamelCase(name) {
    return name.replace(/-([a-z])/g, (_match, letter) => letter.toUpperCase());
}

/**
 * Returns the prop an attribute passes: `foo-bar="…"`, `fooBar="…"`, `:foo-bar` or `v-bind:foo-bar`.
 * Other directives and dynamic arguments pass no statically known prop.
 *
 * @example
 * getPassedPropName(attributeOf(':empty-icon="icon"')); // 'emptyIcon'
 */
function getPassedPropName(attribute) {
    if (!attribute.directive) {
        return toCamelCase(attribute.key.rawName ?? attribute.key.name);
    }

    const argument = attribute.key.argument;

    if (attribute.key.name.name !== 'bind' || argument?.type !== 'VIdentifier') {
        return null;
    }

    return toCamelCase(argument.rawName ?? argument.name);
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
module.exports = {
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
        const defineTemplateBodyVisitor = context.sourceCode.parserServices?.defineTemplateBodyVisitor;

        if (!defineTemplateBodyVisitor) {
            return {};
        }

        const deprecatedComponents = getDeprecatedOptionIndex(context.options[0]?.componentSources);

        return defineTemplateBodyVisitor({
            VElement(node) {
                const componentName = toKebabCase(node.rawName);
                const component = deprecatedComponents.get(componentName);

                if (!component) {
                    return;
                }

                const { componentApiMigrations } = loadRegistry();

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
};
