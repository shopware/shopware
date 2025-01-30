const RulerTester = require('eslint').RuleTester;
const rule = require('./replace-top-level-blocks-to-extends');

const tester = new RulerTester({
    parserOptions: {
        ecmaVersion: 2015,
        sourceType: 'module',
    },
    parser: require.resolve('vue-eslint-parser'),
});

tester.run('replace-top-level-blocks-to-extends', rule, {
    valid: [
        {
            name: 'top-level block with extends attribute and inner block',
            filename: 'test.html.twig',
            code: `
                <template>
                    <sw-block extends="block-1">
                        <div>
                            <span>Title</span>
                            <sw-block name="block-2" :data="$dataScope"></sw-block>
                        </div>
                    </sw-block>
                </template>
            `,
        },
        {
            name: 'top-level block defining a non existing block',
            filename: 'test.html.twig',
            code: `
                <template>
                    <sw-block name="block-1" :data="$dataScope">
                        <div>
                            <span>Title</span>
                            <sw-block name="block-2" :data="$dataScope"></sw-block>
                        </div>
                    </sw-block>
                </template>
            `,
        },
    ],
    invalid: [
        {
            name: 'top-level block without extends attribute',
            filename: 'test.html.twig',
            code: `
                <template>
                    <sw-block name="sw_desktop_content" :data="$dataScope">
                        <div>
                            <span>Title</span>
                            <sw-block name="block-2" :data="$dataScope"></sw-block>
                        </div>
                    </sw-block>
                </template>
            `,
            /* eslint-disable */
            output: `
                <template>
                    <sw-block extends="sw_desktop_content" >
                        <div>
                            <span>Title</span>
                            <sw-block name="block-2" :data="$dataScope"></sw-block>
                        </div>
                    </sw-block>
                </template>
            `,
            /* eslint-enable */
            errors: [{
                message: 'Top-level <sw-block> should use the `extends` prop instead of the `name` prop',
            }],
        },
    ],
});
