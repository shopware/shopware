const mtColorpickerValidTests = [
    {
        name: '"sw-colorpicker" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-colorpicker />
            </template>`
    }
]

const mtColorpickerInvalidTests = [
    {
        name: '"mt-colorpicker" wrong "value" prop usage should be replaced with "model-value"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-colorpicker value="Hello World" />
            </template>`,
        output: `
            <template>
                <mt-colorpicker model-value="Hello World" />
            </template>`,
        errors: [{
            message: '[mt-colorpicker] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-colorpicker" wrong "value" prop usage should be replaced with "model-value" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-colorpicker value="Hello World" />
            </template>`,
        errors: [{
            message: '[mt-colorpicker] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-colorpicker" wrong "value" prop usage should be replaced with "model-value" [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-colorpicker :value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-colorpicker :model-value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-colorpicker] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-colorpicker" wrong "value" prop usage should be replaced with "model-value" [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-colorpicker :value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-colorpicker] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-colorpicker" wrong "v-model:value" usage should be replaced with default v-model',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-colorpicker v-model:value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-colorpicker v-model="myValue" />
            </template>`,
        errors: [{
            message: '[mt-colorpicker] The "value" API is deprecated.',
        }]
    },
    {
        name: '"mt-colorpicker" wrong "v-model:value" usage should be replaced with default v-model [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-colorpicker v-model:value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-colorpicker] The "value" API is deprecated.',
        }]
    },
    {
        name: '"mt-colorpicker" wrong "update:value" event usage should be replaced with "update:mode-value"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-colorpicker @update:value="updateValue" />
            </template>`,
        output: `
            <template>
                <mt-colorpicker @update:model-value="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-colorpicker] The "update:value" API is deprecated. Use "update:model-value" instead.',
        }],
    },
    {
        name: '"mt-colorpicker" wrong "update:value" event usage should be replaced with "update:mode-value" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-colorpicker @update:value="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-colorpicker] The "update:value" API is deprecated. Use "update:model-value" instead.',
        }],
    },
    {
        name: '"mt-colorpicker" wrong "label" slot usage should be removed [shorthand syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-colorpicker>
                    <template #label>
                        My Label
                    </template>
                </mt-colorpicker>
            </template>`,
        output: `
            <template>
                <mt-colorpicker label="My Label">
                </mt-colorpicker>
            </template>`,
        errors: [{
            message: '[mt-colorpicker] The "label" slot is removed. Pass its content as the "label" prop.',
        }]
    },
    {
        name: '"mt-colorpicker" wrong "label" slot usage should be removed [disableFix, shorthand syntax]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-colorpicker>
                    <template #label>
                        My Label
                    </template>
                </mt-colorpicker>
            </template>`,
        errors: [{
            message: '[mt-colorpicker] The "label" slot is removed. Pass its content as the "label" prop.',
        }]
    },
    {
        name: '"mt-colorpicker" wrong "label" slot usage should be removed [v-slot syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-colorpicker>
                    <template v-slot:label>
                        My Label
                    </template>
                </mt-colorpicker>
            </template>`,
        output: `
            <template>
                <mt-colorpicker label="My Label">
                </mt-colorpicker>
            </template>`,
        errors: [{
            message: '[mt-colorpicker] The "label" slot is removed. Pass its content as the "label" prop.',
        }]
    },
    {
        name: '"mt-colorpicker" wrong "label" slot usage should be removed [v-slot syntax, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-colorpicker>
                    <template v-slot:label>
                        My Label
                    </template>
                </mt-colorpicker>
            </template>`,
        errors: [{
            message: '[mt-colorpicker] The "label" slot is removed. Pass its content as the "label" prop.',
        }]
    },
];

module.exports = {
    valid: mtColorpickerValidTests,
    invalid: mtColorpickerInvalidTests,
};
