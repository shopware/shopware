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
        name: '"mt-checkbox" wrong v-model usage should be replaced with "v-model:checked"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox v-model:checked="isCheckedValue" />
            </template>`,
    },
]

const mtCheckboxInvalidTests = [
    {
        name: '"mt-checkbox" wrong "value" prop usage should be replaced with "checked"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox value="yes" />
            </template>`,
        output: `
            <template>
                <mt-checkbox checked="yes" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "value" prop is deprecated. Use "checked" instead.',
        }]
    },
    {
        name: '"mt-checkbox" wrong "value" prop usage should be replaced with "checked" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-checkbox value="yes" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "value" prop is deprecated. Use "checked" instead.',
        }]
    },
    {
        name: '"mt-checkbox" wrong "value" prop usage should be replaced with "checked" [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox :value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-checkbox :checked="myValue" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "value" prop is deprecated. Use "checked" instead.',
        }]
    },
    {
        name: '"mt-checkbox" wrong "value" prop usage should be replaced with "checked" [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-checkbox :value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "value" prop is deprecated. Use "checked" instead.',
        }]
    },
    {
        name: '"mt-checkbox" wrong v-model usage should be replaced with "v-model:checked"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox v-model="isCheckedValue" />
            </template>`,
        output: `
            <template>
                <mt-checkbox v-model:checked="isCheckedValue" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "v-model" directive is deprecated. Use "v-model:checked" instead.',
        }]
    },
    {
        name: '"mt-checkbox" wrong v-model usage should be replaced with "v-model:checked" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-checkbox v-model="isCheckedValue" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "v-model" directive is deprecated. Use "v-model:checked" instead.',
        }]
    },
    {
        name: '"mt-checkbox" wrong v-model usage should be replaced with "v-model:checked" [with :value]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox v-model:value="isCheckedValue" />
            </template>`,
        output: `
            <template>
                <mt-checkbox v-model:checked="isCheckedValue" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "v-model" directive is deprecated. Use "v-model:checked" instead.',
        }]
    },
    {
        name: '"mt-checkbox" wrong v-model usage should be replaced with "v-model:checked" [with :value, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-checkbox v-model:value="isCheckedValue" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "v-model" directive is deprecated. Use "v-model:checked" instead.',
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
                <mt-checkbox>
                    <!-- Slot "hint" was removed and should be replaced with "label" prop. Previous value was:  Hello Shopware  -->
                </mt-checkbox>
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "hint" slot is deprecated. Use the "label" prop instead.',
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
            message: '[mt-checkbox] The "hint" slot is deprecated. Use the "label" prop instead.',
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
                <mt-checkbox>
                    <!-- Slot "hint" was removed and should be replaced with "label" prop. Previous value was:  Hello Shopware  -->
                </mt-checkbox>
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "hint" slot is deprecated. Use the "label" prop instead.',
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
            message: '[mt-checkbox] The "hint" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "id" usage should be removed without replacement',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox id="checkbox-id" />
            </template>`,
        output: `
            <template>
                <mt-checkbox  />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "id" prop is deprecated. Remove it without replacement.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "id" usage should be removed without replacement [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-checkbox id="checkbox-id" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "id" prop is deprecated. Remove it without replacement.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "id" usage should be removed without replacement [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox :id="checkboxId" />
            </template>`,
        output: `
            <template>
                <mt-checkbox  />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "id" prop is deprecated. Remove it without replacement.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "id" usage should be removed without replacement [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-checkbox :id="checkboxId" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "id" prop is deprecated. Remove it without replacement.',
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
                <mt-checkbox  />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "ghostValue" prop is deprecated. Remove it without replacement.',
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
            message: '[mt-checkbox] The "ghostValue" prop is deprecated. Remove it without replacement.',
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
                <mt-checkbox  />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "ghostValue" prop is deprecated. Remove it without replacement.',
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
            message: '[mt-checkbox] The "ghostValue" prop is deprecated. Remove it without replacement.',
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
            message: '[mt-checkbox] The "partlyChecked" prop is deprecated. Use "partial" instead.',
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
            message: '[mt-checkbox] The "partlyChecked" prop is deprecated. Use "partial" instead.',
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
            message: '[mt-checkbox] The "partlyChecked" prop is deprecated. Use "partial" instead.',
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
            message: '[mt-checkbox] The "partlyChecked" prop is deprecated. Use "partial" instead.',
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
                <mt-checkbox  />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "padded" prop is deprecated. Remove it without replacement.',
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
            message: '[mt-checkbox] The "padded" prop is deprecated. Remove it without replacement.',
        }]
    },
    {
        name: '"mt-checkbox" wrong event "update:value" usage should be replaced with "update:checked"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox @update:value="updateValue" />
            </template>`,
        output: `
            <template>
                <mt-checkbox @update:checked="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "update:value" event is deprecated. Use "update:checked" instead.',
        }]
    },
    {
        name: '"mt-checkbox" wrong event "update:value" usage should be replaced with "update:checked" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-checkbox @update:value="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "update:value" event is deprecated. Use "update:checked" instead.',
        }]
    }
]

module.exports = {
    mtCheckboxValidTests,
    mtCheckboxInvalidTests
};
