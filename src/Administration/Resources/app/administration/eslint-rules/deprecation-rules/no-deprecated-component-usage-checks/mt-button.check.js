/** @param {RuleContext} context
 *  @param {VElement} node
 */
const handleMtButton = (context, node) => {
    const mtButtonComponentName = 'mt-button';

    // Refactor the old usage of sw-button to mt-button after the migration to the new component
    if (node.name !== mtButtonComponentName) {
        return;
    }

    const startTag = node.startTag;
    const attributes = startTag.attributes;
    const templateComments = context.getSourceCode().ast?.templateBody?.comments;

    // Attribute checks
    const isBindAttribute = (attr) => attr.type === 'VAttribute' && attr.key.name.name === 'bind';
    const variantAttribute = attributes.find((attr) => attr.key.name === 'variant');
    const routerLinkAttribute = attributes.find((attr) => {
        // Check for bind attribute
        if (isBindAttribute(attr)) {
            return attr?.key?.argument?.name === 'router-link';
        }

        return attr.key.name === 'router-link';
    });

    // Check if attribute "variant" contains value "ghost"
    if (variantAttribute && variantAttribute.value.value === 'ghost') {
        context.report({
            node,
            message: '[mt-button] The "variant" prop with value "ghost" is deprecated. Please use the "ghost" prop instead.',
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.insertTextAfterRange(variantAttribute.range, 'ghost');
                yield fixer.removeRange(variantAttribute.range);
            }
        });
    }

    // Check if attribute "variant" contains value "danger"
    if (variantAttribute && variantAttribute.value.value === 'danger') {
        context.report({
            node,
            message: '[mt-button] The "variant" prop with value "danger" is deprecated. Please use the "critical" prop instead.',
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.replaceTextRange(variantAttribute.value.range, '"critical"');
            }
        });
    }

    // Check if attribute "variant" contains value "ghost-danger"
    if (variantAttribute && variantAttribute.value.value === 'ghost-danger') {
        context.report({
            node,
            message: '[mt-button] The "variant" prop with value "ghost-danger" is deprecated. Please use the "critical" prop in combination with "ghost" prop instead.',
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.insertTextAfterRange(variantAttribute.range, ' ghost');
                yield fixer.replaceTextRange(variantAttribute.value.range, '"critical"');
            }
        });
    }

    // Check if attribute "variant" contains value "contrast"
    if (variantAttribute && variantAttribute.value.value === 'contrast') {
        context.report({
            node,
            message: '[mt-button] The "variant" prop with value "contrast" is deprecated without replacement.',
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                // Replace the value with a placeholder
                yield fixer.replaceTextRange(
                    variantAttribute.value.range,
                    '"TODO-Codemod-Variant-Contrast-Was-Removed"'
                );
            }
        });
    }

    // Check if attribute "variant" contains value "context"
    if (variantAttribute && variantAttribute.value.value === 'context') {
        context.report({
            node,
            message: '[mt-button] The "variant" prop with value "context" is deprecated without replacement.',
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                // Replace the value with a placeholder
                yield fixer.replaceTextRange(
                    variantAttribute.value.range,
                    '"TODO-Codemod-Variant-Context-Was-Removed"'
                );
            }
        });
    }

    // Check if attribute "router-link" is used
    if (routerLinkAttribute) {
        context.report({
            node,
            message: '[mt-button] The "router-link" prop is deprecated without replacement.',
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                /* Replace the router-link attribute with "@click" event
                 * which calls the "$router.push()" method with the desired route
                 */
                let routerLinkValue;
                if (isBindAttribute(routerLinkAttribute)) {
                    // Get the value of the bind attribute
                    const range = routerLinkAttribute.value.range;
                    routerLinkValue = context.getSourceCode().text.slice(range[0], range[1]);
                    // Remove the quotes from the value
                    routerLinkValue = routerLinkValue.slice(1, routerLinkValue.length - 1);
                } else {
                    routerLinkValue = routerLinkAttribute.value.value;
                    // Add quotes to the value
                    routerLinkValue = `'${routerLinkValue}'`;
                }

                yield fixer.replaceTextRange(
                    routerLinkAttribute.range,
                    `@click="$router.push(${routerLinkValue})"`
                );
            }
        });
    }
}

const mtButtonValidChecks = [
    {
        name: '"mt-button" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-button>Hello</mt-button>
            </template>`
    },
    {
        name: '"sw-button" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-button>Hello</sw-button>
            </template>`
    },
    {
        name: '"mt-button" new ghost prop usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-button ghost>Hello</mt-button>
            </template>`
    },
    {
        name: 'Ignore wrong "sw-button" usage with old variant prop "ghost"',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-button variant="ghost">Hello</sw-button>
            </template>`,
    }
]
const mtButtonInvalidChecks = [
    {
        name: '"mt-button" wrong ghost prop usage',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-button variant="ghost">Hello</mt-button>
            </template>`,
        output: `
            <template>
                <mt-button ghost>Hello</mt-button>
            </template>`,
        errors: [{
            message: '[mt-button] The "variant" prop with value "ghost" is deprecated. Please use the "ghost" prop instead.',
        }]
    },
    {
        name: '"mt-button" wrong ghost prop usage [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-button variant="ghost">Hello</mt-button>
            </template>`,
        errors: [{
            message: '[mt-button] The "variant" prop with value "ghost" is deprecated. Please use the "ghost" prop instead.',
        }]
    },
    {
        name: '"mt-button" wrong danger prop usage in variant',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-button variant="danger">Hello</mt-button>
            </template>`,
        output: `
            <template>
                <mt-button variant="critical">Hello</mt-button>
            </template>`,
        errors: [{
            message: '[mt-button] The "variant" prop with value "danger" is deprecated. Please use the "critical" prop instead.',
        }]
    },
    {
        name: '"mt-button" wrong danger prop usage in variant [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-button variant="danger">Hello</mt-button>
            </template>`,
        errors: [{
            message: '[mt-button] The "variant" prop with value "danger" is deprecated. Please use the "critical" prop instead.',
        }]
    },
    {
        name: '"mt-button" wrong ghost-danger prop usage in variant',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-button variant="ghost-danger">Hello</mt-button>
            </template>`,
        output: `
            <template>
                <mt-button variant="critical" ghost>Hello</mt-button>
            </template>`,
        errors: [{
            message: '[mt-button] The "variant" prop with value "ghost-danger" is deprecated. Please use the "critical" prop in combination with "ghost" prop instead.',
        }]
    },
    {
        name: '"mt-button" wrong ghost-danger prop usage in variant [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-button variant="ghost-danger">Hello</mt-button>
            </template>`,
        errors: [{
            message: '[mt-button] The "variant" prop with value "ghost-danger" is deprecated. Please use the "critical" prop in combination with "ghost" prop instead.',
        }]
    },
    {
        name: '"mt-button" wrong contrast prop usage in variant',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-button variant="contrast">Hello</mt-button>
            </template>`,
        output: `
            <template>
                <mt-button variant="TODO-Codemod-Variant-Contrast-Was-Removed">Hello</mt-button>
            </template>`,
        errors: [{
            message: '[mt-button] The "variant" prop with value "contrast" is deprecated without replacement.',
        }]
    },
    {
        name: '"mt-button" wrong contrast prop usage in variant [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-button variant="contrast">Hello</mt-button>
            </template>`,
        errors: [{
            message: '[mt-button] The "variant" prop with value "contrast" is deprecated without replacement.',
        }]
    },
    {
        name: '"mt-button" wrong contrast prop usage in variant [indented]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-button
                    variant="contrast"
                >
                    Hello
                </mt-button>
            </template>`,
        output: `
            <template>
                <mt-button
                    variant="TODO-Codemod-Variant-Contrast-Was-Removed"
                >
                    Hello
                </mt-button>
            </template>`,
        errors: [{
            message: '[mt-button] The "variant" prop with value "contrast" is deprecated without replacement.',
        }]
    },
    {
        name: '"mt-button" wrong context prop usage in variant',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-button variant="context">Hello</mt-button>
            </template>`,
        output: `
            <template>
                <mt-button variant="TODO-Codemod-Variant-Context-Was-Removed">Hello</mt-button>
            </template>`,
        errors: [{
            message: '[mt-button] The "variant" prop with value "context" is deprecated without replacement.',
        }]
    },
    {
        name: '"mt-button" wrong context prop usage in variant [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-button variant="context">Hello</mt-button>
            </template>`,
        errors: [{
            message: '[mt-button] The "variant" prop with value "context" is deprecated without replacement.',
        }]
    },
    {
        name: '"mt-button" deprecated usage of "router-link" prop [string usage]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-button router-link="sw.example.link">Hello</mt-button>
            </template>`,
        output: `
            <template>
                <mt-button @click="$router.push('sw.example.link')">Hello</mt-button>
            </template>`,
        errors: [{
            message: '[mt-button] The "router-link" prop is deprecated without replacement.',
        }]
    },
    {
        name: '"mt-button" deprecated usage of "router-link" prop [string usage, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-button router-link="sw.example.link">Hello</mt-button>
            </template>`,
        errors: [{
            message: '[mt-button] The "router-link" prop is deprecated without replacement.',
        }]
    },
    {
        name: '"mt-button" deprecated usage of "router-link" prop [string usage with indents]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-button
                    router-link="sw.example.link"
                >
                    Hello
                </mt-button>
            </template>`,
        output: `
            <template>
                <mt-button
                    @click="$router.push('sw.example.link')"
                >
                    Hello
                </mt-button>
            </template>`,
        errors: [{
            message: '[mt-button] The "router-link" prop is deprecated without replacement.',
        }]
    },
    {
        name: '"mt-button" deprecated usage of "router-link" prop [bind usage]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-button :router-link="{ name: 'sw.example.link' }">Hello</mt-button>
            </template>`,
        output: `
            <template>
                <mt-button @click="$router.push({ name: 'sw.example.link' })">Hello</mt-button>
            </template>`,
        errors: [{
            message: '[mt-button] The "router-link" prop is deprecated without replacement.',
        }]
    },
    {
        name: '"mt-button" deprecated usage of "router-link" prop [bind usage, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-button :router-link="{ name: 'sw.example.link' }">Hello</mt-button>
            </template>`,
        errors: [{
            message: '[mt-button] The "router-link" prop is deprecated without replacement.',
        }]
    },
    {
        name: '"mt-button" deprecated usage of "router-link" prop [bind usage with indents]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-button
                    :router-link="{ name: 'sw.example.link' }"
                >
                    Hello
                </mt-button>
            </template>`,
        output: `
            <template>
                <mt-button
                    @click="$router.push({ name: 'sw.example.link' })"
                >
                    Hello
                </mt-button>
            </template>`,
        errors: [{
            message: '[mt-button] The "router-link" prop is deprecated without replacement.',
        }]
    }
];

module.exports = {
    mtButtonValidChecks,
    mtButtonInvalidChecks,
    handleMtButton
};
