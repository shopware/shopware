const mtUrlFieldValidTests = [
    {
        name: '"sw-url-field" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-url-field />
            </template>`
    }
]

const mtUrlFieldInvalidTests = [
    {
        name: '"mt-url-field" wrong "value" prop usage should be replaced with "model-value"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-url-field value="Hello World" />
            </template>`,
        output: `
            <template>
                <mt-url-field model-value="Hello World" />
            </template>`,
        errors: [{
            message: '[mt-url-field] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-url-field" wrong "value" prop usage should be replaced with "model-value" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-url-field value="Hello World" />
            </template>`,
        errors: [{
            message: '[mt-url-field] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-url-field" wrong "value" prop usage should be replaced with "model-value" [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-url-field :value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-url-field :model-value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-url-field] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-url-field" wrong "value" prop usage should be replaced with "model-value" [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-url-field :value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-url-field] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-url-field" wrong "v-model:value" usage should be replaced with default v-model',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-url-field v-model:value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-url-field v-model="myValue" />
            </template>`,
        errors: [{
            message: '[mt-url-field] The "v-model:value" binding is deprecated. Use "v-model" instead.',
        }]
    },
    {
        name: '"mt-url-field" wrong "v-model:value" usage should be replaced with default v-model [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-url-field v-model:value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-url-field] The "v-model:value" binding is deprecated. Use "v-model" instead.',
        }]
    },
    {
        name: '"mt-url-field" wrong "size" prop "medium" usage should be replaced with "default"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-url-field size="medium" />
            </template>`,
        output: `
            <template>
                <mt-url-field size="default" />
            </template>`,
        errors: [{
            message: '[mt-url-field] The "size" API is deprecated. Use "default" instead.',
        }]
    },
    {
        name: '"mt-url-field" wrong "size" prop usage should be replaced with "default" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-url-field size="medium" />
            </template>`,
        errors: [{
            message: '[mt-url-field] The "size" API is deprecated. Use "default" instead.',
        }]
    },
    {
        name: '"mt-url-field" wrong "isInvalid" prop usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-url-field isInvalid />
            </template>`,
        output: `
            <template>
                <mt-url-field />
            </template>`,
        errors: [{
            message: '[mt-url-field] The "is-invalid" API is deprecated.',
        }]
    },
    {
        name: '"mt-url-field" wrong "isInvalid" prop usage should be removed [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-url-field isInvalid />
            </template>`,
        errors: [{
            message: '[mt-url-field] The "is-invalid" API is deprecated.',
        }]
    },
    {
        name: '"mt-url-field" wrong "isInvalid" prop expression usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-url-field :isInvalid="1 == 1" />
            </template>`,
        output: `
            <template>
                <mt-url-field />
            </template>`,
        errors: [{
            message: '[mt-url-field] The "is-invalid" API is deprecated.',
        }]
    },
    {
        name: '"mt-url-field" wrong "isInvalid" prop expression usage should be removed [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-url-field :isInvalid="1 == 1" />
            </template>`,
        errors: [{
            message: '[mt-url-field] The "is-invalid" API is deprecated.',
        }]
    },
    {
        name: '"mt-url-field" wrong "update:value" event usage should be replaced with "update:mode-value"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-url-field @update:value="updateValue" />
            </template>`,
        output: `
            <template>
                <mt-url-field @update:model-value="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-url-field] The "update:value" API is deprecated. Use "update:model-value" instead.',
        }],
    },
    {
        name: '"mt-url-field" wrong "update:value" event usage should be replaced with "update:mode-value" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-url-field @update:value="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-url-field] The "update:value" API is deprecated. Use "update:model-value" instead.',
        }],
    },
    {
        name: '"mt-url-field" wrong "label" slot usage should be removed [shorthand syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-url-field>
                    <template #label>
                        My Label
                    </template>
                </mt-url-field>
            </template>`,
        output: `
            <template>
                <mt-url-field label="My Label">
                </mt-url-field>
            </template>`,
        errors: [{
            message: '[mt-url-field] The "label" slot is removed. Pass its content as the "label" prop.',
        }]
    },
    {
        name: '"mt-url-field" wrong "label" slot usage should be removed [disableFix, shorthand syntax]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-url-field>
                    <template #label>
                        My Label
                    </template>
                </mt-url-field>
            </template>`,
        errors: [{
            message: '[mt-url-field] The "label" slot is removed. Pass its content as the "label" prop.',
        }]
    },
    {
        name: '"mt-url-field" wrong "label" slot usage should be removed [v-slot syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-url-field>
                    <template v-slot:label>
                        My Label
                    </template>
                </mt-url-field>
            </template>`,
        output: `
            <template>
                <mt-url-field label="My Label">
                </mt-url-field>
            </template>`,
        errors: [{
            message: '[mt-url-field] The "label" slot is removed. Pass its content as the "label" prop.',
        }]
    },
    {
        name: '"mt-url-field" wrong "label" slot usage should be removed [v-slot syntax, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-url-field>
                    <template v-slot:label>
                        My Label
                    </template>
                </mt-url-field>
            </template>`,
        errors: [{
            message: '[mt-url-field] The "label" slot is removed. Pass its content as the "label" prop.',
        }]
    },
];

const keptHintSlot = {
    name: '"mt-url-field" keeps its "hint" slot, which mt-url-field still supports',
    filename: 'test.html.twig',
    code: `
            <template>
                <mt-url-field>
                    <template #hint>
                        My Hint
                    </template>
                </mt-url-field>
            </template>`,
};

module.exports = {
    valid: [...mtUrlFieldValidTests, keptHintSlot],
    invalid: mtUrlFieldInvalidTests,
};
