const mtNumberFieldValidTests = [
    {
        name: '"sw-number-field" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-number-field />
            </template>`
    },
]

const mtNumberFieldInvalidTests = [
    {
        name: '"mt-number-field" wrong "value" prop usage should be replaced with "model-value"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-number-field value="5" />
            </template>`,
        output: `
            <template>
                <mt-number-field model-value="5" />
            </template>`,
        errors: [{
            message: '[mt-number-field] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-number-field" wrong "value" prop usage should be replaced with "model-value" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-number-field value="5" />
            </template>`,
        errors: [{
            message: '[mt-number-field] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-number-field" wrong "value" prop usage should be replaced with "model-value" [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-number-field :value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-number-field :model-value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-number-field] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-number-field" wrong "value" prop usage should be replaced with "model-value" [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-number-field :value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-number-field] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-number-field" wrong "v-model:value" usage should be replaced with default v-model',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-number-field v-model:value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-number-field v-model="myValue" />
            </template>`,
        errors: [{
            message: '[mt-number-field] The "v-model:value" binding is deprecated. Use "v-model" instead.',
        }]
    },
    {
        name: '"mt-number-field" wrong "v-model:value" usage should be replaced with default v-model [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-number-field v-model:value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-number-field] The "v-model:value" binding is deprecated. Use "v-model" instead.',
        }]
    },
    {
        name: '"mt-number-field" wrong "label" slot usage should be removed [shorthand syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-number-field>
                    <template #label>
                        My Label
                    </template>
                </mt-number-field>
            </template>`,
        output: `
            <template>
                <mt-number-field label="My Label">
                </mt-number-field>
            </template>`,
        errors: [{
            message: '[mt-number-field] The "label" slot is removed. Pass its content as the "label" prop.',
        }]
    },
    {
        name: '"mt-number-field" wrong "label" slot usage should be removed [disableFix, shorthand syntax]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-number-field>
                    <template #label>
                        My Label
                    </template>
                </mt-number-field>
            </template>`,
        errors: [{
            message: '[mt-number-field] The "label" slot is removed. Pass its content as the "label" prop.',
        }]
    },
    {
        name: '"mt-number-field" wrong "label" slot usage should be removed [v-slot syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-number-field>
                    <template v-slot:label>
                        My Label
                    </template>
                </mt-number-field>
            </template>`,
        output: `
            <template>
                <mt-number-field label="My Label">
                </mt-number-field>
            </template>`,
        errors: [{
            message: '[mt-number-field] The "label" slot is removed. Pass its content as the "label" prop.',
        }]
    },
    {
        name: '"mt-number-field" wrong "label" slot usage should be removed [v-slot syntax, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-number-field>
                    <template v-slot:label>
                        My Label
                    </template>
                </mt-number-field>
            </template>`,
        errors: [{
            message: '[mt-number-field] The "label" slot is removed. Pass its content as the "label" prop.',
        }]
    },
    {
        name: '"mt-number-field" wrong "update:value" event usage should be replaced with "update:mode-value"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-number-field @update:value="updateValue" />
            </template>`,
        output: `
            <template>
                <mt-number-field @update:model-value="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-number-field] The "update:value" API is deprecated. Use "update:model-value" instead.',
        }],
    },
    {
        name: '"mt-number-field" wrong "update:value" event usage should be replaced with "update:mode-value" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-number-field @update:value="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-number-field] The "update:value" API is deprecated. Use "update:model-value" instead.',
        }],
    },
    {
        name: '"mt-number-field" "medium" size is replaced with "default"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-number-field size="medium" />
            </template>`,
        output: `
            <template>
                <mt-number-field size="default" />
            </template>`,
        errors: [{
            message: '[mt-number-field] The "size" API is deprecated. Use "default" instead.',
        }]
    },
];

module.exports = {
    valid: mtNumberFieldValidTests,
    invalid: mtNumberFieldInvalidTests,
};
