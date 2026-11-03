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
        name: '"mt-email-field" wrong "value" prop usage should be replaced with "model-value"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-email-field value="Hello World" />
            </template>`,
        output: `
            <template>
                <mt-email-field model-value="Hello World" />
            </template>`,
        errors: [{
            message: '[mt-email-field] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-email-field" wrong "value" prop usage should be replaced with "model-value" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-email-field value="Hello World" />
            </template>`,
        errors: [{
            message: '[mt-email-field] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-email-field" wrong "value" prop usage should be replaced with "model-value" [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-email-field :value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-email-field :model-value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-email-field] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-email-field" wrong "value" prop usage should be replaced with "model-value" [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-email-field :value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-email-field] The "value" API is deprecated. Use "model-value" instead.',
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
            message: '[mt-email-field] The "value" API is deprecated.',
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
            message: '[mt-email-field] The "value" API is deprecated.',
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
            message: '[mt-email-field] The "size" API is deprecated. Use "default" instead.',
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
            message: '[mt-email-field] The "size" API is deprecated. Use "default" instead.',
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
            message: '[mt-email-field] The "is-invalid" API is deprecated.',
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
            message: '[mt-email-field] The "is-invalid" API is deprecated.',
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
            message: '[mt-email-field] The "is-invalid" API is deprecated.',
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
            message: '[mt-email-field] The "is-invalid" API is deprecated.',
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
            message: '[mt-email-field] The "ai-badge" API is deprecated.',
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
            message: '[mt-email-field] The "ai-badge" API is deprecated.',
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
            message: '[mt-email-field] The "ai-badge" API is deprecated.',
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
            message: '[mt-email-field] The "ai-badge" API is deprecated.',
        }]
    },
    {
        name: '"mt-email-field" wrong "update:value" event usage should be replaced with "update:mode-value"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-email-field @update:value="updateValue" />
            </template>`,
        output: `
            <template>
                <mt-email-field @update:model-value="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-email-field] The "update:value" API is deprecated. Use "update:model-value" instead.',
        }],
    },
    {
        name: '"mt-email-field" wrong "update:value" event usage should be replaced with "update:mode-value" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-email-field @update:value="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-email-field] The "update:value" API is deprecated. Use "update:model-value" instead.',
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
            message: '[mt-email-field] The "base-field-mounted" API is deprecated.',
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
            message: '[mt-email-field] The "base-field-mounted" API is deprecated.',
        }]
    },
    {
        name: '"mt-email-field" wrong camelCase "baseFieldMounted" event usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-email-field @baseFieldMounted="onFieldMounted" />
            </template>`,
        output: `
            <template>
                <mt-email-field  />
            </template>`,
        errors: [{
            message: '[mt-email-field] The "base-field-mounted" API is deprecated.',
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
            message: '[mt-email-field] The "label" API is deprecated.',
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
            message: '[mt-email-field] The "label" API is deprecated.',
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
            message: '[mt-email-field] The "label" API is deprecated.',
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
            message: '[mt-email-field] The "label" API is deprecated.',
        }]
    },
];

module.exports = {
    valid: mtEmailFieldValidTests,
    invalid: mtEmailFieldInvalidTests,
};
