// A fix is dropped when the element already has the attribute it would write.
const duplicateReplacementInvalidTests = [
    {
        name: 'does not fix value rename when model-value already exists',
        filename: 'test.html.twig',
        code: '<template><mt-text-field model-value="new" value="old" /></template>',
        output: null,
        errors: [
            {
                message: '[mt-text-field] The "value" API is deprecated. Use "model-value" instead.',
            },
        ],
    },
    {
        name: 'does not fix bound value rename when bound model-value already exists',
        filename: 'test.html.twig',
        code: '<template><mt-text-field :model-value="newValue" :value="oldValue" /></template>',
        output: null,
        errors: [
            {
                message: '[mt-text-field] The "value" API is deprecated. Use "model-value" instead.',
            },
        ],
    },
    {
        name: 'does not fix value rename when camelCase modelValue already exists',
        filename: 'test.html.twig',
        code: '<template><mt-text-field modelValue="new" value="old" /></template>',
        output: null,
        errors: [
            {
                message: '[mt-text-field] The "value" API is deprecated. Use "model-value" instead.',
            },
        ],
    },
    {
        name: 'does not fix bound value rename when camelCase bound modelValue already exists',
        filename: 'test.html.twig',
        code: '<template><mt-text-field :modelValue="newValue" :value="oldValue" /></template>',
        output: null,
        errors: [
            {
                message: '[mt-text-field] The "value" API is deprecated. Use "model-value" instead.',
            },
        ],
    },
    {
        name: 'does not fix v-model argument rename when camelCase replacement model already exists',
        filename: 'test.html.twig',
        code: '<template><mt-text-field v-model:modelValue="newValue" v-model:value="oldValue" /></template>',
        output: null,
        errors: [
            {
                message: '[mt-text-field] The "value" API is deprecated.',
            },
        ],
    },
    {
        name: 'does not fix camelCase prop rename when replacement already exists',
        filename: 'test.html.twig',
        code: '<template><mt-switch remove-top-margin no-margin-top /></template>',
        output: null,
        errors: [
            {
                message: '[mt-switch] The "no-margin-top" API is deprecated. Use "remove-top-margin" instead.',
            },
        ],
    },
    {
        name: 'does not fix event rename when replacement event already exists',
        filename: 'test.html.twig',
        code: '<template><mt-text-field @update:model-value="onNew" @update:value="onOld" /></template>',
        output: null,
        errors: [
            {
                message: '[mt-text-field] The "update:value" API is deprecated. Use "update:model-value" instead.',
            },
        ],
    },
];

module.exports = {
    valid: [],
    invalid: duplicateReplacementInvalidTests,
};
