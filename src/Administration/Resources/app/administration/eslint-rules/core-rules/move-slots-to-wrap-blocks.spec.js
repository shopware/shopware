const RulerTester = require('eslint').RuleTester;
const rule = require('./move-slots-to-wrap-blocks');

const tester = new RulerTester({
    parserOptions: {
        ecmaVersion: 2015,
        sourceType: 'module',
    },
    parser: require.resolve('vue-eslint-parser'),
});

tester.run('move-slots-to-wrap-blocks', rule, {
    valid: [
        {
            name: 'block has multiple children',
            filename: 'test.html.twig',
            code: `
                <template>
                    <sw-block name="block-name">
                        <template #default>
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </template>
                        <template #body>
                            <h1>Title 2</h1>
                            <p>{{ message2 }}</p>
                        </template>
                        <template #footer>
                            <h1>Title 3</h1>
                            <p>{{ message3 }}</p>
                        </template>
                    </sw-block>
                </template>
            `,
        },
    ],
    invalid: [
        {
            name: 'block has only one child template with slot',
            filename: 'test.html.twig',
            code: `
                <template>
                    <sw-block name="block-name">
                        <template #default>
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </template>
                    </sw-block>
                </template>
            `,
            /* eslint-disable */
            output: `
                <template>
                    <template #default>
                        <sw-block name="block-name">
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </sw-block>
                    </template>
                </template>
            `,
            /* eslint-enable */
            errors: [{
                message: '<sw-block> with single child should move v-slot attribute to the <sw-block> element',
            }],
        },
        {
            name: 'block has only one child template with v-slot',
            filename: 'test.html.twig',
            code: `
                <template>
                    <sw-block name="block-name">
                        <template v-slot:default>
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </template>
                    </sw-block>
                </template>
            `,
            /* eslint-disable */
            output: `
                <template>
                    <template v-slot:default>
                        <sw-block name="block-name">
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </sw-block>
                    </template>
                </template>
            `,
            /* eslint-enable */
            errors: [{
                message: '<sw-block> with single child should move v-slot attribute to the <sw-block> element',
            }],
        },
        {
            name: 'block has only one child template with scoped slot',
            filename: 'test.html.twig',
            code: `
                <template>
                    <sw-block name="block-name">
                        <template #body="{prop1, prop2}">
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </template>
                    </sw-block>
                </template>
            `,
            /* eslint-disable */
            output: `
                <template>
                    <template #body="{prop1, prop2}">
                        <sw-block name="block-name">
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </sw-block>
                    </template>
                </template>
            `,
            /* eslint-enable */
            errors: [{
                message: '<sw-block> with single child should move v-slot attribute to the <sw-block> element',
            }],
        },
    ],
});
