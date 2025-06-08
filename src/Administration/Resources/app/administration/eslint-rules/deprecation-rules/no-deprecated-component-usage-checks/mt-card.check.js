/** @param {RuleContext} context
 *  @param {VElement} node
 */
const handleMtCard = (context, node) => {
    const mtComponentName = 'mt-card';

    // Refactor the old usage of mt-card to mt-card after the migration to the new component
    if (node.name !== mtComponentName) {
        return;
    }

    const attributes = node.startTag.attributes;

    // ai-badge attribute
    const aiBadgeAttribute = attributes.find((attr) => {
        return attr?.key?.name === 'aiBadge' ||
            attr?.key?.name === 'ai-badge' ||
            attr?.key?.name === 'aibadge'
    });

    const aiBadgeAttributeExpression = attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' &&
            (
                attr?.key?.argument?.name === 'aiBadge' ||
                attr?.key?.argument?.name === 'ai-badge' ||
                attr?.key?.argument?.name === 'aibadge'
            )
    });

    // content-padding attribute
    const contentPaddingAttribute = attributes.find((attr) => {
        return attr?.key?.name === 'contentPadding' ||
            attr?.key?.name === 'content-padding' ||
            attr?.key?.name === 'contentpadding'
    });

    const contentPaddingAttributeExpression = attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' &&
            (
                attr?.key?.argument?.name === 'contentPadding' ||
                attr?.key?.argument?.name === 'content-padding' ||
                attr?.key?.argument?.name === 'contentpadding'
            )
    });

    // When the ai-badge attribute is used, it should be removed and the AI badge should be used directly in the slot
    if (aiBadgeAttribute) {
        context.report({
            node,
            loc: node.loc,
            message: `[${mtComponentName}] The "ai-badge" prop is deprecated. Please use the AI badge directly in the slot.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                // Remove the ai-badge attribute
                yield fixer.remove(aiBadgeAttribute);
                // Save indent of the node start tag for the new slot
                const indent = node.startTag.loc.start.column + 4;
                // Add the AI badge in the "title" slot
                yield fixer.insertTextAfter(
                    node.startTag,
                    `\n${' '.repeat(indent)}<slot name="title"><sw-ai-copilot-badge /></slot>\n${' '.repeat(indent)}`
                );
            }
        });
    }

    // When the ai-badge attribute is used with bind, it should be removed and the AI badge should be used directly in the slot
    if (aiBadgeAttributeExpression) {
        context.report({
            node,
            loc: node.loc,
            message: `[${mtComponentName}] The "ai-badge" prop is deprecated. Please use the AI badge directly in the slot.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                // Remove the ai-badge attribute
                yield fixer.remove(aiBadgeAttributeExpression);
                // Save indent of the node start tag for the new slot
                const indent = node.startTag.loc.start.column + 4;
                // Save the expression of the ai-badge attribute
                const sourceCode = context.getSourceCode();
                const aiBadgeAttributeExpressionTextRaw = sourceCode.getText(aiBadgeAttributeExpression.value);
                // Remove the quotes at the beginning and end of the expression when they are present
                const aiBadgeAttributeExpressionText = aiBadgeAttributeExpressionTextRaw.replace(/^['"]|['"]$/g, '');
                // Add the AI badge in the "title" slot
                yield fixer.insertTextAfter(
                    node.startTag,
                    `\n${' '.repeat(indent)}<slot name="title"><sw-ai-copilot-badge v-if="${aiBadgeAttributeExpressionText}" /></slot>\n${' '.repeat(indent)}`
                );
            }
        });
    }

    // When the content-padding attribute is used, it should be removed
    if (contentPaddingAttribute) {
        context.report({
            node,
            loc: node.loc,
            message: `[${mtComponentName}] The "content-padding" prop was removed.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                // Remove the content-padding attribute
                yield fixer.remove(contentPaddingAttribute);
            }
        });
    }

    // When the content-padding attribute is used with bind, it should be removed
    if (contentPaddingAttributeExpression) {
        context.report({
            node,
            loc: node.loc,
            message: `[${mtComponentName}] The "content-padding" prop was removed.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                // Remove the content-padding attribute
                yield fixer.remove(contentPaddingAttributeExpression);
            }
        });
    }
}

const mtCardValidTests = [
    {
        name: '"sw-card" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-card>Hello World</sw-card>
            </template>`
    }
]

const mtCardInvalidTests = [
    {
        name: '"mt-card" wrong "ai-badge" attribute usage',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-card ai-badge>Hello World</mt-card>
            </template>`,
        output: `
            <template>
                <mt-card >
                    <slot name="title"><sw-ai-copilot-badge /></slot>
                    Hello World</mt-card>
            </template>`,
        errors: [
            {
                message: '[mt-card] The "ai-badge" prop is deprecated. Please use the AI badge directly in the slot.',
            }
        ]
    },
    {
        name: '"mt-card" wrong "ai-badge" attribute usage [disable fix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-card ai-badge>Hello World</mt-card>
            </template>`,
        errors: [
            {
                message: '[mt-card] The "ai-badge" prop is deprecated. Please use the AI badge directly in the slot.',
            }
        ]
    },
    {
        name: '"mt-card" wrong "ai-badge" attribute usage [with bind]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-card :ai-badge="1 == 1">Hello World</mt-card>
            </template>`,
        output: `
            <template>
                <mt-card >
                    <slot name="title"><sw-ai-copilot-badge v-if="1 == 1" /></slot>
                    Hello World</mt-card>
            </template>`,
        errors: [
            {
                message: '[mt-card] The "ai-badge" prop is deprecated. Please use the AI badge directly in the slot.',
            }
        ]
    },
    {
        name: '"mt-card" wrong "ai-badge" attribute usage [with bind, disable fix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-card :ai-badge="1 == 1">Hello World</mt-card>
            </template>`,
        errors: [
            {
                message: '[mt-card] The "ai-badge" prop is deprecated. Please use the AI badge directly in the slot.',
            }
        ]
    },
    {
        name: '"mt-card" wrong "content-padding" attribute usage',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-card content-padding>Hello World</mt-card>
            </template>`,
        output: `
            <template>
                <mt-card >Hello World</mt-card>
            </template>`,
        errors: [
            {
                message: '[mt-card] The "content-padding" prop was removed.',
            }
        ]
    },
    {
        name: '"mt-card" wrong "content-padding" attribute usage [disable fix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-card content-padding>Hello World</mt-card>
            </template>`,
        errors: [
            {
                message: '[mt-card] The "content-padding" prop was removed.',
            }
        ]
    },
    {
        name: '"mt-card" wrong "content-padding" attribute usage [with bind]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-card :content-padding="1 == 1">Hello World</mt-card>
            </template>`,
        output: `
            <template>
                <mt-card >Hello World</mt-card>
            </template>`,
        errors: [
            {
                message: '[mt-card] The "content-padding" prop was removed.',
            }
        ]
    },
    {
        name: '"mt-card" wrong "content-padding" attribute usage [with bind]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-card :content-padding="1 == 1">Hello World</mt-card>
            </template>`,
        output: `
            <template>
                <mt-card >Hello World</mt-card>
            </template>`,
        errors: [
            {
                message: '[mt-card] The "content-padding" prop was removed.',
            }
        ]
    },
    {
        name: '"mt-card" wrong "content-padding" attribute usage [with bind, disable fix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-card :content-padding="1 == 1">Hello World</mt-card>
            </template>`,
        errors: [
            {
                message: '[mt-card] The "content-padding" prop was removed.',
            }
        ]
    }
];

module.exports = {
    handleMtCard,
    mtCardValidTests,
    mtCardInvalidTests
};
