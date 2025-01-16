const RulerTester = require('eslint').RuleTester;
const rule = require('./remove-empty-templates');

const tester = new RulerTester({
    parserOptions: {
        ecmaVersion: 2015,
        sourceType: 'module',
    },
    parser: require.resolve('vue-eslint-parser'),
});

tester.run('remove-empty-templates', rule, {
    valid: [
        {
            name: 'template used for slot',
            filename: 'test.html.twig',
            code: `
                <template>
                    <div>
                        <template #default="{message}">
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </template>
                    </div>
                </template>
            `,
        },
        {
            name: 'template used for slot',
            filename: 'test.html.twig',
            code: `
                <template>
                    <div>
                        <template v-slot:default="{message}">
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </template>
                    </div>
                </template>
            `,
        },
        {
            name: 'template used for condition',
            filename: 'test.html.twig',
            code: `
                <template>
                    <div>
                        <template v-if="!!message">
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </template>
                    </div>
                </template>
            `,
        },
    ],
    invalid: [
        {
            name: 'template without any attributes',
            filename: 'test.html.twig',
            code: `
                <template>
                    <div>
                        <template>
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </template>
                    </div>
                </template>
            `,
            /* eslint-disable */
            output: `
                <template>
                    <div>
                        <h1>Title</h1>
                            <p>{{ message }}</p>
                    </div>
                </template>
            `,
            /* eslint-enable */
            errors: [{
                message: 'remove templates tags with no attributes',
            }],
        },
    ],
});
