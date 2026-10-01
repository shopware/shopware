/**
 * @sw-package framework
 */

const path = require('path');

/**
 * The shape a native setup component name must have: multi-segment lowercase kebab-case.
 *
 * Multi-word is checked here because `vue/multi-word-component-names` sees `index` for an index file and
 * has to ignore it; only this rule resolves the directory-derived name.
 */
const COMPONENT_NAME_PATTERN = /^[a-z][a-z0-9]*(-[a-z0-9]+)+$/;

/**
 * Derives the component name the transform would infer from a filename.
 *
 * Mirrors `inferShopwareSetupFromFilename` in build/vue-setup-transform: the name is the filename
 * without its suffix, or the directory name for an index file.
 */
function deriveComponentName(filename) {
    const file = path.basename(filename);
    const suffix = file.endsWith('.override.vue') ? '.override.vue' : '.vue';

    if (file === `index${suffix}`) {
        return path.basename(path.dirname(filename));
    }

    return file.slice(0, -suffix.length);
}

/**
 * Reports SFC filenames that would register a component under a non-conventional name.
 *
 * The filename becomes the component's template tag and its public override target, so `Bad_Name.vue`
 * yields a working but unconventional component. The name is escaped wherever it is emitted, so this is a
 * convention checked by lint, not a build error.
 *
 * No gate on file contents: a `.vue` file that is not a valid native setup component does not build.
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

                const componentName = deriveComponentName(filename);

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
