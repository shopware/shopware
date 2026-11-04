// Steps that run only on elements the component conversion produced.
const registryDrivenValidTests = [
    {
        name: 'registry-driven mt-checkbox converted from sw-checkbox-field keeps its label slot',
        filename: 'test.html.twig',
        code: `
            <template>
                <!-- TODO Codemod: Converted from sw-checkbox-field - please check if everything works correctly -->
                <mt-checkbox>
                    <template #label>Accept the <a href="#">terms</a></template>
                </mt-checkbox>
            </template>`,
    },
    {
        name: 'registry-driven mt-checkbox keeps its native label slot',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox>
                    <template #label>Accept</template>
                </mt-checkbox>
            </template>`,
    },
];

const registryDrivenInvalidTests = [
    {
        name: 'registry-driven mt-text-field keeps v-model modifiers when v-model:value becomes v-model',
        filename: 'test.html.twig',
        code: '<template><mt-text-field v-model:value.lazy.trim="myValue" /></template>',
        output: '<template><mt-text-field v-model.lazy.trim="myValue" /></template>',
        errors: [
            {
                message: '[mt-text-field] The "v-model:value" binding is deprecated. Use "v-model" instead.',
            },
        ],
    },
    {
        name: 'registry-driven mt-progress-bar does not fix value when model-value already exists',
        filename: 'test.html.twig',
        code: '<template><mt-progress-bar model-value="new" value="old" /></template>',
        output: null,
        errors: [
            {
                message: '[mt-progress-bar] The "value" API is deprecated. Use "model-value" instead.',
            },
        ],
    },
    {
        name: 'registry-driven sw-entity-listing does not fix items when data-source already exists',
        filename: 'test.html.twig',
        code: '<template><sw-entity-listing :data-source="newValue" :items="oldValue" /></template>',
        output: null,
        errors: [
            {
                message: '[sw-entity-listing] The "items" API is deprecated. Use "data-source" instead.',
            },
        ],
    },
];

module.exports = {
    valid: registryDrivenValidTests,
    invalid: registryDrivenInvalidTests,
};
