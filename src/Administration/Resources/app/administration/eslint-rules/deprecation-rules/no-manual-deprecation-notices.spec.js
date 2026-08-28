/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-manual-deprecation-notices');

const ruleTester = new RuleTester({
    languageOptions: { ecmaVersion: 2022 },
});

ruleTester.run('no-manual-deprecation-notices', rule, {
    valid: [
        {
            name: 'allows the feature-aware deprecation helper',
            code: `Shopware.Feature.triggerDeprecationOrThrow('V6_9_0_0', 'oldMethod() is deprecated.');`,
        },
        {
            name: 'allows unrelated warnings',
            code: `console.warn('Failed to load the plugin.');`,
        },
        {
            name: 'allows messages about the deprecation plugin itself',
            code: `warn('Deprecation Plugin', 'This plugin is already installed');`,
        },
    ],
    invalid: [
        {
            name: 'rejects console deprecation warnings',
            code: `console.warn('[Shopware Deprecation] Use replacement() instead.');`,
            errors: [{ messageId: 'manualNotice' }],
        },
        {
            name: 'rejects debug deprecation warnings assembled from literals',
            code: `Shopware.Utils.debug.warn('Component', 'The old prop is ' + 'deprecated.');`,
            errors: [{ messageId: 'manualNotice' }],
        },
        {
            name: 'rejects deprecation errors stored in a local argument array',
            code: `
                const debugArgs = ['CORE', 'The requested data set is deprecated.'];
                Shopware.Utils.debug.error(...debugArgs);
            `,
            errors: [{ messageId: 'manualNotice' }],
        },
        {
            name: 'rejects destructured warning helpers',
            code: `warn('Component', \`The component is deprecated.\`);`,
            errors: [{ messageId: 'manualNotice' }],
        },
    ],
});
