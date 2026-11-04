// A fix that writes an attribute the element already has is dropped, unless both pass the same value or sit in different Twig branches.
const duplicateReplacementInvalidTests = [
    {
        name: 'does not fix value rename when model-value already exists',
        filename: 'test.html.twig',
        code: '<template><mt-text-field model-value="new" value="old" /></template>',
        output: null,
        errors: [
            {
                message:
                    '[mt-text-field] The "value" API is deprecated. Use "model-value" instead.\nNot fixed: the element already passes "model-value" with a different value. Merge them manually.',
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
                message:
                    '[mt-text-field] The "value" API is deprecated. Use "model-value" instead.\nNot fixed: the element already passes "model-value" with a different value. Merge them manually.',
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
                message:
                    '[mt-text-field] The "value" API is deprecated. Use "model-value" instead.\nNot fixed: the element already passes "model-value" with a different value. Merge them manually.',
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
                message:
                    '[mt-text-field] The "value" API is deprecated. Use "model-value" instead.\nNot fixed: the element already passes "model-value" with a different value. Merge them manually.',
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
                message:
                    '[mt-text-field] The "v-model:value" binding is deprecated. Use "v-model" instead.\nNot fixed: the element already passes "v-model" with a different value. Merge them manually.',
            },
        ],
    },
    {
        name: 'removes the deprecated prop when the replacement already passes the same value',
        filename: 'test.html.twig',
        code: '<template><mt-switch remove-top-margin no-margin-top /></template>',
        output: '<template><mt-switch remove-top-margin /></template>',
        errors: [
            {
                message:
                    '[mt-switch] The "no-margin-top" API is deprecated. Use "remove-top-margin" instead.\nThe element already passes "remove-top-margin" with the same value, so the deprecated attribute is removed.',
            },
        ],
    },
    {
        name: 'renames the deprecated prop when the replacement sits in another Twig branch',
        filename: 'test.html.twig',
        code: '<template><mt-text-field {% if VUE3 %}:model-value="name"{% else %}:value="name"{% endif %} /></template>',
        output: '<template><mt-text-field {% if VUE3 %}:model-value="name"{% else %}:model-value="name"{% endif %} /></template>',
        errors: [
            {
                message: '[mt-text-field] The "value" API is deprecated. Use "model-value" instead.',
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
                message:
                    '[mt-text-field] The "update:value" API is deprecated. Use "update:model-value" instead.\nNot fixed: the element already passes "update:model-value" with a different value. Merge them manually.',
            },
        ],
    },
];

module.exports = {
    valid: [],
    invalid: duplicateReplacementInvalidTests,
};
