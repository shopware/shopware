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
    mtCardValidTests,
    mtCardInvalidTests
};
