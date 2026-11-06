/**
 * @sw-package framework
 */

const mtCheckboxValidTests = [
    {
        name: '"sw-checkbox-field" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-checkbox-field />
            </template>`
    },
    {
        name: '"mt-checkbox" with "v-model:checked" is still allowed until the mass migration',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox v-model:checked="isCheckedValue" />
            </template>`,
    },
    {
        name: '"mt-checkbox" with plain "v-model" is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox v-model="isCheckedValue" />
            </template>`,
    },
]

const mtCheckboxInvalidTests = [
    {
        name: '"mt-checkbox" wrong "value" prop usage should be renamed to "model-value"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox value="yes" />
            </template>`,
        output: `
            <template>
                <mt-checkbox model-value="yes" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-checkbox" wrong "value" prop usage should be renamed to "model-value" [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox :value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-checkbox :model-value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "value" API is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-checkbox" wrong slot "hint" usage. Was removed without replacement',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox>
                    <template v-slot:hint>
                        Hello Shopware
                    </template>
                </mt-checkbox>
            </template>`,
        output: `
            <template>
                <mt-checkbox label="Hello Shopware">
                </mt-checkbox>
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "hint" slot is removed. Pass its content as the "label" prop.',
        }]
    },
    {
        name: '"mt-checkbox" wrong slot "hint" usage. Was removed without replacement [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-checkbox>
                    <template v-slot:hint>
                        Hello Shopware
                    </template>
                </mt-checkbox>
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "hint" slot is removed. Pass its content as the "label" prop.',
        }]
    },
    {
        name: '"mt-checkbox" wrong slot "hint" usage. Was removed without replacement [shorthandSyntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox>
                    <template #hint>
                        Hello Shopware
                    </template>
                </mt-checkbox>
            </template>`,
        output: `
            <template>
                <mt-checkbox label="Hello Shopware">
                </mt-checkbox>
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "hint" slot is removed. Pass its content as the "label" prop.',
        }]
    },
    {
        name: '"mt-checkbox" wrong slot "hint" usage. Was removed without replacement [shorthandSyntax, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-checkbox>
                    <template #hint>
                        Hello Shopware
                    </template>
                </mt-checkbox>
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "hint" slot is removed. Pass its content as the "label" prop.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "id" usage should be removed without replacement',
        filename: 'test.html.twig',
        code: `
            <template>
                <!-- TODO Codemod: Converted from sw-checkbox-field - please check if everything works correctly -->
                <mt-checkbox id="checkbox-id" />
            </template>`,
        output: `
            <template>
                <!-- TODO Codemod: Converted from sw-checkbox-field - please check if everything works correctly -->
                <!-- TODO Codemod: [mt-checkbox] mt-checkbox has no "id" prop. sw-checkbox-field gave its input the id and name "sw-field--<id>", mt-checkbox generates them: check selectors, label "for" attributes and tests that use them. -->
                <mt-checkbox />
            </template>`,
        errors: [{
            message: '[mt-checkbox] mt-checkbox has no "id" prop. sw-checkbox-field gave its input the id and name "sw-field--<id>", mt-checkbox generates them: check selectors, label "for" attributes and tests that use them.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "id" usage should be removed without replacement [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <!-- TODO Codemod: Converted from sw-checkbox-field - please check if everything works correctly -->
                <mt-checkbox id="checkbox-id" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] mt-checkbox has no "id" prop. sw-checkbox-field gave its input the id and name "sw-field--<id>", mt-checkbox generates them: check selectors, label "for" attributes and tests that use them.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "id" usage should be removed without replacement [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <!-- TODO Codemod: Converted from sw-checkbox-field - please check if everything works correctly -->
                <mt-checkbox :id="checkboxId" />
            </template>`,
        output: `
            <template>
                <!-- TODO Codemod: Converted from sw-checkbox-field - please check if everything works correctly -->
                <!-- TODO Codemod: [mt-checkbox] mt-checkbox has no "id" prop. sw-checkbox-field gave its input the id and name "sw-field--<id>", mt-checkbox generates them: check selectors, label "for" attributes and tests that use them. -->
                <mt-checkbox />
            </template>`,
        errors: [{
            message: '[mt-checkbox] mt-checkbox has no "id" prop. sw-checkbox-field gave its input the id and name "sw-field--<id>", mt-checkbox generates them: check selectors, label "for" attributes and tests that use them.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "id" usage should be removed without replacement [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <!-- TODO Codemod: Converted from sw-checkbox-field - please check if everything works correctly -->
                <mt-checkbox :id="checkboxId" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] mt-checkbox has no "id" prop. sw-checkbox-field gave its input the id and name "sw-field--<id>", mt-checkbox generates them: check selectors, label "for" attributes and tests that use them.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "ghostValue" usage should be removed without replacement',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox ghostValue="yes" />
            </template>`,
        output: `
            <template>
                <mt-checkbox />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "ghost-value" API is deprecated.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "ghostValue" usage should be removed without replacement [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-checkbox ghostValue="yes" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "ghost-value" API is deprecated.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "ghostValue" usage should be removed without replacement [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox :ghostValue="yes" />
            </template>`,
        output: `
            <template>
                <mt-checkbox />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "ghost-value" API is deprecated.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "ghostValue" usage should be removed without replacement [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-checkbox :ghostValue="yes" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "ghost-value" API is deprecated.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "partlyChecked" usage should be replaced with "partial"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox partlyChecked />
            </template>`,
        output: `
            <template>
                <mt-checkbox partial />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "partly-checked" API is deprecated. Use "partial" instead.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "partlyChecked" usage should be replaced with "partial" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-checkbox partlyChecked />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "partly-checked" API is deprecated. Use "partial" instead.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "partlyChecked" usage should be replaced with "partial" [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox :partlyChecked="isChecked" />
            </template>`,
        output: `
            <template>
                <mt-checkbox :partial="isChecked" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "partly-checked" API is deprecated. Use "partial" instead.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "partlyChecked" usage should be replaced with "partial" [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-checkbox :partlyChecked="isChecked" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "partly-checked" API is deprecated. Use "partial" instead.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "padded" usage should be removed without replacement',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox padded />
            </template>`,
        output: `
            <template>
                <mt-checkbox />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "padded" API is deprecated.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "padded" usage should be removed without replacement [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-checkbox padded />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "padded" API is deprecated.',
        }]
    },
    {
        name: '"mt-checkbox" wrong event "update:value" usage should be replaced with "update:modelValue"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox @update:value="updateValue" />
            </template>`,
        output: `
            <template>
                <mt-checkbox @update:modelValue="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "update:value" API is deprecated. Use "update:modelValue" instead.',
        }]
    },
    {
        name: '"mt-checkbox" wrong event "update:value" usage should be replaced with "update:modelValue" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-checkbox @update:value="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "update:value" API is deprecated. Use "update:modelValue" instead.',
        }]
    }
]

module.exports = {
    valid: mtCheckboxValidTests,
    invalid: mtCheckboxInvalidTests,
};
