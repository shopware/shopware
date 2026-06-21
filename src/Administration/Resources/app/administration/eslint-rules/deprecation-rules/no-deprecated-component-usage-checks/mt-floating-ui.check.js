/** @param {RuleContext} context
 *  @param {VElement} node
 */
const handleMtFloatingUi = (context, node) => {
    const mtComponentName = 'mt-floating-ui';

    // Refactor the old usage of mt-floating-ui to mt-floating-ui after the migration to the new component
    if (node.name !== mtComponentName) {
        return;
    }

    const attributes = node.startTag.attributes;
    const removedProps = [
        'zIndex',
        'z-index',
        'zindex',
        'popoverClass',
        'popover-class',
        'popoverclass',
    ];

    const findAttribute = (names) => {
        return attributes.find((attr) => names.includes(attr?.key?.name));
    };

    const findAttributeExpression = (names) => {
        return attributes.find((attr) => {
            return attr?.key?.name?.name === 'bind' &&
                names.includes(attr?.key?.argument?.name);
        });
    };

    const reportRemovedProp = (attribute, propName) => {
        if (!attribute) {
            return;
        }

        context.report({
            node: attribute,
            message: `[${mtComponentName}] The "${propName}" prop is deprecated. Remove it.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(attribute);
            },
        });
    };

    const resizeWidthAttribute = findAttribute([
        'resizeWidth',
        'resize-width',
        'resizewidth',
    ]);
    const resizeWidthAttributeExpression = findAttributeExpression([
        'resizeWidth',
        'resize-width',
        'resizewidth',
    ]);

    if (resizeWidthAttribute) {
        context.report({
            node: resizeWidthAttribute,
            message: `[${mtComponentName}] The "resize-width" prop is deprecated. Use "match-reference-width" instead.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.replaceText(resizeWidthAttribute.key, 'match-reference-width');
            },
        });
    }

    if (resizeWidthAttributeExpression) {
        context.report({
            node: resizeWidthAttributeExpression,
            message: `[${mtComponentName}] The "resize-width" prop is deprecated. Use "match-reference-width" instead.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.replaceText(resizeWidthAttributeExpression.key.argument, 'match-reference-width');
            },
        });
    }

    removedProps.forEach((propName) => {
        reportRemovedProp(findAttribute([propName]), propName);
        reportRemovedProp(findAttributeExpression([propName]), propName);
    });

    const isOpenedAttribute = attributes.find((attr) => {
        return attr?.key?.name === 'isOpened' ||
            attr?.key?.name === 'is-opened' ||
            attr?.key?.name === 'isopened'
    });

    const isOpenedAttributeExpression = attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' &&
            (
                attr?.key?.argument?.name === 'isOpened' ||
                attr?.key?.argument?.name === 'is-opened' ||
                attr?.key?.argument?.name === 'isopened'
            )
    });

    if (!isOpenedAttribute && !isOpenedAttributeExpression) {
        context.report({
            node,
            message: `[${mtComponentName}] The floating-ui is not opened by default. Please set the "isOpened" prop to "true" to maintain backward compatibility.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                const rangeAfterStartTag = node.startTag?.range[0] + '<mt-floating-ui'.length;
                yield fixer.insertTextAfterRange([rangeAfterStartTag, rangeAfterStartTag], ` :isOpened="true"`);
            }
        });
    }
}

const mtFloatingUiValidTests = [
    {
        name: '"sw-popover" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-popover />
            </template>`
    },
    {
        name: '"mt-floating-ui" set "isOpened" prop to "true" when not exists to maintain backward compatibility',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-floating-ui :isOpened="true" />
            </template>`,
    },
    {
        name: '"mt-floating-ui" set "isOpened" prop to "true" when not exists to maintain backward compatibility',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-floating-ui isOpened="true" />
            </template>`,
    },
    {
        name: '"mt-floating-ui" set "isOpened" prop to "true" when not exists to maintain backward compatibility',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-floating-ui isOpened />
            </template>`,
    }
]

const mtFloatingUiInvalidTests = [
    {
        name: '"mt-floating-ui" set "isOpened" prop to "true" when not exists to maintain backward compatibility',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-floating-ui />
            </template>`,
        output: `
            <template>
                <mt-floating-ui :isOpened="true" />
            </template>`,
        errors: [{
            message: '[mt-floating-ui] The floating-ui is not opened by default. Please set the "isOpened" prop to "true" to maintain backward compatibility.',
        }]
    },
    {
        name: '"mt-floating-ui" set "isOpened" prop to "true" when not exists to maintain backward compatibility',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-floating-ui />
            </template>`,
        errors: [{
            message: '[mt-floating-ui] The floating-ui is not opened by default. Please set the "isOpened" prop to "true" to maintain backward compatibility.',
        }]
    },
    {
        name: '"mt-floating-ui" replaces resize-width with match-reference-width',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-floating-ui resize-width />
            </template>`,
        output: `
            <template>
                <mt-floating-ui :isOpened="true" match-reference-width />
            </template>`,
        errors: [
            {
                message: '[mt-floating-ui] The "resize-width" prop is deprecated. Use "match-reference-width" instead.',
            },
            {
                message: '[mt-floating-ui] The floating-ui is not opened by default. Please set the "isOpened" prop to "true" to maintain backward compatibility.',
            },
        ],
    },
    {
        name: '"mt-floating-ui" removes popover-class',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-floating-ui popover-class="legacy" />
            </template>`,
        output: `
            <template>
                <mt-floating-ui :isOpened="true"  />
            </template>`,
        errors: [
            {
                message: '[mt-floating-ui] The "popover-class" prop is deprecated. Remove it.',
            },
            {
                message: '[mt-floating-ui] The floating-ui is not opened by default. Please set the "isOpened" prop to "true" to maintain backward compatibility.',
            },
        ],
    },
];

module.exports = {
    handleMtFloatingUi,
    mtFloatingUiValidTests,
    mtFloatingUiInvalidTests
};
