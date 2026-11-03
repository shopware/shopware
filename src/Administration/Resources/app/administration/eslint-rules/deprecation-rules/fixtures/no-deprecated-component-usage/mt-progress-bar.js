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
                <mt-progress-bar model-value="Hello World" />
            </template>`
    }
];

const mtProgressBarInvalidTests = [
    {
        name: '"mt-progress-bar" wrong "value" prop usage should be replaced with "model-value"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-progress-bar value="Hello World" />
            </template>`,
        output: `
            <template>
                <mt-progress-bar model-value="Hello World" />
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
                <mt-progress-bar value="Hello World" />
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
                <mt-progress-bar :value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-progress-bar :model-value="myValue" />
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
                <mt-progress-bar :value="myValue" />
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
                <mt-progress-bar v-model:value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-progress-bar v-model="myValue" />
            </template>`,
        errors: [{
            message: '[mt-progress-bar] The "value" API is deprecated.',
        }]
    },
    {
        name: '"mt-progress-bar" wrong "v-model:value" usage should be replaced with default v-model [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-progress-bar v-model:value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-progress-bar] The "value" API is deprecated.',
        }]
    },
    {
        name: '"mt-progress-bar" wrong "update:value" event usage should be replaced with "update:mode-value"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-progress-bar @update:value="updateValue" />
            </template>`,
        output: `
            <template>
                <mt-progress-bar @update:model-value="updateValue" />
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
                <mt-progress-bar @update:value="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-progress-bar] The "update:value" API is deprecated. Use "update:model-value" instead.',
        }],
    },
];

module.exports = {
    valid: mtProgressBarValidTests,
    invalid: mtProgressBarInvalidTests,
};
