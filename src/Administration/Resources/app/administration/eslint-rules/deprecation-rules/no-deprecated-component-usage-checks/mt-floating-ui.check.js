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
];

module.exports = {
    handleMtFloatingUi,
    mtFloatingUiValidTests,
    mtFloatingUiInvalidTests
};
