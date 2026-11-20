/**
 * @sw-package framework
 *
 * Upgrade codemod `asset-paths`: 6.8 removes the `.png` and `.jpg` images of the Administration that have a `.webp`
 * version since 6.7.6.0. The rule replaces the extension in strings, the fixed parts of template literals and static
 * attributes that point to the Administration's `static` folder: `administration/static/…`,
 * `administration/administration/static/…` (the path `assetFilter` expects) and `bundles/administration/…`.
 * Images of the extension's own bundle with the same name stay untouched.
 *
 * `cms/preview_youtube.jpg` and `cms/preview_vimeo.jpg` have no `.webp` version and get a codemod comment.
 * SCSS is not linted. The data is in `no-removed-image-assets.data.js`.
 */

const { reportTodo } = require('./upgrade-helpers/todo-comment');
const { templateAnchor } = require('./upgrade-helpers/create-todo-rule');
const { defineStringVisitors } = require('./upgrade-helpers/string-literals');
const { locOf } = require('./upgrade-helpers/twig-interpolations');
const { webp: WEBP, replaced: REPLACED } = require('./no-removed-image-assets.data');

const ID = 'asset-paths';
const WEBP_PATHS = new Set(WEBP);
const IMAGE_PATH = /administration\/(?:administration\/)?static\/([\w/.-]+?\.(?:png|jpg))(?![\w.-])/g;

/**
 * Whether the text before a match ends where an Administration asset path may start: at the start of the string,
 * after a quote, bracket or `}` of a template literal and an optional `/`, or after `bundles/`. A path like
 * `/acmeplugin/administration/static/…` belongs to another bundle.
 */
function startsAssetPath(before) {
    return /(?:^|[^\w./-])\/?$/.test(before) || /(?:^|\/)bundles\/$/.test(before);
}

const webpMessage = (image) => {
    const webp = image.replace(/\.(png|jpg)$/, '.webp');

    return `static/${image} of the Administration is removed in 6.8. Use static/${webp} instead.`;
};

const replacedMessage = (image) => {
    return `static/${image} of the Administration was removed in 6.7.8.0 and has no .webp version. Use static/${REPLACED[image]} instead.`;
};

/** @type {import('eslint').Rule.RuleModule} */
module.exports = {
    meta: {
        type: 'problem',
        fixable: 'code',
        docs: {
            description: 'Point the .png and .jpg images that 6.8 removes from the Administration to their .webp version',
        },
        schema: [],
    },

    /** @param {import('eslint').Rule.RuleContext} context */
    create(context) {
        const sourceCode = context.sourceCode;
        const seen = new Set();

        return defineStringVisitors(context, (node, value, scope) => {
            if (!value.includes('static/')) {
                return;
            }

            const text = sourceCode.text.slice(node.range[0], node.range[1]);

            for (const match of text.matchAll(IMAGE_PATH)) {
                const image = match[1];

                if (!startsAssetPath(text.slice(0, match.index))) {
                    continue;
                }

                const end = node.range[0] + match.index + match[0].length;
                const range = [node.range[0] + match.index, end];

                if (REPLACED[image]) {
                    reportTodo(context, {
                        id: ID,
                        message: replacedMessage(image),
                        range: scope === 'template' ? templateAnchor(node).range : range,
                        scope,
                        seen,
                    });
                } else if (WEBP_PATHS.has(image)) {
                    context.report({
                        loc: locOf(sourceCode, { range }),
                        message: webpMessage(image),
                        fix: (fixer) => fixer.replaceTextRange([end - 3, end], 'webp'),
                    });
                }
            }
        });
    },
};
