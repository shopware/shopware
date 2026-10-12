/**
 * @sw-package framework
 */

const { loadRegistry } = require('./registry/load-registry');
const {
    findComponentDefinitions,
    getDeprecatedOption,
    getPropDefinitions,
    hasComponentCodemod,
    hasPropCodemod,
} = require('./registry/component-deprecations');

const RULE_NAME = 'sw-deprecation-rules/require-deprecation-codemod';

function buildMessage(subject) {
    return [
        `${subject} is marked \`deprecated\`, but the deprecation registry has no codemod for it.`,
        'Add a migration to src/app/deprecation-registry/definitions/components so extensions can migrate automatically,',
        `or explain why none is possible: // eslint-disable-next-line ${RULE_NAME} -- <why no codemod is possible>`,
    ].join('\n');
}

/**
 * Every `deprecated` option on a component or prop needs a matching codemod in the deprecation
 * registry, so extension authors get a migration for each runtime deprecation warning.
 *
 * Invalid:
 * Component.register('sw-foo', { deprecated: 'v6.8.0.0' }); // no "component.sw-foo" migration
 *
 * Valid:
 * Component.register('sw-alert', { deprecated: 'v6.8.0.0' }); // registry migrates sw-alert to mt-banner
 *
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'Require a deprecation registry codemod for every deprecated component and prop',
            recommended: true,
        },
        schema: [],
    },

    create(context) {
        const { componentApiMigrations } = loadRegistry();

        return {
            Program(program) {
                findComponentDefinitions(program, context.filename).forEach(({ name, options }) => {
                    const componentDeprecation = getDeprecatedOption(options);

                    if (componentDeprecation && !hasComponentCodemod(componentApiMigrations, name)) {
                        context.report({
                            node: componentDeprecation,
                            message: buildMessage(`The component "${name}"`),
                        });
                    }

                    getPropDefinitions(options).forEach((prop) => {
                        const propDeprecation = getDeprecatedOption(prop.definition);

                        if (propDeprecation && !hasPropCodemod(componentApiMigrations, name, prop.name)) {
                            context.report({
                                node: propDeprecation,
                                message: buildMessage(`The prop "${prop.name}" of "${name}"`),
                            });
                        }
                    });
                });
            },
        };
    },
};
