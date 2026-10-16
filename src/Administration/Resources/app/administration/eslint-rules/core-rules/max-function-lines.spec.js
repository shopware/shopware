const { RuleTester } = require('eslint');
const rule = require('./max-function-lines');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

const options = [{ max: 3, setupMax: 5 }];

// A function body of `count` statements spans `count + 2` lines
function body(count) {
    return Array.from({ length: count }, (_, index) => `    step${index}();`).join('\n');
}

function exceed(name, lineCount, maxLines) {
    return { messageId: 'exceed', data: { name, lineCount, maxLines } };
}

ruleTester.run('max-function-lines', rule, {
    valid: [
        {
            name: 'allows a function within the limit',
            code: `function load() {\n${body(1)}\n}`,
            options,
        },
        {
            name: 'allows a component setup up to the setup limit',
            code: `export default Shopware.Component.wrapComponentConfig({\nsetup() {\n${body(3)}\n},\n});`,
            options,
        },
        {
            name: 'allows an arrow function setup up to the setup limit',
            code: `export default Shopware.Component.wrapComponentConfig({\nsetup: () => {\n${body(3)}\n},\n});`,
            options,
        },
        {
            name: 'skips blank lines and comments like max-lines-per-function',
            code: `function load() {\n\n// fetch\n${body(1)}\n}`,
            options: [{ max: 3, skipBlankLines: true, skipComments: true }],
        },
    ],
    invalid: [
        {
            name: 'reports a function over the limit',
            code: `function load() {\n${body(2)}\n}`,
            options,
            errors: [exceed("Function 'load'", 4, 3)],
        },
        {
            name: 'reports a component setup over the setup limit',
            code: `export default Shopware.Component.wrapComponentConfig({\nsetup() {\n${body(4)}\n},\n});`,
            options,
            errors: [exceed("Method 'setup'", 6, 5)],
        },
        {
            name: 'applies the normal limit to functions inside setup',
            code: `export default Shopware.Component.wrapComponentConfig({\nsetup() {\nconst load = () => {\n${body(2)}\n};\n},\n});`,
            options: [{ max: 3, setupMax: 8 }],
            errors: [exceed('Arrow function', 4, 3)],
        },
        {
            name: 'applies the normal limit to a setup outside of a component',
            code: `createPlugin({\nsetup() {\n${body(2)}\n},\n});`,
            options,
            errors: [exceed("Method 'setup'", 4, 3)],
        },
    ],
});
