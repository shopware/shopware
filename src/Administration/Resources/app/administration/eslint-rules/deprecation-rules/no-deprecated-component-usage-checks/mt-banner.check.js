const { findShortHandSlot, removeWhitespaceFromSlot } = require('./helper');

/** @param {RuleContext} context
 *  @param {VElement} node
 */
const handleMtBanner = (context, node) => {
    const mtComponentName = 'mt-banner';

    if (node.name !== mtComponentName) {
        return;
    }

    // Check if the mt-banner has the attribute "notificationIndex"
    const notificationIndexAttr = node.startTag.attributes.find((attr) => {
        return [
            'notificationindex',
            'notification-index',
            'notificationIndex',
        ].includes(attr.key.name);
    });

    if (notificationIndexAttr) {
        context.report({
            node: notificationIndexAttr,
            message: `[${mtComponentName}] The "notificationIndex" prop is deprecated. Use "bannerIndex" instead.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.replaceText(notificationIndexAttr.key, 'bannerIndex');
            }
        });
    }

    // Check if the mt-banner uses deprecated attribute `appearance`
    const appearanceAttr = node.startTag.attributes.find((attr) => attr.key.name === 'appearance');
    if (appearanceAttr) {
        context.report({
            node: appearanceAttr,
            message: `[${mtComponentName}] The "appearance" prop is deprecated. Remove it.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(appearanceAttr);
            }
        })
    }

    // Check if the mt-banner has the attribute "showIcon"
    const showIconAttr = node.startTag.attributes.find((attr) => {
        return [
            'showicon',
            'show-icon',
            'showIcon',
        ].includes(attr.key.name);
    });

    if (showIconAttr) {
        context.report({
            node: showIconAttr,
            message: `[${mtComponentName}] The "showIcon" prop is deprecated. Use "hideIcon" instead.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                // empty showIcon is default behaviour with hideIcon being false per default
                if (!showIconAttr.value) {
                    yield fixer.remove(showIconAttr);
                    return;
                }

                // replace showIcon with hide-icon and negate the condition
                yield fixer.replaceText(showIconAttr, `hide-icon="!(${showIconAttr.value.value})"`);
            }
        });
    }

    // Check if the mt-banner uses deprecated variants
    const deprecatedVariants = {
        warning: 'attention',
        error: 'critical',
        success: 'positive',
    };
    const variantAttr = node.startTag.attributes.find((attr) => {
        return attr.key.name === 'variant' && Object.keys(deprecatedVariants).includes(attr.value.value);
    });
    if (variantAttr) {
        context.report({
            node: variantAttr,
            message: `[${mtComponentName}] The value "${variantAttr.value.value}" for prop "variant" is deprecated. Use "${deprecatedVariants[variantAttr.value.value]}" instead.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.replaceText(variantAttr.value, `"${deprecatedVariants[variantAttr.value.value]}"`);
            }
        })
    }

    // Check if the mt-banner uses deprecated slot actions
    const actionsSlot = findShortHandSlot(node, 'actions');
    if(actionsSlot) {
        context.report({
            node: actionsSlot,
            message: `[${mtComponentName}] The "actions" slot is deprecated. Remove it.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.replaceText(actionsSlot, `<!-- Slot "actions" was removed and has no replacement. -->`);
            }
        })
    }
};

const mtBannerValidTests = [
    {
        name: '"sw-alert" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-alert />
            </template>`
    }
];

const mtBannerInvalidTests = [
    {
        name: '"mt-banner" wrong "notificationIndex" prop usage should be replaced with "bannerIndex"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-banner notificationIndex="1" />
            </template>`,
        output: `
            <template>
                <mt-banner bannerIndex="1" />
            </template>`,
        errors: [{
            message: '[mt-banner] The "notificationIndex" prop is deprecated. Use "bannerIndex" instead.',
        }]
    },
    {
        name: '"mt-banner" wrong "appearance" prop usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-banner appearance="foobar" />
            </template>`,
        output: `
            <template>
                <mt-banner  />
            </template>`,
        errors: [{
            message: '[mt-banner] The "appearance" prop is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-banner" wrong "showIcon" prop usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-banner showIcon />
            </template>`,
        output: `
            <template>
                <mt-banner  />
            </template>`,
        errors: [{
                message: '[mt-banner] The "showIcon" prop is deprecated. Use "hideIcon" instead.',
        }]
    },
    {
        name: '"mt-banner" wrong "showIcon" prop usage with condition should be replaced with "hideIcon"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-banner showIcon="condition" />
            </template>`,
        output: `
            <template>
                <mt-banner hide-icon="!(condition)" />
            </template>`,
        errors: [{
                message: '[mt-banner] The "showIcon" prop is deprecated. Use "hideIcon" instead.',
        }]
    },
    {
        name: '"mt-banner" wrong "variant" prop usage with value "warning" should be replaced with "attention"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-banner variant="warning" />
            </template>`,
        output: `
            <template>
                <mt-banner variant="attention" />
            </template>`,
        errors: [{
            message: '[mt-banner] The value "warning" for prop "variant" is deprecated. Use "attention" instead.',
        }]
    },
    {
        name: '"mt-banner" wrong "variant" prop usage with value "error" should be replaced with "critical"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-banner variant="error" />
            </template>`,
        output: `
            <template>
                <mt-banner variant="critical" />
            </template>`,
        errors: [{
            message: '[mt-banner] The value "error" for prop "variant" is deprecated. Use "critical" instead.',
        }]
    },
    {
        name: '"mt-banner" wrong "variant" prop usage with value "success" should be replaced with "positive"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-banner variant="success" />
            </template>`,
        output: `
            <template>
                <mt-banner variant="positive" />
            </template>`,
        errors: [{
            message: '[mt-banner] The value "success" for prop "variant" is deprecated. Use "positive" instead.',
        }]
    },
    {
        name: '"mt-banner" deprecated "actions" slot usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-banner>
                    <template #actions>
                        <sw-button />
                    </template>
                </mt-banner>
            </template>`,
        output: `
            <template>
                <mt-banner>
                    <!-- Slot "actions" was removed and has no replacement. -->
                </mt-banner>
            </template>`,
        errors: [{
            message: '[mt-banner] The "actions" slot is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-banner" deprecated "actions" slot usage should be removed [v-slot syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-banner>
                    <template v-slot:actions>
                        <sw-button />
                    </template>
                </mt-banner>
            </template>`,
        output: `
            <template>
                <mt-banner>
                    <!-- Slot "actions" was removed and has no replacement. -->
                </mt-banner>
            </template>`,
        errors: [{
            message: '[mt-banner] The "actions" slot is deprecated. Remove it.',
        }]
    },
];

module.exports = {
    handleMtBanner,
    mtBannerValidTests,
    mtBannerInvalidTests,
};
