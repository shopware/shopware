const mtProgressBarValidTests = [
    {
        name: '"sw-progress-bar" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-progress-bar />
            </template>`
    },
    {
        name: '"mt-progress-bar" without deprecated value API is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-progress-bar model-value="Hello World" :max-value="100" />
            </template>`
    },
    {
        name: '"mt-progress-bar" with "max-value" is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-progress-bar :model-value="done" max-value="10" />
                <mt-progress-bar :model-value="done" :maxValue="total" />
            </template>`
    },
];

const mtProgressBarInvalidTests = [
    {
        name: '"mt-progress-bar" wrong "value" prop usage should be replaced with "model-value"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-progress-bar value="Hello World" :max-value="100" />
            </template>`,
        output: `
            <template>
                <mt-progress-bar model-value="Hello World" :max-value="100" />
            </template>`,
        errors: [{
            message: '[mt-progress-bar] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-progress-bar" wrong "value" prop usage should be replaced with "model-value" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-progress-bar value="Hello World" :max-value="100" />
            </template>`,
        errors: [{
            message: '[mt-progress-bar] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-progress-bar" wrong "value" prop usage should be replaced with "model-value" [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-progress-bar :value="myValue" :max-value="100" />
            </template>`,
        output: `
            <template>
                <mt-progress-bar :model-value="myValue" :max-value="100" />
            </template>`,
        errors: [{
            message: '[mt-progress-bar] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-progress-bar" wrong "value" prop usage should be replaced with "model-value" [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-progress-bar :value="myValue" :max-value="100" />
            </template>`,
        errors: [{
            message: '[mt-progress-bar] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-progress-bar" wrong "v-model:value" usage should be replaced with default v-model',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-progress-bar v-model:value="myValue" :max-value="100" />
            </template>`,
        output: `
            <template>
                <mt-progress-bar v-model="myValue" :max-value="100" />
            </template>`,
        errors: [{
            message: '[mt-progress-bar] The "v-model:value" binding is deprecated. Use "v-model" instead.',
        }]
    },
    {
        name: '"mt-progress-bar" wrong "v-model:value" usage should be replaced with default v-model [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-progress-bar v-model:value="myValue" :max-value="100" />
            </template>`,
        errors: [{
            message: '[mt-progress-bar] The "v-model:value" binding is deprecated. Use "v-model" instead.',
        }]
    },
    {
        name: '"mt-progress-bar" wrong "update:value" event usage should be replaced with "update:mode-value"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-progress-bar @update:value="updateValue" :max-value="100" />
            </template>`,
        output: `
            <template>
                <mt-progress-bar @update:model-value="updateValue" :max-value="100" />
            </template>`,
        errors: [{
            message: '[mt-progress-bar] The "update:value" API is deprecated. Use "update:model-value" instead.',
        }],
    },
    {
        name: '"mt-progress-bar" wrong "update:value" event usage should be replaced with "update:mode-value" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-progress-bar @update:value="updateValue" :max-value="100" />
            </template>`,
        errors: [{
            message: '[mt-progress-bar] The "update:value" API is deprecated. Use "update:model-value" instead.',
        }],
    },
    {
        name: '"mt-progress-bar" without "max-value" gets the previous default of 100',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-progress-bar :value="done" />
                <mt-progress-bar></mt-progress-bar>
            </template>`,
        output: `
            <template>
                <mt-progress-bar :model-value="done" :max-value="100" />
                <mt-progress-bar :max-value="100"></mt-progress-bar>
            </template>`,
        errors: [
            { message: '[mt-progress-bar] The "max-value" prop has no default anymore. Set it to 100 to keep the previous behavior.' },
            { message: '[mt-progress-bar] The "value" API is deprecated. Use "model-value" instead.' },
            { message: '[mt-progress-bar] The "max-value" prop has no default anymore. Set it to 100 to keep the previous behavior.' },
        ],
    },
    {
        name: '"mt-progress-bar" without "max-value" gets the previous default of 100 [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-progress-bar :model-value="done" />
            </template>`,
        errors: [
            { message: '[mt-progress-bar] The "max-value" prop has no default anymore. Set it to 100 to keep the previous behavior.' },
        ],
    },
];

module.exports = {
    valid: mtProgressBarValidTests,
    invalid: mtProgressBarInvalidTests,
};
