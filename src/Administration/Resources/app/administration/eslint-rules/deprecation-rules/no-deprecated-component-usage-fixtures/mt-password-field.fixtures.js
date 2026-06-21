const mtPasswordFieldValidTests = [
    {
        name: '"sw-password-field" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-password-field />
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
            message: '[mt-password-field] The "value" prop is deprecated. Use "model-value" instead.',
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
            message: '[mt-password-field] The "value" prop is deprecated. Use "model-value" instead.',
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
            message: '[mt-password-field] The "value" prop is deprecated. Use "model-value" instead.',
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
            message: '[mt-password-field] The "value" prop is deprecated. Use "model-value" instead.',
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
            message: '[mt-password-field] The "value" prop is deprecated. Use "model-value" instead.',
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
            message: '[mt-password-field] The "value" prop is deprecated. Use "model-value" instead.',
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
            message: '[mt-password-field] The "size" prop value "medium" is deprecated. Use "default" instead.',
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
            message: '[mt-password-field] The "size" prop value "medium" is deprecated. Use "default" instead.',
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
            message: '[mt-password-field] The "isInvalid" prop is deprecated. Remove it.',
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
            message: '[mt-password-field] The "isInvalid" prop is deprecated. Remove it.',
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
            message: '[mt-password-field] The "isInvalid" prop is deprecated. Remove it.',
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
            message: '[mt-password-field] The "isInvalid" prop is deprecated. Remove it.',
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
            message: '[mt-password-field] The "update:value" event is deprecated. Use "update:mode-value" instead.',
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
            message: '[mt-password-field] The "update:value" event is deprecated. Use "update:mode-value" instead.',
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
            message: '[mt-password-field] The "base-field-mounted" event is deprecated. Remove it.',
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
            message: '[mt-password-field] The "base-field-mounted" event is deprecated. Remove it.',
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
                <mt-password-field>
                    <!-- Slot "label" was removed and should be replaced with "label" prop. Previous value was:  My Label  -->
                </mt-password-field>
            </template>`,
        errors: [{
            message: '[mt-password-field] The "label" slot is deprecated. Use the "label" prop instead.',
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
            message: '[mt-password-field] The "label" slot is deprecated. Use the "label" prop instead.',
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
                <mt-password-field>
                    <!-- Slot "label" was removed and should be replaced with "label" prop. Previous value was:  My Label  -->
                </mt-password-field>
            </template>`,
        errors: [{
            message: '[mt-password-field] The "label" slot is deprecated. Use the "label" prop instead.',
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
            message: '[mt-password-field] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-password-field" wrong "hint" slot usage should be removed [shorthand syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-password-field>
                    <template #hint>
                        My Hint
                    </template>
                </mt-password-field>
            </template>`,
        output: `
            <template>
                <mt-password-field>
                    <!-- Slot "hint" was removed and should be replaced with "hint" prop. Previous value was:  My Hint  -->
                </mt-password-field>
            </template>`,
        errors: [{
            message: '[mt-password-field] The "hint" slot is deprecated. Use the "hint" prop instead.',
        }]
    },
    {
        name: '"mt-password-field" wrong "hint" slot usage should be removed [disableFix, shorthand syntax]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-password-field>
                    <template #hint>
                        My Hint
                    </template>
                </mt-password-field>
            </template>`,
        errors: [{
            message: '[mt-password-field] The "hint" slot is deprecated. Use the "hint" prop instead.',
        }]
    },
    {
        name: '"mt-password-field" wrong "hint" slot usage should be removed [v-slot syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-password-field>
                    <template v-slot:hint>
                        My Hint
                    </template>
                </mt-password-field>
            </template>`,
        output: `
            <template>
                <mt-password-field>
                    <!-- Slot "hint" was removed and should be replaced with "hint" prop. Previous value was:  My Hint  -->
                </mt-password-field>
            </template>`,
        errors: [{
            message: '[mt-password-field] The "hint" slot is deprecated. Use the "hint" prop instead.',
        }]
    },
    {
        name: '"mt-password-field" wrong "hint" slot usage should be removed [v-slot syntax, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-password-field>
                    <template v-slot:hint>
                        My Hint
                    </template>
                </mt-password-field>
            </template>`,
        errors: [{
            message: '[mt-password-field] The "hint" slot is deprecated. Use the "hint" prop instead.',
        }]
    }
];

module.exports = {
    mtPasswordFieldValidTests,
    mtPasswordFieldInvalidTests
};
