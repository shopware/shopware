const RulerTester = require('eslint').RuleTester;
const rule = require('./move-v-if-conditions-to-blocks');

const tester = new RulerTester({
    parserOptions: {
        ecmaVersion: 2015,
        sourceType: 'module',
    },
    parser: require.resolve('vue-eslint-parser'),
});

tester.run('move-v-if-conditions-to-blocks', rule, {
    valid: [
        {
            name: 'block has multiple children',
            filename: 'test.html.twig',
            code: `
                <template>
                    <sw-block name="block-name">
                        <template v-if="condition">
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </template>
                        <template v-else-if="condition2">
                            <h1>Title 2</h1>
                            <p>{{ message2 }}</p>
                        </template>
                        <template v-else>
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
            name: 'block has only one child template with v-if',
            filename: 'test.html.twig',
            code: `
                <template>
                    <sw-block name="block-name">
                        <template v-if="condition">
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </template>
                    </sw-block>
                </template>
            `,
            /* eslint-disable */
            output: `
                <template>
                    <sw-block name="block-name" v-if="condition" >
                        <template >
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </template>
                    </sw-block>
                </template>
            `,
            /* eslint-enable */
            errors: [{
                message: '<sw-block> with single child should move v-if, v-else or v-else-if attributes to the <sw-block> element',
            }],
        },
        {
            name: 'block has only one child template with v-else-if',
            filename: 'test.html.twig',
            code: `
                <template>
                    <sw-block name="block-name">
                        <template v-else-if="condition">
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </template>
                    </sw-block>
                </template>
            `,
            /* eslint-disable */
            output: `
                <template>
                    <sw-block name="block-name" v-else-if="condition" >
                        <template >
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </template>
                    </sw-block>
                </template>
            `,
            /* eslint-enable */
            errors: [{
                message: '<sw-block> with single child should move v-if, v-else or v-else-if attributes to the <sw-block> element',
            }],
        },
        {
            name: 'block has only one child template with v-else',
            filename: 'test.html.twig',
            code: `
                <template>
                    <sw-block name="block-name">
                        <template v-else>
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </template>
                    </sw-block>
                </template>
            `,
            /* eslint-disable */
            output: `
                <template>
                    <sw-block name="block-name" v-else >
                        <template >
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </template>
                    </sw-block>
                </template>
            `,
            /* eslint-enable */
            errors: [{
                message: '<sw-block> with single child should move v-if, v-else or v-else-if attributes to the <sw-block> element',
            }],
        },
    ],
});
