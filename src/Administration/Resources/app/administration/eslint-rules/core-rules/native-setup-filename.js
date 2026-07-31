/**
 * @sw-package framework
 */

const path = require('path');

/**
 * The shape a native setup component name is expected to have: multi-segment lowercase kebab-case.
 *
 * Multi-word is enforced here rather than left to `vue/multi-word-component-names`, because that rule
 * sees the *component* name and `sw-thing/index.vue` reports as the literal `index` - which the config
 * has to ignore. Only this rule knows the name really came from the directory, so only this rule can
 * hold every `.vue` file to the convention.
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

    return file.endsWith(suffix) ? file.slice(0, -suffix.length) : file;
}

/**
 * Reports SFC filenames that would register a component under a non-conventional name.
 *
 * The transform derives the component name from the filename, and that name is both a template tag and
 * the public override target - so a file like `Bad_Name.vue` produces a working but unconventional
 * component that no other Administration component resembles. The name is escaped wherever it is emitted,
 * so this is a convention rather than a correctness problem; it is enforced by lint, not by the build.
 *
 * It applies to every `.vue` file, with no gate on the file's contents: an SFC that is not a valid native
 * setup component does not build at all, so there is no such thing as a `.vue` file whose filename is not
 * a component name.
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
