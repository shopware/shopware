/**
 * @sw-package framework
 */

const path = require('path');

/**
 * The shape a native setup component name is expected to have: lowercase kebab-case.
 *
 * A single segment is allowed here because `vue/multi-word-component-names` already reports that, with
 * a message about the Vue convention rather than about the filename.
 */
const COMPONENT_NAME_PATTERN = /^[a-z][a-z0-9]*(-[a-z0-9]+)*$/;

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

    return file.endsWith(suffix) ? file.slice(0, -suffix.length) : file;
}

/**
 * Reports SFC filenames that would register a component under a non-conventional name.
 *
 * The transform derives the component name from the filename, and that name is both a template tag and
 * the public override target - so a file like `Bad_Name.vue` produces a working but unconventional
 * component that no other Administration component resembles. This is a naming convention rather than a
 * correctness problem (the name is escaped wherever it is emitted), so it is reported here instead of
 * failing the build.
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
                'the public override target, so it should be lowercase kebab-case - for example "sw-product-list".',
        },
    },

    create(context) {
        return {
            Program(node) {
                const filename = context.getFilename();

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
