/**
 * @sw-package framework
 */

const { COMPONENT_NAME_PATTERN, inferShopwareSetupFromFilename } = require('../../build/vue-setup-transform');

/**
 * The filename becomes the component's template tag and its public override target. The name is
 * escaped wherever it is emitted, so an unconventional one still builds: this is a lint convention,
 * not a build error. Multi-word is checked here because `vue/multi-word-component-names` sees `index`
 * for an index file and has to ignore it.
 *
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = {
    meta: {
        type: 'suggestion',
        docs: {
            description: 'Native setup SFC filenames should yield a kebab-case component name',
            category: 'Stylistic Issues',
            recommended: true,
        },
        schema: [],
        messages: {
            invalidName:
                'The component name "{{ componentName }}" comes from this filename and becomes a template tag and ' +
                'the public override target, so it should be multi-word lowercase kebab-case - for example ' +
                '"sw-product-list".',
        },
    },

    create(context) {
        return {
            Program(node) {
                const filename = context.filename ?? context.getFilename();

                if (!filename.endsWith('.vue')) {
                    return;
                }

                const { componentName } = inferShopwareSetupFromFilename(filename);

                if (COMPONENT_NAME_PATTERN.test(componentName)) {
                    return;
                }

                context.report({
                    node,
                    messageId: 'invalidName',
                    data: { componentName },
                });
            },
        };
    },
};
