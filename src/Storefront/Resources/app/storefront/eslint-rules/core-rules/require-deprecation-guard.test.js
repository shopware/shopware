/**
 * @sw-package framework
 */
const { describe, it } = require('node:test');
const { RuleTester } = require('eslint');
const rule = require('./require-deprecation-guard');

RuleTester.describe = describe;
RuleTester.it = it;

const tester = new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } });
const annotation = '/** @deprecated tag:v6.8.0 - Use the replacement. */';
const guard = 'window.Feature.triggerDeprecationOrThrow("v6.8.0.0", "Use the replacement.");';
const missingGuard = { messageId: 'missingGuard', data: { flag: 'v6.8.0.0' } };

tester.run('require-deprecation-guard', rule, {
    valid: [
        'function supported() {}',
        '/** @param {string} old @deprecated tag:v6.8.0 - Parameter only. */ function supported(old) {}',
        '/**\n * Supported API.\n * @param {string} old @deprecated tag:v6.8.0 - Parameter only.\n */ function supported(old) {}',
        `${annotation} function old() { ${guard} }`,
        `${annotation} export function old() { prepare(); ${guard} }`,
        `${annotation} export default function old() { if (legacy) { ${guard} } }`,
        `${annotation} const old = () => { if (legacy) { prepare(); ${guard} } };`,
        `${annotation} const old = function () { ${guard} };`,
        `const api = { ${annotation} old() { ${guard} } };`,
        `class Api { ${annotation} old() { prepare(); if (legacy) { ${guard} } } }`,
        `class Api { ${annotation} static old() { ${guard} } }`,
        `class Api { ${annotation} get old() { ${guard} return value; } }`,
        `class Api { ${annotation} set old(value) { if (legacy) { ${guard} } this.value = value; } }`,
        `${annotation} export default class OldPlugin extends Plugin { init() { prepare(); ${guard} } }`,
        `${annotation} class OldPlugin extends Plugin { init() { if (legacy) { ${guard} } } }`,
        'class Api { /** @private @deprecated tag:v6.8.0 */ old() {} }',
        `class Api { static options = { ${annotation} oldDefault: null }; }`,
        `class Api { ${annotation} oldField = null; }`,
        `${annotation} import Old from 'old';`,
        `${annotation} class OldHelper { static supported() {} }`,
        '/** @deprecated tag:v6.8.0.0 */ function old() { window.Feature.triggerDeprecationOrThrow("v6.8.0.0", message); }',
        `import Flags from 'src/helper/feature.helper'; ${annotation} function old() { Flags.triggerDeprecationOrThrow('v6.8.0.0', 'Use new().'); }`,
        `import Flags from './helper/feature.helper.js'; ${annotation} const old = () => Flags.triggerDeprecationOrThrow('v6.8.0.0', 'Use new().');`,
        `${annotation} function old() { Flags.triggerDeprecationOrThrow('v6.8.0.0', 'Use new().'); } import Flags from 'src/helper/feature.helper';`,
        `function callbackFactory() { return () => { ${guard} }; }`,
    ],
    invalid: [
        { code: `${annotation} function old() { performRequest(); }`, errors: [missingGuard] },
        { code: `${annotation} export default function old() {}`, errors: [missingGuard] },
        { code: `${annotation} export const old = () => {};`, errors: [missingGuard] },
        { code: `const api = { ${annotation} old: function () {} };`, errors: [missingGuard] },
        { code: `class Api { ${annotation} set old(value) { this.value = value; } }`, errors: [missingGuard] },
        { code: `${annotation} export default class OldPlugin extends Plugin { init() {} }`, errors: [missingGuard] },
        { code: `${annotation} function old() { window.Feature.triggerDeprecationOrThrow('v6.9.0.0', 'Use new().'); }`, errors: [missingGuard] },
        { code: `${annotation} function old() { window.Feature.triggerDeprecationOrThrow('v6.8.0', 'Use new().'); }`, errors: [missingGuard] },
        { code: `${annotation} function old() { window.Feature.triggerDeprecationOrThrow('v6.8.0.0'); }`, errors: [missingGuard] },
        { code: `${annotation} function old() { window.Feature.triggerDeprecationOrThrow('v6.8.0.0', ' '); }`, errors: [missingGuard] },
        { code: `${annotation} function old() { other.triggerDeprecationOrThrow('v6.8.0.0', 'Use new().'); }`, errors: [missingGuard] },
        { code: `import Flags from 'src/helper/feature.helper'; ${annotation} function old(Flags) { Flags.triggerDeprecationOrThrow('v6.8.0.0', 'Use new().'); }`, errors: [missingGuard] },
        { code: `${annotation} function old(window) { ${guard} }`, errors: [missingGuard] },
        { code: `${annotation} function old() { nested(() => { ${guard} }); }`, errors: [missingGuard] },
        { code: `${annotation} function old() { function nested() { ${guard} } }`, errors: [missingGuard] },
        { code: `${annotation} function old() { return class Nested { init() { ${guard} } }; }`, errors: [missingGuard] },
        { code: `${annotation} function old() {} function unrelated() { ${guard} }`, errors: [missingGuard] },
        { code: '/** @deprecated tag:v99.0.0 */ function old() {}', errors: [{ messageId: 'unknownFlag', data: { flag: 'v99.0.0.0' } }] },
        { code: guard, errors: [{ messageId: 'moduleGuard' }] },
        { code: `if (legacy) { ${guard} }`, errors: [{ messageId: 'moduleGuard' }] },
    ],
});
