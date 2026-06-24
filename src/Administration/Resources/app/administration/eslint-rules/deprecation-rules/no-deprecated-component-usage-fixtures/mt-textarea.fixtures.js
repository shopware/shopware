const mtTextareaValidTests = [
    {
        name: '"sw-textarea-field" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-textarea-field />
            </template>`
    }
]

const mtTextareaInvalidTests = [
    {
        name: '"mt-textarea" wrong "value" prop usage should be replaced with "model-value"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-textarea value="yes" />
            </template>`,
        output: `
            <template>
                <mt-textarea model-value="yes" />
            </template>`,
        errors: [{
            message: '[mt-textarea] The "value" prop is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-textarea" wrong "value" prop usage should be replaced with "model-value" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-textarea value="yes" />
            </template>`,
        errors: [{
            message: '[mt-textarea] The "value" prop is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-textarea" wrong "value" prop usage should be replaced with "model-value" [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-textarea :value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-textarea :model-value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-textarea] The "value" prop is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-textarea" wrong "value" prop usage should be replaced with "model-value" [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-textarea :value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-textarea] The "value" prop is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-textarea" wrong "v-model:value" usage should be replaced with default v-model',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-textarea v-model:value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-textarea v-model="myValue" />
            </template>`,
        errors: [{
            message: '[mt-textarea] The "v-model:value" binding is deprecated. Use "v-model" instead.',
        }]
    },
    {
        name: '"mt-textarea" wrong "v-model:value" usage should be replaced with default v-model [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-textarea v-model:value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-textarea] The "v-model:value" binding is deprecated. Use "v-model" instead.',
        }]
    },
    {
        name: '"mt-textarea" wrong "update:value" event usage should be replaced with "update:mode-value"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-textarea @update:value="updateValue" />
            </template>`,
        output: `
            <template>
                <mt-textarea @update:model-value="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-textarea] The "update:value" event is deprecated. Use "update:mode-value" instead.',
        }],
    },
    {
        name: '"mt-textarea" wrong "update:value" event usage should be replaced with "update:mode-value" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-textarea @update:value="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-textarea] The "update:value" event is deprecated. Use "update:mode-value" instead.',
        }],
    },
    {
        name: '"mt-textarea" wrong "label" slot usage should be removed [shorthand syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-textarea>
                    <template #label>
                        My Label
                    </template>
                </mt-textarea>
            </template>`,
        output: `
            <template>
                <mt-textarea>
                    <!-- Slot "label" was removed and should be replaced with "label" prop. Previous value was:  My Label  -->
                </mt-textarea>
            </template>`,
        errors: [{
            message: '[mt-textarea] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-textarea" wrong "label" slot usage should be removed [disableFix, shorthand syntax]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-textarea>
                    <template #label>
                        My Label
                    </template>
                </mt-textarea>
            </template>`,
        errors: [{
            message: '[mt-textarea] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-textarea" wrong "label" slot usage should be removed [v-slot syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-textarea>
                    <template v-slot:label>
                        My Label
                    </template>
                </mt-textarea>
            </template>`,
        output: `
            <template>
                <mt-textarea>
                    <!-- Slot "label" was removed and should be replaced with "label" prop. Previous value was:  My Label  -->
                </mt-textarea>
            </template>`,
        errors: [{
            message: '[mt-textarea] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-textarea" wrong "label" slot usage should be removed [v-slot syntax, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-textarea>
                    <template v-slot:label>
                        My Label
                    </template>
                </mt-textarea>
            </template>`,
        errors: [{
            message: '[mt-textarea] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
]

module.exports = {
    mtTextareaValidTests,
    mtTextareaInvalidTests
};
