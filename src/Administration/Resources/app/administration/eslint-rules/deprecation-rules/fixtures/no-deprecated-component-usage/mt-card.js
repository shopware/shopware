const mtCardValidTests = [
    {
        name: '"sw-card" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-card>Hello World</sw-card>
            </template>`
    },
    {
        name: '"mt-card" "headerRight" slot is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-card><template #headerRight>Actions</template></mt-card>
            </template>`
    },
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
                <!-- TODO Codemod: [mt-card] Remove the AI badge prop and render the AI badge directly in the title slot. -->
                <mt-card>
                    <slot name="title"><sw-ai-copilot-badge /></slot>
                    Hello World</mt-card>
            </template>`,
        errors: [
            {
                message: '[mt-card] Remove the AI badge prop and render the AI badge directly in the title slot.',
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
                message: '[mt-card] Remove the AI badge prop and render the AI badge directly in the title slot.',
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
                <!-- TODO Codemod: [mt-card] Remove the AI badge prop and render the AI badge directly in the title slot. -->
                <mt-card>
                    <slot name="title"><sw-ai-copilot-badge v-if="1 == 1" /></slot>
                    Hello World</mt-card>
            </template>`,
        errors: [
            {
                message: '[mt-card] Remove the AI badge prop and render the AI badge directly in the title slot.',
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
                message: '[mt-card] Remove the AI badge prop and render the AI badge directly in the title slot.',
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
                <mt-card>Hello World</mt-card>
            </template>`,
        errors: [
            {
                message: '[mt-card] The "content-padding" API is deprecated.',
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
                message: '[mt-card] The "content-padding" API is deprecated.',
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
                <mt-card>Hello World</mt-card>
            </template>`,
        errors: [
            {
                message: '[mt-card] The "content-padding" API is deprecated.',
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
                message: '[mt-card] The "content-padding" API is deprecated.',
            }
        ]
    },
    {
        name: '"mt-card" "header-right" slot is renamed to "headerRight"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-card>
                    <template #header-right>Actions</template>
                </mt-card>
            </template>`,
        output: `
            <template>
                <mt-card>
                    <template #headerRight>Actions</template>
                </mt-card>
            </template>`,
        errors: [
            {
                message: '[mt-card] The "header-right" slot is renamed. Use "headerRight" instead.',
            }
        ]
    },
    {
        name: '"mt-card" "header-right" slot is renamed to "headerRight" [v-slot syntax, slot props]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-card>
                    <template v-slot:header-right="{ isLoading }">Actions</template>
                </mt-card>
            </template>`,
        output: `
            <template>
                <mt-card>
                    <template v-slot:headerRight="{ isLoading }">Actions</template>
                </mt-card>
            </template>`,
        errors: [
            {
                message: '[mt-card] The "header-right" slot is renamed. Use "headerRight" instead.',
            }
        ]
    },
    {
        name: '"mt-card" "header-right" slot is renamed to "headerRight" [disable fix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-card>
                    <template #header-right>Actions</template>
                </mt-card>
            </template>`,
        errors: [
            {
                message: '[mt-card] The "header-right" slot is renamed. Use "headerRight" instead.',
            }
        ]
    },
    {
        name: '"mt-card" "header-right" slot is not renamed when "headerRight" is already filled',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-card>
                    <template #header-right>Old</template>
                    <template #headerRight>New</template>
                </mt-card>
            </template>`,
        output: null,
        errors: [
            {
                message: '[mt-card] The "header-right" slot is renamed. Use "headerRight" instead.\nThe element already fills the "headerRight" slot. Merge both slots manually.',
            }
        ]
    },
    {
        name: '"mt-card" "hero" prop is reported for a manual migration',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-card hero>Hello World</mt-card>
            </template>`,
        output: null,
        errors: [
            {
                message: '[mt-card] mt-card has no "hero" prop, so the card loses its borderless, centered hero look. Remove the prop and restyle the card manually if needed.',
            }
        ]
    },
    {
        name: '"mt-card" removed attribute on its own line leaves no empty line',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-card
                    title="Title"
                    content-padding
                    position-identifier="card"
                >Hello World</mt-card>
            </template>`,
        output: `
            <template>
                <mt-card
                    title="Title"
                    position-identifier="card"
                >Hello World</mt-card>
            </template>`,
        errors: [
            {
                message: '[mt-card] The "content-padding" API is deprecated.',
            }
        ]
    },
    {
        name: '"mt-card" removed attribute that starts a line keeps the next attribute on it',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-card
                    content-padding title="Title"
                >Hello World</mt-card>
            </template>`,
        output: `
            <template>
                <mt-card
                    title="Title"
                >Hello World</mt-card>
            </template>`,
        errors: [
            {
                message: '[mt-card] The "content-padding" API is deprecated.',
            }
        ]
    },
];

module.exports = {
    valid: mtCardValidTests,
    invalid: mtCardInvalidTests,
};
