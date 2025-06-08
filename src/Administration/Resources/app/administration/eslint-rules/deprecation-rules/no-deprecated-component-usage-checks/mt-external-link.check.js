/** @param {RuleContext} context
 *  @param {VElement} node
 */
const handleMtExternalLink = (context, node) => {
    const mtComponentName = 'mt-external-link';

    // Refactor the old usage of mt-external-link to mt-external-link after the migration to the new component
    if (node.name !== mtComponentName) {
        return;
    }

    // Check if the mt-external-link has the attribute "icon"
    const iconAttribute = node.startTag.attributes.find((attr) => {
        return attr.key.name === 'icon';
    });
    // Check if the mt-external-link has the attribute expression "aiBadge"
    const iconAttributeExpression = node.startTag.attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' && attr?.key?.argument?.name === 'icon';
    });


    if (iconAttribute) {
        context.report({
            node: iconAttribute,
            message: `[${mtComponentName}] The "icon" prop is deprecated. Remove it.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(iconAttribute);
            }
        });
    }

    if (iconAttributeExpression) {
        context.report({
            node: iconAttributeExpression,
            message: `[${mtComponentName}] The "icon" prop is deprecated. Remove it.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(iconAttributeExpression);
            }
        });
    }
}

const mtExternalLinkValidTests = [
    {
        name: '"sw-external-link" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-external-link />
            </template>`
    }
]

const mtExternalLinkInvalidTests = [
    {
        name: '"mt-external-link" wrong "icon" prop usage',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-external-link icon="default-test-icon" />
            </template>`,
        output: `
            <template>
                <mt-external-link  />
            </template>`,
        errors: [
            {
                message: '[mt-external-link] The "icon" prop is deprecated. Remove it.'
            }
        ]
    },
    {
        name: '"mt-external-link" wrong "icon" prop usage [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-external-link icon="default-test-icon" />
            </template>`,
        errors: [
            {
                message: '[mt-external-link] The "icon" prop is deprecated. Remove it.'
            }
        ]
    },
    {
        name: '"mt-external-link" wrong "icon" prop usage [attribute expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-external-link :icon="theIcon" />
            </template>`,
        output: `
            <template>
                <mt-external-link  />
            </template>`,
        errors: [
            {
                message: '[mt-external-link] The "icon" prop is deprecated. Remove it.'
            }
        ]
    },
    {
        name: '"mt-external-link" wrong "icon" prop usage [attribute expression] [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-external-link :icon="theIcon" />
            </template>`,
        errors: [
            {
                message: '[mt-external-link] The "icon" prop is deprecated. Remove it.'
            }
        ]
    }
];

module.exports = {
    handleMtExternalLink,
    mtExternalLinkValidTests,
    mtExternalLinkInvalidTests
};
