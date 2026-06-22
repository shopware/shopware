const mtSelectValidTests = [
    {
        name: '"sw-select-field" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-select-field />
            </template>`
    },
    {
        name: '"mt-select" fix already applied for: wrong "default" slot usage. Should be replaced with prop "options" [without declaration]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select>
                    <!-- TODO Codemod: Remove the "default" slot and use the "options" prop instead -->
                    <option value="optionA">Option A</option>
                    <option value="optionB">Option B</option>
                </mt-select>
            </template>`,
    },
    {
        name: '"mt-select" fix already applied for: wrong "default" slot usage. Should be replaced with prop "options" [short syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select>
                    <!-- TODO Codemod: Remove the "default" slot and use the "options" prop instead -->
                    <template #default>
                        <option value="optionA">Option A</option>
                        <option value="optionB">Option B</option>
                    </template>
                </mt-select>
            </template>`,
    },
    {
        name: '"mt-select" fix already applied for: wrong "label" slot usage. Should be replaced with prop "label" [short syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select>
                    <!-- TODO Codemod: Remove the "label" slot and use the "label" prop instead -->
<template #label>
                        My Label
                    </template>
                </mt-select>
            </template>`,
    }
]

const mtSelectInvalidTests = [
    {
        name: '"mt-select" wrong "value" prop usage should be replaced with "model-value"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select value="optionA" />
            </template>`,
        output: `
            <template>
                <mt-select model-value="optionA" />
            </template>`,
        errors: [{
            message: '[mt-select] The "value" prop is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-select" wrong "value" prop usage should be replaced with "model-value" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-select value="optionA" />
            </template>`,
        errors: [{
            message: '[mt-select] The "value" prop is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-select" wrong "value" prop usage should be replaced with "model-value" [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select :value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-select :model-value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-select] The "value" prop is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-select" wrong "value" prop usage should be replaced with "model-value" [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-select :value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-select] The "value" prop is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-select" wrong "v-model:value" binding usage should be replaced with "v-model"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select v-model:value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-select v-model="myValue" />
            </template>`,
        errors: [{
            message: '[mt-select] The "v-model:value" binding is deprecated. Use "v-model" instead.',
        }]
    },
    {
        filename: 'test.html.twig',
        name: '"mt-select" wrong "v-model:value" binding usage should be replaced with "v-model" [disableFix]',
        options: ['disableFix'],
        code: `
            <template>
                <mt-select v-model:value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-select] The "v-model:value" binding is deprecated. Use "v-model" instead.',
        }]
    },
    {
        name: '"mt-select" wrong "aside" prop usage. It was removed without a replacement',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select aside />
            </template>`,
        output: `
            <template>
                <mt-select  />
            </template>`,
        errors: [{
            message: '[mt-select] The "aside" prop was removed without a replacement.',
        }]
    },
    {
        name: '"mt-select" wrong "aside" prop usage. It was removed without a replacement [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-select aside />
            </template>`,
        errors: [{
            message: '[mt-select] The "aside" prop was removed without a replacement.',
        }]
    },
    {
        name: '"mt-select" wrong "aside" prop usage. It was removed without a replacement [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select aside="true" />
            </template>`,
        output: `
            <template>
                <mt-select  />
            </template>`,
        errors: [{
            message: '[mt-select] The "aside" prop was removed without a replacement.',
        }]
    },
    {
        name: '"mt-select" wrong "aside" prop usage. It was removed without a replacement [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-select aside="true" />
            </template>`,
        errors: [{
            message: '[mt-select] The "aside" prop was removed without a replacement.',
        }]
    },
    {
        name: '"mt-select" wrong "aside" prop usage. It was removed without a replacement [expression binding]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select :aside="true" />
            </template>`,
        output: `
            <template>
                <mt-select  />
            </template>`,
        errors: [{
            message: '[mt-select] The "aside" prop was removed without a replacement.',
        }]
    },
    {
        name: '"mt-select" wrong "aside" prop usage. It was removed without a replacement [expression binding, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-select :aside="true" />
            </template>`,
        errors: [{
            message: '[mt-select] The "aside" prop was removed without a replacement.',
        }]
    },
    {
        name: '"mt-select" options with name/id keys should be replaced with label/value keys',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select :options="[{ name: 'Option A', id: 'optionA' }]" />
            </template>`,
        output: `
            <template>
                <mt-select :options="[{ label: 'Option A', value: 'optionA' }]" />
            </template>`,
        errors: [{
            message: '[mt-select] Replace option object keys "name"/"id" with "label"/"value".',
        }]
    },
    {
        name: '"mt-select" dynamic options with name/id keys should be reported without auto-fix',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select :options="options.map(({ name, id }) => ({ name, id }))" />
            </template>`,
        output: null,
        errors: [{
            message: '[mt-select] Replace option object keys "name"/"id" with "label"/"value".',
        }]
    },
    {
        name: '"mt-select" wrong "default" slot usage. Should be replaced with prop "options" [without declaration]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select>
                    <option value="optionA">Option A</option>
                    <option value="optionB">Option B</option>
                </mt-select>
            </template>`,
        output: `
            <template>
                <mt-select>
                    <!-- TODO Codemod: Remove the "default" slot and use the "options" prop instead -->
<option value="optionA">Option A</option>
                    <option value="optionB">Option B</option>
                </mt-select>
            </template>`,
        errors: [{
            message: '[mt-select] The "default" slot is deprecated. Use the "options" prop instead.',
        }]
    },
    {
        name: '"mt-select" wrong "default" slot usage. Should be replaced with prop "options" [without declaration, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-select>
                    <option value="optionA">Option A</option>
                    <option value="optionB">Option B</option>
                </mt-select>
            </template>`,
        errors: [{
            message: '[mt-select] The "default" slot is deprecated. Use the "options" prop instead.',
        }]
    },
    {
        name: '"mt-select" wrong "default" slot usage. Should be replaced with prop "options" [short syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select>
                    <template #default>
                        <option value="optionA">Option A</option>
                        <option value="optionB">Option B</option>
                    </template>
                </mt-select>
            </template>`,
        output: `
            <template>
                <mt-select>
                    <!-- TODO Codemod: Remove the "default" slot and use the "options" prop instead -->
<template #default>
                        <option value="optionA">Option A</option>
                        <option value="optionB">Option B</option>
                    </template>
                </mt-select>
            </template>`,
        errors: [{
            message: '[mt-select] The "default" slot is deprecated. Use the "options" prop instead.',
        }]
    },
    {
        name: '"mt-select" wrong "default" slot usage. Should be replaced with prop "options" [short syntax, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-select>
                    <template #default>
                        <option value="optionA">Option A</option>
                        <option value="optionB">Option B</option>
                    </template>
                </mt-select>
            </template>`,
        errors: [{
            message: '[mt-select] The "default" slot is deprecated. Use the "options" prop instead.',
        }]
    },
    {
        name: '"mt-select" wrong "label" slot usage. Should be replaced with prop "label" [short syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select>
                    <template #label>
                        My Label
                    </template>
                </mt-select>
            </template>`,
        output: `
            <template>
                <mt-select>
                    <!-- TODO Codemod: Remove the "label" slot and use the "label" prop instead -->
<template #label>
                        My Label
                    </template>
                </mt-select>
            </template>`,
        errors: [{
            message: '[mt-select] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-select" wrong "label" slot usage. Should be replaced with prop "label" [short syntax, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-select>
                    <template #label>
                        My Label
                    </template>
                </mt-select>
            </template>`,
        errors: [{
            message: '[mt-select] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-select" event "update:value" was renamed to "update:mode-value"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select @update:value="onUpdateValue" />
            </template>`,
        output: `
            <template>
                <mt-select @update:model-value="onUpdateValue" />
            </template>`,
        errors: [{
            message: '[mt-select] The "update:value" event is deprecated. Use "update:mode-value" instead.',
        }]
    },
    {
        name: '"mt-select" event "update:value" was renamed to "update:mode-value" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-select @update:value="onUpdateValue" />
            </template>`,
        errors: [{
            message: '[mt-select] The "update:value" event is deprecated. Use "update:mode-value" instead.',
        }]
    },
    {
        name: '"mt-select" event "update:value" was renamed to "update:mode-value" [long syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select v-on:update:value="onUpdateValue" />
            </template>`,
        output: `
            <template>
                <mt-select v-on:update:model-value="onUpdateValue" />
            </template>`,
        errors: [{
            message: '[mt-select] The "update:value" event is deprecated. Use "update:mode-value" instead.',
        }]
    },
    {
        name: '"mt-select" event "update:value" was renamed to "update:mode-value" [long syntax, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-select v-on:update:value="onUpdateValue" />
            </template>`,
        errors: [{
            message: '[mt-select] The "update:value" event is deprecated. Use "update:mode-value" instead.',
        }]
    },
]

module.exports = {
    mtSelectValidTests,
    mtSelectInvalidTests
};
