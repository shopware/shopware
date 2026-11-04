const mtTextFieldValidTests = [
    {
        name: '"sw-text-field" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-text-field />
            </template>`
    }
];

const mtTextFieldInvalidTests = [
    {
        name: '"mt-text-field" wrong "value" prop usage should be replaced with "model-value"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-text-field value="Hello World" />
            </template>`,
        output: `
            <template>
                <mt-text-field model-value="Hello World" />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-text-field" wrong "value" prop usage should be replaced with "model-value" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-text-field value="Hello World" />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-text-field" wrong "value" prop usage should be replaced with "model-value" [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-text-field :value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-text-field :model-value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-text-field" wrong "value" prop usage should be replaced with "model-value" [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-text-field :value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-text-field" wrong "v-model:value" usage should be replaced with default v-model',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-text-field v-model:value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-text-field v-model="myValue" />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "value" API is deprecated.',
        }]
    },
    {
        name: '"mt-text-field" wrong "v-model:value" usage should be replaced with default v-model [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-text-field v-model:value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "value" API is deprecated.',
        }]
    },
    {
        name: '"mt-text-field" wrong "size" prop "medium" usage should be replaced with "default"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-text-field size="medium" />
            </template>`,
        output: `
            <template>
                <mt-text-field size="default" />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "size" API is deprecated. Use "default" instead.',
        }]
    },
    {
        name: '"mt-text-field" wrong "size" prop usage should be replaced with "default" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-text-field size="medium" />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "size" API is deprecated. Use "default" instead.',
        }]
    },
    {
        name: '"mt-text-field" wrong "isInvalid" prop usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-text-field isInvalid />
            </template>`,
        output: `
            <template>
                <mt-text-field  />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "is-invalid" API is deprecated.',
        }]
    },
    {
        name: '"mt-text-field" wrong "isInvalid" prop usage should be removed [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-text-field isInvalid />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "is-invalid" API is deprecated.',
        }]
    },
    {
        name: '"mt-text-field" wrong "isInvalid" prop expression usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-text-field :isInvalid="1 == 1" />
            </template>`,
        output: `
            <template>
                <mt-text-field  />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "is-invalid" API is deprecated.',
        }]
    },
    {
        name: '"mt-text-field" wrong "isInvalid" prop expression usage should be removed [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-text-field :isInvalid="1 == 1" />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "is-invalid" API is deprecated.',
        }]
    },
    {
        name: '"mt-text-field" wrong "aiBadge" prop usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-text-field aiBadge />
            </template>`,
        output: `
            <template>
                <mt-text-field  />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "ai-badge" API is deprecated.',
        }]
    },
    {
        name: '"mt-text-field" wrong "aiBadge" prop usage should be removed [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-text-field aiBadge />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "ai-badge" API is deprecated.',
        }]
    },
    {
        name: '"mt-text-field" wrong "aiBadge" prop expression usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-text-field :aiBadge="1 == 1" />
            </template>`,
        output: `
            <template>
                <mt-text-field  />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "ai-badge" API is deprecated.',
        }]
    },
    {
        name: '"mt-text-field" wrong "aiBadge" prop expression usage should be removed [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-text-field :aiBadge="1 == 1" />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "ai-badge" API is deprecated.',
        }]
    },
    {
        name: '"mt-text-field" wrong "update:value" event usage should be replaced with "update:mode-value"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-text-field @update:value="updateValue" />
            </template>`,
        output: `
            <template>
                <mt-text-field @update:model-value="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "update:value" API is deprecated. Use "update:model-value" instead.',
        }],
    },
    {
        name: '"mt-text-field" wrong "update:value" event usage should be replaced with "update:mode-value" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-text-field @update:value="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "update:value" API is deprecated. Use "update:model-value" instead.',
        }],
    },
    {
        name: '"mt-text-field" wrong "base-field-mounted" event usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-text-field @base-field-mounted="onFieldMounted" />
            </template>`,
        output: `
            <template>
                <mt-text-field  />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "base-field-mounted" API is deprecated.',
        }]
    },
    {
        name: '"mt-text-field" wrong "base-field-mounted" event usage should be removed [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-text-field @base-field-mounted="onFieldMounted" />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "base-field-mounted" API is deprecated.',
        }]
    },
    {
        name: '"mt-text-field" wrong "label" slot usage should be removed [shorthand syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-text-field>
                    <template #label>
                        My Label
                    </template>
                </mt-text-field>
            </template>`,
        output: `
            <template>
                <mt-text-field label="My Label">
                </mt-text-field>
            </template>`,
        errors: [{
            message: '[mt-text-field] The "label" slot is removed. Pass its content as the "label" prop.',
        }]
    },
    {
        name: '"mt-text-field" wrong "label" slot usage should be removed [disableFix, shorthand syntax]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-text-field>
                    <template #label>
                        My Label
                    </template>
                </mt-text-field>
            </template>`,
        errors: [{
            message: '[mt-text-field] The "label" slot is removed. Pass its content as the "label" prop.',
        }]
    },
    {
        name: '"mt-text-field" wrong "label" slot usage should be removed [v-slot syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-text-field>
                    <template v-slot:label>
                        My Label
                    </template>
                </mt-text-field>
            </template>`,
        output: `
            <template>
                <mt-text-field label="My Label">
                </mt-text-field>
            </template>`,
        errors: [{
            message: '[mt-text-field] The "label" slot is removed. Pass its content as the "label" prop.',
        }]
    },
    {
        name: '"mt-text-field" wrong "label" slot usage should be removed [v-slot syntax, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-text-field>
                    <template v-slot:label>
                        My Label
                    </template>
                </mt-text-field>
            </template>`,
        errors: [{
            message: '[mt-text-field] The "label" slot is removed. Pass its content as the "label" prop.',
        }]
    },
];

module.exports = {
    valid: mtTextFieldValidTests,
    invalid: mtTextFieldInvalidTests,
};
