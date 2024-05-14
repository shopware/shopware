/** @param {RuleContext} context
 *  @param {VElement} node
 */
const handleMtIcon = (context, node) => {
    const mtComponentName = 'mt-icon';

    // Refactor the old usage of mt-icon to mt-icon after the migration to the new component
    if (node.name !== mtComponentName) {
        return;
    }

    const attributes = node.startTag.attributes;
    const smallAttribute = attributes.find((attr) => {
        return attr?.key?.name === 'small';
    });
    const smallAttributeExpression = attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' &&
            attr?.key?.argument?.name === 'small'
    });
    const largeAttribute = attributes.find((attr) => {
        return attr?.key?.name === 'large';
    });
    const largeAttributeExpression = attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' &&
            attr?.key?.argument?.name === 'large'
    });
    const sizeAttribute = attributes.find((attr) => {
        return attr?.key?.name === 'size';
    });
    const sizeAttributeExpression = attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' &&
            attr?.key?.argument?.name === 'size'
    });

    // Handle the old attribute "small" of mt-icon and replace it with "size" of value "16px"
    if (smallAttribute && !(sizeAttribute || sizeAttributeExpression)) {
        context.report({
            node,
            message: '[mt-icon] The "small" prop is deprecated. Please use the "size" prop with value "16px" instead.',
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.insertTextAfterRange(smallAttribute.range, 'size="16px"');
                yield fixer.removeRange(smallAttribute.range);
            }
        });
    } else if (smallAttribute && (sizeAttributeExpression || sizeAttribute)) {
        context.report({
            node,
            message: '[mt-icon] The "small" prop is deprecated. Please use the "size" prop with value "16px" instead.',
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.removeRange(smallAttribute.range);
            }
        });
    }

    // Handle the old attribute expression "small" of mt-icon and replace it with "size" of value "16px"
    if (smallAttributeExpression && !(sizeAttribute || sizeAttributeExpression)) {
        context.report({
            node,
            message: '[mt-icon] The "small" prop is deprecated. Please use the "size" prop with value "16px" instead.',
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.insertTextAfterRange(smallAttributeExpression.range, 'size="16px"');
                yield fixer.removeRange(smallAttributeExpression.range);
            }
        });
    } else if (smallAttributeExpression && (sizeAttributeExpression || sizeAttribute)) {
        context.report({
            node,
            message: '[mt-icon] The "small" prop is deprecated. Please use the "size" prop with value "16px" instead.',
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.removeRange(smallAttributeExpression.range);
            }
        });
    }

    // Handle the old attribute "large" of mt-icon and replace it with "size" of value "32px"
    if (largeAttribute && !(sizeAttribute || sizeAttributeExpression)) {
        context.report({
            node,
            message: '[mt-icon] The "large" prop is deprecated. Please use the "size" prop with value "32px" instead.',
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.insertTextAfterRange(largeAttribute.range, 'size="32px"');
                yield fixer.removeRange(largeAttribute.range);
            }
        });
    } else if (largeAttribute && (sizeAttributeExpression || sizeAttribute)) {
        context.report({
            node,
            message: '[mt-icon] The "large" prop is deprecated. Please use the "size" prop with value "32px" instead.',
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.removeRange(largeAttribute.range);
            }
        });
    }

    // Handle the old attribute expression "large" of mt-icon and replace it with "size" of value "32px"
    if (largeAttributeExpression && !(sizeAttribute || sizeAttributeExpression)) {
        context.report({
            node,
            message: '[mt-icon] The "large" prop is deprecated. Please use the "size" prop with value "32px" instead.',
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.insertTextAfterRange(largeAttributeExpression.range, 'size="32px"');
                yield fixer.removeRange(largeAttributeExpression.range);
            }
        });
    } else if (largeAttributeExpression && (sizeAttributeExpression || sizeAttribute)) {
        context.report({
            node,
            message: '[mt-icon] The "large" prop is deprecated. Please use the "size" prop with value "32px" instead.',
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.removeRange(largeAttributeExpression.range);
            }
        });
    }

    // Handle when no size, small or large attribute is set
    if (!(sizeAttribute || sizeAttributeExpression) && !(smallAttribute || smallAttributeExpression) && !(largeAttribute || largeAttributeExpression)) {
        context.report({
            node,
            message: '[mt-icon] The size of the icon is not 24px by default now. Please use the "size" prop with value "24px" to set the size explicitly if needed.',
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                // Handle no attributes
                if (attributes.length === 0) {
                    yield fixer.insertTextAfterRange([
                        node.startTag.range[0],
                        node.startTag.range[0] + mtComponentName.length + 1
                    ], ' size="24px"');
                    return;
                }

                const firstAttribute = attributes[0];
                yield fixer.insertTextAfterRange(firstAttribute.range, ' size="24px"');
            }
        });
    }
}

const mtIconValidTests = [
    {
        name: '"sw-icon" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-icon  name="regular-times-s"/>
            </template>`
    }
]

const mtIconInvalidTests = [
    {
        name: '"mt-icon" wrong "small" prop usage should be replaced with size prop with value 16px',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-icon name="regular-times-s" small />
            </template>`,
        output: `
            <template>
                <mt-icon name="regular-times-s" size="16px" />
            </template>`,
        errors: [{
            message: '[mt-icon] The "small" prop is deprecated. Please use the "size" prop with value "16px" instead.',
        }]
    },
    {
        name: '"mt-icon" wrong "small" prop usage should be replaced with size prop with value 16px [nested]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-icon
                    name="regular-times-s"
                    small
                />
            </template>`,
        output: `
            <template>
                <mt-icon
                    name="regular-times-s"
                    size="16px"
                />
            </template>`,
        errors: [{
            message: '[mt-icon] The "small" prop is deprecated. Please use the "size" prop with value "16px" instead.',
        }]
    },
    {
        name: '"mt-icon" wrong "small" prop usage should be removed when size prop already exists',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-icon name="regular-times-s" small size="32px" />
            </template>`,
        output: `
            <template>
                <mt-icon name="regular-times-s"  size="32px" />
            </template>`,
        errors: [{
            message: '[mt-icon] The "small" prop is deprecated. Please use the "size" prop with value "16px" instead.',
        }]
    },
    {
        name: '"mt-icon" wrong "small" prop usage should be removed when size prop already exists [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-icon name="regular-times-s" small size="32px" />
            </template>`,
        errors: [{
            message: '[mt-icon] The "small" prop is deprecated. Please use the "size" prop with value "16px" instead.',
        }]
    },
    {
        name: '"mt-icon" wrong "small" prop usage should be replaced with size prop with value 16px [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-icon name="regular-times-s" small />
            </template>`,
        errors: [{
            message: '[mt-icon] The "small" prop is deprecated. Please use the "size" prop with value "16px" instead.',
        }]
    },
    {
        name: '"mt-icon" wrong "small" prop with value inside (e.g. "true") usage should be replaced with size prop with value 16px',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-icon name="regular-times-s" :small="true" />
            </template>`,
        output: `
            <template>
                <mt-icon name="regular-times-s" size="16px" />
            </template>`,
        errors: [{
            message: '[mt-icon] The "small" prop is deprecated. Please use the "size" prop with value "16px" instead.',
        }]
    },
    {
        name: '"mt-icon" wrong "small" prop with value inside (e.g. "true") usage should be replaced with size prop with value 16px [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-icon name="regular-times-s" :small="true" />
            </template>`,
        errors: [{
            message: '[mt-icon] The "small" prop is deprecated. Please use the "size" prop with value "16px" instead.',
        }]
    },
    {
        name: '"mt-icon" wrong "small" prop with value inside (e.g. "true") usage should be removed when size prop already exists',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-icon name="regular-times-s" :small="true" size="42px" />
            </template>`,
        output: `
            <template>
                <mt-icon name="regular-times-s"  size="42px" />
            </template>`,
        errors: [{
            message: '[mt-icon] The "small" prop is deprecated. Please use the "size" prop with value "16px" instead.',
        }]
    },
    {
        name: '"mt-icon" wrong "small" prop with value inside (e.g. "true") usage should be removed when size prop already exists [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-icon name="regular-times-s" :small="true" size="42px" />
            </template>`,
        errors: [{
            message: '[mt-icon] The "small" prop is deprecated. Please use the "size" prop with value "16px" instead.',
        }]
    },
    {
        name: '"mt-icon" wrong "large" prop usage should be replaced with size prop with value 32px',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-icon name="regular-times-s" large />
            </template>`,
        output: `
            <template>
                <mt-icon name="regular-times-s" size="32px" />
            </template>`,
        errors: [{
            message: '[mt-icon] The "large" prop is deprecated. Please use the "size" prop with value "32px" instead.',
        }]
    },
    {
        name: '"mt-icon" wrong "large" prop usage should be replaced with size prop with value 32px [nested]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-icon
                    name="regular-times-s"
                    large
                />
            </template>`,
        output: `
            <template>
                <mt-icon
                    name="regular-times-s"
                    size="32px"
                />
            </template>`,
        errors: [{
            message: '[mt-icon] The "large" prop is deprecated. Please use the "size" prop with value "32px" instead.',
        }]
    },
    {
        name: '"mt-icon" wrong "large" prop usage should be removed when size prop already exists',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-icon name="regular-times-s" large size="42px" />
            </template>`,
        output: `
            <template>
                <mt-icon name="regular-times-s"  size="42px" />
            </template>`,
        errors: [{
            message: '[mt-icon] The "large" prop is deprecated. Please use the "size" prop with value "32px" instead.',
        }]
    },
    {
        name: '"mt-icon" wrong "large" prop usage should be removed when size prop already exists [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-icon name="regular-times-s" large size="42px" />
            </template>`,
        errors: [{
            message: '[mt-icon] The "large" prop is deprecated. Please use the "size" prop with value "32px" instead.',
        }]
    },
    {
        name: '"mt-icon" wrong "large" prop usage should be replaced with size prop with value 32px [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-icon name="regular-times-s" large />
            </template>`,
        errors: [{
            message: '[mt-icon] The "large" prop is deprecated. Please use the "size" prop with value "32px" instead.',
        }]
    },
    {
        name: '"mt-icon" wrong "large" prop with value inside (e.g. "true") usage should be replaced with size prop with value 32px',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-icon name="regular-times-s" :large="true" />
            </template>`,
        output: `
            <template>
                <mt-icon name="regular-times-s" size="32px" />
            </template>`,
        errors: [{
            message: '[mt-icon] The "large" prop is deprecated. Please use the "size" prop with value "32px" instead.',
        }]
    },
    {
        name: '"mt-icon" wrong "large" prop with value inside (e.g. "true") usage should be replaced with size prop with value 32px [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-icon name="regular-times-s" :large="true" />
            </template>`,
        errors: [{
            message: '[mt-icon] The "large" prop is deprecated. Please use the "size" prop with value "32px" instead.',
        }]
    },
    {
        name: '"mt-icon" wrong "large" prop with value inside (e.g. "true") usage should be removed when size prop already exists',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-icon name="regular-times-s" :large="true" size="42px" />
            </template>`,
        output: `
            <template>
                <mt-icon name="regular-times-s"  size="42px" />
            </template>`,
        errors: [{
            message: '[mt-icon] The "large" prop is deprecated. Please use the "size" prop with value "32px" instead.',
        }]
    },
    {
        name: '"mt-icon" wrong "large" prop with value inside (e.g. "true") usage should be removed when size prop already exists [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-icon name="regular-times-s" :large="true" size="42px" />
            </template>`,
        errors: [{
            message: '[mt-icon] The "large" prop is deprecated. Please use the "size" prop with value "32px" instead.',
        }]
    },
    {
        name: '"mt-icon" without a size prop should be replaced with size prop with value 24px',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-icon name="regular-times-s" />
            </template>`,
        output: `
            <template>
                <mt-icon name="regular-times-s" size="24px" />
            </template>`,
        errors: [{
            message: '[mt-icon] The size of the icon is not 24px by default now. Please use the "size" prop with value "24px" to set the size explicitly if needed.',
        }]
    },
    {
        name: '"mt-icon" without a size prop should be replaced with size prop with value 24px [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-icon name="regular-times-s" />
            </template>`,
        errors: [{
            message: '[mt-icon] The size of the icon is not 24px by default now. Please use the "size" prop with value "24px" to set the size explicitly if needed.',
        }]
    },
    {
        name: '"mt-icon" without a size prop should be replaced with size prop with value 24px [nested]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-icon
                    name="regular-times-s"
                />
            </template>`,
        output: `
            <template>
                <mt-icon
                    name="regular-times-s" size="24px"
                />
            </template>`,
        errors: [{
            message: '[mt-icon] The size of the icon is not 24px by default now. Please use the "size" prop with value "24px" to set the size explicitly if needed.',
        }]
    },
    {
        name: '"mt-icon" without a size prop should be replaced with size prop with value 24px [nested, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-icon
                    name="regular-times-s"
                />
            </template>`,
        errors: [{
            message: '[mt-icon] The size of the icon is not 24px by default now. Please use the "size" prop with value "24px" to set the size explicitly if needed.',
        }]
    },
    {
        name: '"mt-icon" without a size prop should be replaced with size prop with value 24px [no other attributes]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-icon />
            </template>`,
        output: `
            <template>
                <mt-icon size="24px" />
            </template>`,
        errors: [{
            message: '[mt-icon] The size of the icon is not 24px by default now. Please use the "size" prop with value "24px" to set the size explicitly if needed.',
        }]
    },
    {
        name: '"mt-icon" without a size prop should be replaced with size prop with value 24px [no other attributes, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-icon />
            </template>`,
        errors: [{
            message: '[mt-icon] The size of the icon is not 24px by default now. Please use the "size" prop with value "24px" to set the size explicitly if needed.',
        }]
    },
];

module.exports = {
    handleMtIcon,
    mtIconValidTests,
    mtIconInvalidTests
};
