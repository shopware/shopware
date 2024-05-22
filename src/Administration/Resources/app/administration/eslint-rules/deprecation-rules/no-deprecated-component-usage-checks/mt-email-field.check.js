const { handleMtTextField } = require('./mt-text-field.check');

const handleMtEmailField = (context, node) => {
    return handleMtTextField(context, node, true);
}

const mtEmailFieldValidTests = [
    {
        name: '"sw-email-field" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-email-field />
            </template>`
    }
];

const mtEmailFieldInvalidTests = [
    {
        name: '"mt-email-field" wrong "value" prop usage should be replaced with "modelValue"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-email-field value="Hello World" />
            </template>`,
        output: `
            <template>
                <mt-email-field modelValue="Hello World" />
            </template>`,
        errors: [{
            message: '[mt-email-field] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-email-field" wrong "value" prop usage should be replaced with "modelValue" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-email-field value="Hello World" />
            </template>`,
        errors: [{
            message: '[mt-email-field] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-email-field" wrong "value" prop usage should be replaced with "modelValue" [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-email-field :value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-email-field :modelValue="myValue" />
            </template>`,
        errors: [{
            message: '[mt-email-field] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-email-field" wrong "value" prop usage should be replaced with "modelValue" [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-email-field :value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-email-field] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-email-field" wrong "v-model:value" usage should be replaced with default v-model',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-email-field v-model:value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-email-field v-model="myValue" />
            </template>`,
        errors: [{
            message: '[mt-email-field] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-email-field" wrong "v-model:value" usage should be replaced with default v-model [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-email-field v-model:value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-email-field] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-email-field" wrong "size" prop "medium" usage should be replaced with "default"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-email-field size="medium" />
            </template>`,
        output: `
            <template>
                <mt-email-field size="default" />
            </template>`,
        errors: [{
            message: '[mt-email-field] The "size" prop value "medium" is deprecated. Use "default" instead.',
        }]
    },
    {
        name: '"mt-email-field" wrong "size" prop usage should be replaced with "default" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-email-field size="medium" />
            </template>`,
        errors: [{
            message: '[mt-email-field] The "size" prop value "medium" is deprecated. Use "default" instead.',
        }]
    },
    {
        name: '"mt-email-field" wrong "isInvalid" prop usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-email-field isInvalid />
            </template>`,
        output: `
            <template>
                <mt-email-field  />
            </template>`,
        errors: [{
            message: '[mt-email-field] The "isInvalid" prop is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-email-field" wrong "isInvalid" prop usage should be removed [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-email-field isInvalid />
            </template>`,
        errors: [{
            message: '[mt-email-field] The "isInvalid" prop is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-email-field" wrong "isInvalid" prop expression usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-email-field :isInvalid="1 == 1" />
            </template>`,
        output: `
            <template>
                <mt-email-field  />
            </template>`,
        errors: [{
            message: '[mt-email-field] The "isInvalid" prop is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-email-field" wrong "isInvalid" prop expression usage should be removed [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-email-field :isInvalid="1 == 1" />
            </template>`,
        errors: [{
            message: '[mt-email-field] The "isInvalid" prop is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-email-field" wrong "aiBadge" prop usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-email-field aiBadge />
            </template>`,
        output: `
            <template>
                <mt-email-field  />
            </template>`,
        errors: [{
            message: '[mt-email-field] The "aiBadge" prop is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-email-field" wrong "aiBadge" prop usage should be removed [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-email-field aiBadge />
            </template>`,
        errors: [{
            message: '[mt-email-field] The "aiBadge" prop is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-email-field" wrong "aiBadge" prop expression usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-email-field :aiBadge="1 == 1" />
            </template>`,
        output: `
            <template>
                <mt-email-field  />
            </template>`,
        errors: [{
            message: '[mt-email-field] The "aiBadge" prop is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-email-field" wrong "aiBadge" prop expression usage should be removed [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-email-field :aiBadge="1 == 1" />
            </template>`,
        errors: [{
            message: '[mt-email-field] The "aiBadge" prop is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-email-field" wrong "update:value" event usage should be replaced with "update:modelValue"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-email-field @update:value="updateValue" />
            </template>`,
        output: `
            <template>
                <mt-email-field @update:modelValue="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-email-field] The "update:value" event is deprecated. Use "update:modelValue" instead.',
        }],
    },
    {
        name: '"mt-email-field" wrong "update:value" event usage should be replaced with "update:modelValue" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-email-field @update:value="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-email-field] The "update:value" event is deprecated. Use "update:modelValue" instead.',
        }],
    },
    {
        name: '"mt-email-field" wrong "base-field-mounted" event usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-email-field @base-field-mounted="onFieldMounted" />
            </template>`,
        output: `
            <template>
                <mt-email-field  />
            </template>`,
        errors: [{
            message: '[mt-email-field] The "base-field-mounted" event is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-email-field" wrong "base-field-mounted" event usage should be removed [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-email-field @base-field-mounted="onFieldMounted" />
            </template>`,
        errors: [{
            message: '[mt-email-field] The "base-field-mounted" event is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-email-field" wrong "label" slot usage should be removed [shorthand syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-email-field>
                    <template #label>
                        My Label
                    </template>
                </mt-text-field>
            </template>`,
        output: `
            <template>
                <mt-email-field>
                    <!-- Slot "label" was removed and should be replaced with "label" prop. Previous value was:  My Label  -->
                </mt-text-field>
            </template>`,
        errors: [{
            message: '[mt-email-field] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-email-field" wrong "label" slot usage should be removed [disableFix, shorthand syntax]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-email-field>
                    <template #label>
                        My Label
                    </template>
                </mt-text-field>
            </template>`,
        errors: [{
            message: '[mt-email-field] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-email-field" wrong "label" slot usage should be removed [v-slot syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-email-field>
                    <template v-slot:label>
                        My Label
                    </template>
                </mt-text-field>
            </template>`,
        output: `
            <template>
                <mt-email-field>
                    <!-- Slot "label" was removed and should be replaced with "label" prop. Previous value was:  My Label  -->
                </mt-text-field>
            </template>`,
        errors: [{
            message: '[mt-email-field] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-email-field" wrong "label" slot usage should be removed [v-slot syntax, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-email-field>
                    <template v-slot:label>
                        My Label
                    </template>
                </mt-text-field>
            </template>`,
        errors: [{
            message: '[mt-email-field] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
];

module.exports = {
    handleMtEmailField,
    mtEmailFieldValidTests,
    mtEmailFieldInvalidTests,
};
