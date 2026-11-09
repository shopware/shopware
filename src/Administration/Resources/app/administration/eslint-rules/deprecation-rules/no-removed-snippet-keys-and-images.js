/**
 * @sw-package framework
 *
 * Shopware 6.8 removes some Administration snippet keys and the `.png`/`.jpg` versions of the
 * static images that have a `.webp` version. The codemod rewrites string literals that are
 * exactly such a key or image path, e.g. `assetFilter('/administration/administration/static/img/cms/preview_plant_small.jpg')`.
 * A key or image without a replacement gets a TODO comment.
 */

const { createTodoReporter, todoMessages } = require('./utils/todo-comment');

const SNIPPET_KEYS = {
    'global.sw-condition.condition.cartTaxDisplay.gross': 'global.sw-condition-generic.cartTaxDisplay.taxDisplay.options.gross',
    'global.sw-condition.condition.cartTaxDisplay.net': 'global.sw-condition-generic.cartTaxDisplay.taxDisplay.options.net',
    'global.sw-condition.condition.lineItemOfTypeRule.product': 'global.sw-condition-generic.cartLineItemOfType.lineItemType.options.product',
    'global.sw-condition.condition.lineItemOfTypeRule.discount_surcharge': 'global.sw-condition-generic.cartLineItemOfType.lineItemType.options.promotion',
    'global.sw-condition.condition.promotionCodeOfTypeRule.global': 'global.sw-condition-generic.promotionCodeOfType.promotionCodeType.options.global',
    'global.sw-condition.condition.promotionCodeOfTypeRule.fixed': 'global.sw-condition-generic.promotionCodeOfType.promotionCodeType.options.fixed',
    'global.sw-condition.condition.promotionCodeOfTypeRule.individual': 'global.sw-condition-generic.promotionCodeOfType.promotionCodeType.options.individual',
};

const REMOVED_SNIPPET_KEYS = {
    'sw-import-export.profile.addNewProfileTooltipLanguage': 'The snippet sw-import-export.profile.addNewProfileTooltipLanguage is removed in 6.8 without replacement.',
};

/** Images below `administration/static/` whose `.png`/`.jpg` version is removed, without the extension. */
const WEBP_IMAGES = new Set([
    'img/sw-login-background',
    'img/plugin-manager--login',
    'img/data-consent-background',
    'img/flowbuilder/ui-sample',
    'img/theme/default_theme_preview',
    'img/cms/preview_plant_small',
    'img/cms/preview_plant_large',
    'img/cms/preview_glasses_small',
    'img/cms/preview_glasses_large',
    'img/cms/preview_mountain_small',
    'img/cms/preview_mountain_large',
    'img/cms/preview_camera_small',
    'img/cms/preview_camera_large',
    'img/cms/preview_page_default',
    'img/cms/preview_page_sidebar',
    'img/cms/preview_custom_entity_detail_default',
    'img/cms/preview_custom_entity_detail_sidebar',
    'img/cms/preview_custom_entity_list_default',
    'img/cms/preview_custom_entity_list_sidebar',
    'img/cms/preview_product_detail_default',
    'img/cms/preview_product_detail_sidebar',
    'img/cms/preview_product_list_default',
    'img/cms/preview_product_list_sidebar',
    'img/cms/preview_landingpage_default',
    'img/cms/preview_landingpage_sidebar',
    'img/cms/default_preview_product_detail',
    'img/cms/default_preview_product_list',
    'img/cms/vimeo-icon',
    'img/cms/youtube-icon',
    'fixtures/sw-login-background',
    'fixtures/sw-login-background-2',
    'fixtures/sw-test-image',
]);

/** Images that are removed without a `.webp` version. */
const REMOVED_IMAGES = new Set([
    'img/cms/preview_youtube',
    'img/cms/preview_vimeo',
]);

// The asset filter takes paths with or without `bundles/` and the doubled `administration/` folder.
const IMAGE_PATH = /^(\/?(?:bundles\/)?administration\/(?:administration\/)?static\/)((?:img|fixtures)\/[\w./-]+)\.(png|jpg)$/;

/**
 * @param {string} value
 * @returns {{ replacement: string } | { hint: string } | null}
 */
function replacementFor(value) {
    if (SNIPPET_KEYS[value]) {
        return { replacement: SNIPPET_KEYS[value] };
    }

    if (REMOVED_SNIPPET_KEYS[value]) {
        return { hint: REMOVED_SNIPPET_KEYS[value] };
    }

    const image = IMAGE_PATH.exec(value);

    if (!image) {
        return null;
    }

    const [, prefix, file] = image;

    if (WEBP_IMAGES.has(file)) {
        return { replacement: `${prefix}${file}.webp` };
    }

    if (REMOVED_IMAGES.has(file)) {
        return { hint: `The image ${file} is removed in 6.8 without a .webp version. Core uses static/img/cms/preview_mountain_small.webp instead.` };
    }

    return null;
}

/**
 * The text of the literal with the new value, in the quotes of the original.
 *
 * @param {string} raw
 * @param {string} value
 */
function quote(raw, value) {
    const quoteChar = raw[0];

    return `${quoteChar}${value}${quoteChar}`;
}

/** @type {import('eslint').Rule.RuleModule} */
module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'Replace snippet keys and static images that Shopware 6.8 removes',
            category: 'Shopware 6.8 Migration',
            recommended: false,
        },
        fixable: 'code',
        schema: [],
        messages: {
            ...todoMessages,
            replace: '"{{ value }}" is removed in 6.8. Use "{{ replacement }}" instead.',
        },
    },
    create(context) {
        const sourceCode = context.sourceCode;
        const reporter = createTodoReporter(context);

        /**
         * @param {{ range: [number, number] }} node the literal
         * @param {string} value
         * @param {(hint: string) => void} reportTodo
         */
        const check = (node, value, reportTodo) => {
            const result = replacementFor(value);

            if (!result) {
                return;
            }

            if ('hint' in result) {
                reportTodo(result.hint);

                return;
            }

            context.report({
                loc: sourceCode.getLocFromIndex(node.range[0]),
                messageId: 'replace',
                data: { value, replacement: result.replacement },
                fix: (fixer) => fixer.replaceTextRange(node.range, quote(sourceCode.text.slice(...node.range), result.replacement)),
            });
        };

        const enclosingElement = (node) => {
            let current = node.parent;

            while (current && current.type !== 'VElement') {
                current = current.parent;
            }

            return current;
        };

        const literalVisitor = (inTemplate) => ({
            Literal(node) {
                if (typeof node.value !== 'string') {
                    return;
                }

                check(node, node.value, (hint) => {
                    const element = inTemplate ? enclosingElement(node) : null;

                    if (!inTemplate) {
                        reporter.reportScript(node, hint);
                    } else if (element) {
                        reporter.reportTemplate(element, hint);
                    }
                });
            },

            TemplateLiteral(node) {
                if (node.expressions.length > 0) {
                    return;
                }

                check(node, node.quasis[0].value.cooked, (hint) => {
                    if (!inTemplate) {
                        reporter.reportScript(node, hint);
                    }
                });
            },
        });

        const parserServices = sourceCode.parserServices;

        if (!parserServices?.defineTemplateBodyVisitor) {
            return literalVisitor(false);
        }

        return parserServices.defineTemplateBodyVisitor({
            ...literalVisitor(true),

            // A static attribute, e.g. `src="/administration/static/img/sw-login-background.png"`.
            VLiteral(node) {
                if (node.parent.type !== 'VAttribute' || node.parent.directive) {
                    return;
                }

                const raw = sourceCode.text.slice(...node.range);

                if (!/^["']/.test(raw)) {
                    return;
                }

                check(node, node.value, (hint) => reporter.reportTemplate(node.parent.parent.parent, hint));
            },
        }, literalVisitor(false));
    },
};
