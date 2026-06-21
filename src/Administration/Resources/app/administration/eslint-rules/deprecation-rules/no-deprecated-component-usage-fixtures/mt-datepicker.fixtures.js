const mtDatepickerValidTests = [
    {
        name: '"mt-datepicker" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-datepicker>Hello</mt-datepicker>
            </template>`
    },
    {
        name: '"sw-datepicker" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-datepicker>Hello</sw-datepicker>
            </template>`
    }
]

const mtDatepickerInvalidTests = [
    {
        name: '"mt-datepicker" wrong "value" prop usage should be replaced with "model-value"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-datepicker value="yes" />
            </template>`,
        output: `
            <template>
                <mt-datepicker model-value="yes" />
            </template>`,
        errors: [{
            message: '[mt-datepicker] The "value" prop is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-datepicker" wrong "value" prop usage should be replaced with "model-value" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-datepicker value="yes" />
            </template>`,
        errors: [{
            message: '[mt-datepicker] The "value" prop is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-datepicker" wrong "value" prop usage should be replaced with "model-value" [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-datepicker :value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-datepicker :model-value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-datepicker] The "value" prop is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-datepicker" wrong "value" prop usage should be replaced with "model-value" [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-datepicker :value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-datepicker] The "value" prop is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-datepicker" wrong "v-model:value" usage should be replaced with default v-model',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-datepicker v-model:value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-datepicker v-model="myValue" />
            </template>`,
        errors: [{
            message: '[mt-datepicker] The "v-model:value" binding is deprecated. Use "v-model" instead.',
        }]
    },
    {
        name: '"mt-datepicker" wrong "v-model:value" usage should be replaced with default v-model [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-datepicker v-model:value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-datepicker] The "v-model:value" binding is deprecated. Use "v-model" instead.',
        }]
    },
    {
        name: '"mt-datepicker" wrong "update:value" event usage should be replaced with "update:mode-value"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-datepicker @update:value="updateValue" />
            </template>`,
        output: `
            <template>
                <mt-datepicker @update:model-value="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-datepicker] The "update:value" event is deprecated. Use "update:mode-value" instead.',
        }],
    },
    {
        name: '"mt-datepicker" wrong "update:value" event usage should be replaced with "update:mode-value" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-datepicker @update:value="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-datepicker] The "update:value" event is deprecated. Use "update:mode-value" instead.',
        }],
    },
    {
        name: '"mt-datepicker" wrong "label" slot usage should be removed [shorthand syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-datepicker>
                    <template #label>
                        My Label
                    </template>
                </mt-datepicker>
            </template>`,
        output: `
            <template>
                <mt-datepicker>
                    <!-- Slot "label" was removed and should be replaced with "label" prop. Previous value was:  My Label  -->
                </mt-datepicker>
            </template>`,
        errors: [{
            message: '[mt-datepicker] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-datepicker" wrong "label" slot usage should be removed [disableFix, shorthand syntax]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-datepicker>
                    <template #label>
                        My Label
                    </template>
                </mt-datepicker>
            </template>`,
        errors: [{
            message: '[mt-datepicker] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-datepicker" wrong "label" slot usage should be removed [v-slot syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-datepicker>
                    <template v-slot:label>
                        My Label
                    </template>
                </mt-datepicker>
            </template>`,
        output: `
            <template>
                <mt-datepicker>
                    <!-- Slot "label" was removed and should be replaced with "label" prop. Previous value was:  My Label  -->
                </mt-datepicker>
            </template>`,
        errors: [{
            message: '[mt-datepicker] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-datepicker" wrong "label" slot usage should be removed [v-slot syntax, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-datepicker>
                    <template v-slot:label>
                        My Label
                    </template>
                </mt-datepicker>
            </template>`,
        errors: [{
            message: '[mt-datepicker] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
];

module.exports = {
    mtDatepickerValidTests,
    mtDatepickerInvalidTests
};