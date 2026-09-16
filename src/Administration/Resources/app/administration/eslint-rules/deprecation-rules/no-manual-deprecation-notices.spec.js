const { RuleTester } = require('eslint');
const rule = require('./no-manual-deprecation-notices');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

ruleTester.run('no-manual-deprecation-notices', rule, {
    valid: [
        {
            name: 'a log that is not about a deprecation',
            code: "console.warn('sw-number-field', 'Provided prop digits must be of type integer');",
        },
        {
            name: 'the guard itself',
            code: "Shopware.Feature.triggerDeprecationOrThrow('V6_8_0_0', 'x is deprecated. Use y instead.');",
        },
        {
            name: 'a log method that is neither warn nor error',
            code: "console.log('sw-tabs is deprecated');",
        },
        {
            name: 'a computed member access the rule cannot resolve',
            code: "logger[method]('sw-tabs is deprecated');",
        },
    ],

    invalid: [
        {
            name: 'console.warn carrying a deprecation notice',
            code: "console.warn('The $tc function is deprecated. Use $t instead.');",
            errors: [{ messageId: 'manualNotice' }],
        },
        {
            name: 'console.error carrying a deprecation notice',
            code: "console.error('sw-tabs is deprecated and will be removed.');",
            errors: [{ messageId: 'manualNotice' }],
        },
        {
            name: 'the debug utils through the global',
            code: "Shopware.Utils.debug.warn('sw-loader', 'The old usage of \"sw-loader\" is deprecated.');",
            errors: [{ messageId: 'manualNotice' }],
        },
        {
            name: 'a destructured warn',
            code: `
const { warn } = Shopware.Utils.debug;
warn('sw-popover', 'The "resizeWidth" prop is deprecated.');
            `,
            errors: [{ messageId: 'manualNotice' }],
        },
        {
            name: 'the notice assembled by concatenation',
            code: `
console.warn(
    'sw-entity-listing: The "items" prop is deprecated ' +
        'and will be removed in v6.8.0.',
);
            `,
            errors: [{ messageId: 'manualNotice' }],
        },
        {
            name: 'the notice in a template literal',
            code: 'console.warn(`Block "${blockName}" uses a deprecated Twig override.`);',
            errors: [{ messageId: 'manualNotice' }],
        },
        {
            name: 'the notice spread from an array built above the call',
            code: `
const debugArgs = [
    'CORE',
    \`The extension "\${name}" uses a deprecated position identifier.\`,
];
Shopware.Utils.debug.error(...debugArgs);
            `,
            errors: [{ messageId: 'manualNotice' }],
        },
        {
            name: 'the notice chosen by a conditional',
            code: "console.warn(isOld ? 'this API is deprecated' : 'all good');",
            errors: [{ messageId: 'manualNotice' }],
        },
    ],
});
