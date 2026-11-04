const mtPasswordFieldValidTests = [
    {
        name: '"sw-password-field" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-password-field />
            </template>`
    },
    {
        name: '"mt-password-field" keeps its native "hint" slot',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-password-field><template #hint>My Hint</template></mt-password-field>
            </template>`
    }
]

const mtPasswordFieldInvalidTests = [
    {
        name: '"mt-password-field" wrong "value" prop usage should be replaced with "model-value"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-password-field value="Hello World" />
            </template>`,
        output: `
            <template>
                <mt-password-field model-value="Hello World" />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-password-field" wrong "value" prop usage should be replaced with "model-value" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-password-field value="Hello World" />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-password-field" wrong "value" prop usage should be replaced with "model-value" [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-password-field :value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-password-field :model-value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-password-field" wrong "value" prop usage should be replaced with "model-value" [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-password-field :value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-password-field" wrong "v-model:value" usage should be replaced with default v-model',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-password-field v-model:value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-password-field v-model="myValue" />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "value" API is deprecated.',
        }]
    },
    {
        name: '"mt-password-field" wrong "v-model:value" usage should be replaced with default v-model [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-password-field v-model:value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "value" API is deprecated.',
        }]
    },
    {
        name: '"mt-password-field" wrong "size" prop "medium" usage should be replaced with "default"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-password-field size="medium" />
            </template>`,
        output: `
            <template>
                <mt-password-field size="default" />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "size" API is deprecated. Use "default" instead.',
        }]
    },
    {
        name: '"mt-password-field" wrong "size" prop usage should be replaced with "default" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-password-field size="medium" />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "size" API is deprecated. Use "default" instead.',
        }]
    },
    {
        name: '"mt-password-field" wrong "isInvalid" prop usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-password-field isInvalid />
            </template>`,
        output: `
            <template>
                <mt-password-field  />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "is-invalid" API is deprecated.',
        }]
    },
    {
        name: '"mt-password-field" wrong "isInvalid" prop usage should be removed [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-password-field isInvalid />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "is-invalid" API is deprecated.',
        }]
    },
    {
        name: '"mt-password-field" wrong "isInvalid" prop expression usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-password-field :isInvalid="1 == 1" />
            </template>`,
        output: `
            <template>
                <mt-password-field  />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "is-invalid" API is deprecated.',
        }]
    },
    {
        name: '"mt-password-field" wrong "isInvalid" prop expression usage should be removed [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-password-field :isInvalid="1 == 1" />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "is-invalid" API is deprecated.',
        }]
    },
    {
        name: '"mt-password-field" wrong "update:value" event usage should be replaced with "update:mode-value"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-password-field @update:value="updateValue" />
            </template>`,
        output: `
            <template>
                <mt-password-field @update:model-value="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "update:value" API is deprecated. Use "update:model-value" instead.',
        }],
    },
    {
        name: '"mt-password-field" wrong "update:value" event usage should be replaced with "update:mode-value" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-password-field @update:value="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "update:value" API is deprecated. Use "update:model-value" instead.',
        }],
    },
    {
        name: '"mt-password-field" wrong "base-field-mounted" event usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-password-field @base-field-mounted="onFieldMounted" />
            </template>`,
        output: `
            <template>
                <mt-password-field  />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "base-field-mounted" API is deprecated.',
        }]
    },
    {
        name: '"mt-password-field" wrong "base-field-mounted" event usage should be removed [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-password-field @base-field-mounted="onFieldMounted" />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "base-field-mounted" API is deprecated.',
        }]
    },
    {
        name: '"mt-password-field" wrong "label" slot usage should be removed [shorthand syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-password-field>
                    <template #label>
                        My Label
                    </template>
                </mt-password-field>
            </template>`,
        output: `
            <template>
                <mt-password-field label="My Label">
                </mt-password-field>
            </template>`,
        errors: [{
            message: '[mt-password-field] The "label" slot is removed. Pass its content as the "label" prop.',
        }]
    },
    {
        name: '"mt-password-field" wrong "label" slot usage should be removed [disableFix, shorthand syntax]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-password-field>
                    <template #label>
                        My Label
                    </template>
                </mt-password-field>
            </template>`,
        errors: [{
            message: '[mt-password-field] The "label" slot is removed. Pass its content as the "label" prop.',
        }]
    },
    {
        name: '"mt-password-field" wrong "label" slot usage should be removed [v-slot syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-password-field>
                    <template v-slot:label>
                        My Label
                    </template>
                </mt-password-field>
            </template>`,
        output: `
            <template>
                <mt-password-field label="My Label">
                </mt-password-field>
            </template>`,
        errors: [{
            message: '[mt-password-field] The "label" slot is removed. Pass its content as the "label" prop.',
        }]
    },
    {
        name: '"mt-password-field" wrong "label" slot usage should be removed [v-slot syntax, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-password-field>
                    <template v-slot:label>
                        My Label
                    </template>
                </mt-password-field>
            </template>`,
        errors: [{
            message: '[mt-password-field] The "label" slot is removed. Pass its content as the "label" prop.',
        }]
    }
];

module.exports = {
    valid: mtPasswordFieldValidTests,
    invalid: mtPasswordFieldInvalidTests,
};
