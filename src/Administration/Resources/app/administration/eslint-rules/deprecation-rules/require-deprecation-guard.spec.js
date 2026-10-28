const path = require('path');
const { RuleTester } = require('eslint');
const rule = require('./require-deprecation-guard');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

const guard = "Shopware.Feature.triggerDeprecationOrThrow('V6_8_0_0', 'x is deprecated. Use y instead.');";
const deprecated = '/** @deprecated tag:v6.8.0 - Use y instead. */';
const registeringFile = path.join(__dirname, 'require-deprecation-guard.fixtures', 'index.js');

ruleTester.run('require-deprecation-guard', rule, {
    valid: [
        {
            name: 'a method with the guard as its first statement',
            code: `export default { methods: { ${deprecated} oldMethod() { ${guard} return 1; } } };`,
        },
        {
            name: 'a computed accessor guarding both get and set',
            code: `export default { computed: { ${deprecated} legacy: { get() { ${guard} return 1; }, set(v) { ${guard} } } } };`,
        },
        {
            name: 'an exported function',
            code: `${deprecated} export function oldFunction() { ${guard} }`,
        },
        {
            name: 'a guard reached through Feature directly',
            code: `${deprecated} export function oldFunction() { Feature.triggerDeprecationOrThrow('V6_8_0_0', 'x'); }`,
        },
        {
            name: 'a flag spelled like the version',
            code: `${deprecated} export function oldFunction() { Feature.triggerDeprecationOrThrow('v6.8.0.0', 'x'); }`,
        },
        {
            name: 'a class whose constructor guards',
            code: `${deprecated} export class Old { constructor() { ${guard} } }`,
        },
        {
            name: 'a getter of a class',
            code: `class Shopware { ${deprecated} get State() { ${guard} return 1; } }`,
        },
        {
            name: 'a wrapped function',
            code: `export default { methods: { ${deprecated} onSearch: debounce(function () { ${guard} }, 100) } };`,
        },
        {
            name: 'a component with the deprecated option',
            code: `${deprecated} export default { template, deprecated: { version: 'v6.8.0.0', comment: 'x' } };`,
        },
        {
            name: 'a component deprecated in the file header',
            code: `/**\n * @sw-package framework\n * @deprecated tag:v6.8.0 - x\n */\nimport template from './t';\nexport default { template, deprecated: { version: 'v6.8.0.0', comment: 'x' } };`,
        },
        {
            name: 'a prop with the deprecated option',
            code: `export default { props: { ${deprecated} old: { type: String, deprecated: { version: 'v6.8.0.0', comment: 'x' } } } };`,
        },
        {
            name: 'the registration of a component that has the deprecated option',
            filename: registeringFile,
            code: `${deprecated} Shopware.Component.register('sw-old', () => import('./guarded-component'));`,
        },
        {
            name: 'a value, which has no use to guard',
            code: `${deprecated} export const LEGACY_MAP = { a: 1 };`,
        },
        {
            name: 'a marker on a statement inside a body',
            code: `export default { methods: { load() {\n${deprecated}\nreturn this.legacy(); } } };`,
        },
        {
            name: 'a class that guards every method instead of its constructor',
            code: `${deprecated} export class Old { constructor() { this.x = 1; } run() { ${guard} } stop() { ${guard} } }`,
        },
        {
            name: 'a value computed with a callback',
            code: `${deprecated} export const LEGACY_IDS = items.map((item) => item.id);`,
        },
        {
            name: 'a multi-line computed accessor silenced by the documented disable comment',
            code: `export default { computed: {
                ${deprecated}
                // eslint-disable-next-line rule-to-test/require-deprecation-guard -- core reads it on every render
                legacy: {
                    get() { return 1; },
                },
            } };`,
        },
        {
            name: 'a private declaration',
            code: `/**\n * @private\n * @deprecated tag:v6.8.0 - x\n */\nexport function oldFunction() { return 1; }`,
        },
        {
            name: 'an underscore-prefixed declaration',
            code: `export default { methods: { ${deprecated} _old() { return 1; } } };`,
        },
        {
            name: 'a deprecation that only changes the visibility',
            code: `/** @deprecated tag:v6.8.0 - Will be private */ export default { template };`,
        },
        {
            name: 'a watcher, which core runs for every instance',
            code: `export default { watch: { ${deprecated} value() { return 1; } } };`,
        },
        {
            name: 'a provided function',
            code: `export default { provide() { return { ${deprecated} getOld: () => this.old }; } };`,
        },
        {
            name: 'a plain default-exported object, which is no component',
            code: `${deprecated} export default { getRegistry, register };`,
        },
        {
            name: 'a marker without a tag',
            code: 'export default { methods: { /** @deprecated */ oldMethod() { return 1; } } };',
        },
    ],

    invalid: [
        {
            name: 'a method without a guard',
            code: `export default { methods: { ${deprecated} oldMethod() { return 1; } } };`,
            errors: [{ messageId: 'missingGuard', data: { name: 'oldMethod', flag: 'V6_8_0_0' } }],
        },
        {
            name: 'a guard behind an early return',
            code: `export default { methods: { ${deprecated} oldMethod() { if (!this.x) { return; } ${guard} } } };`,
            errors: [{ messageId: 'missingGuard' }],
        },
        {
            name: 'a guard inside a condition',
            code: `export default { methods: { ${deprecated} oldMethod() { if (this.x) { ${guard} } } } };`,
            errors: [{ messageId: 'missingGuard' }],
        },
        {
            name: 'an arrow function with an expression body',
            code: `export default { computed: { ${deprecated} isLoading: () => store.isLoading } };`,
            errors: [{ messageId: 'missingGuard', data: { name: 'isLoading', flag: 'V6_8_0_0' } }],
        },
        {
            name: 'a computed accessor guarding only get',
            code: `export default { computed: { ${deprecated} legacy: { get() { ${guard} return 1; }, set(v) { this.x = v; } } } };`,
            errors: [{ messageId: 'missingGuard' }],
        },
        {
            name: 'a guard for another major',
            code: `/** @deprecated tag:v6.9.0 - x */ export function oldFunction() { ${guard} }`,
            errors: [{ messageId: 'flagMismatch', data: { name: 'oldFunction', flag: 'V6_9_0_0', version: 'v6.9.0' } }],
        },
        {
            name: 'a class without a guard',
            code: `${deprecated} export class Old { run() {} }`,
            errors: [{ messageId: 'missingClassGuard', data: { name: 'Old', flag: 'V6_8_0_0' } }],
        },
        {
            name: 'a class that guards only some of its methods',
            code: `${deprecated} export class Old { run() { ${guard} } stop() {} }`,
            errors: [{ messageId: 'missingClassGuard', data: { name: 'Old', flag: 'V6_8_0_0' } }],
        },
        {
            name: 'a function local to its module',
            code: `${deprecated} function helper() { return 1; } export default helper;`,
            errors: [{ messageId: 'missingGuard', data: { name: 'helper', flag: 'V6_8_0_0' } }],
        },
        {
            name: 'a function exported through an object',
            code: `${deprecated} function register() { return 1; } export default { register };`,
            errors: [{ messageId: 'missingGuard', data: { name: 'register', flag: 'V6_8_0_0' } }],
        },
        {
            name: 'a multi-line computed accessor, reported on its key',
            code: `export default { computed: {\n${deprecated}\nlegacy: {\nget() { return 1; },\n},\n} };`,
            errors: [{ messageId: 'missingGuard', line: 3 }],
        },
        {
            name: 'every export of a file deprecated in its header',
            code: `/**\n * @sw-package framework\n * @deprecated tag:v6.8.0 - x\n */\nimport x from 'x';\nexport function a() { return x; }\nfunction b() { return 2; }\nexport { b };`,
            errors: [
                { messageId: 'missingGuard', data: { name: 'a', flag: 'V6_8_0_0' } },
                { messageId: 'missingGuard', data: { name: 'b', flag: 'V6_8_0_0' } },
            ],
        },
        {
            name: 'a prop whose deprecated option names another version',
            code: `export default { props: { ${deprecated} old: { type: String, deprecated: { version: 'v6.9.0.0', comment: 'x' } } } };`,
            errors: [{ messageId: 'versionMismatch', data: { name: 'old', version: 'v6.8.0.0' } }],
        },
        {
            name: 'a component whose deprecated option names another version',
            code: `${deprecated} export default { template, deprecated: { version: 'v6.9.0.0', comment: 'x' } };`,
            errors: [{ messageId: 'versionMismatch', data: { name: 'component', version: 'v6.8.0.0' } }],
        },
        {
            name: 'a default-exported function deprecated in the file header',
            code: `/**\n * @sw-package framework\n * @deprecated tag:v6.8.0 - x\n */\nimport a from './a';\nexport default function initState() { return a; }`,
            errors: [{ messageId: 'missingGuard', data: { name: 'initState', flag: 'V6_8_0_0' } }],
        },
        {
            name: 'a component without the deprecated option',
            code: `${deprecated} export default { template };`,
            errors: [{ messageId: 'missingComponentOption', data: { version: 'v6.8.0.0' } }],
        },
        {
            name: 'a component deprecated in the file header without the option',
            code: `/**\n * @sw-package framework\n * @deprecated tag:v6.8.0 - x\n */\nimport template from './t';\nexport default { template };`,
            errors: [{ messageId: 'missingComponentOption' }],
        },
        {
            name: 'a prop without the deprecated option',
            code: `export default { props: { ${deprecated} old: { type: String } } };`,
            errors: [{ messageId: 'missingPropOption', data: { name: 'old', version: 'v6.8.0.0' } }],
        },
        {
            name: 'the registration of a component without the deprecated option',
            filename: registeringFile,
            code: `${deprecated} Shopware.Component.register('sw-old', () => import('./unguarded-component'));`,
            errors: [
                {
                    messageId: 'missingRegisteredComponentOption',
                    data: { version: 'v6.8.0.0', file: path.join('unguarded-component', 'index.js') },
                },
            ],
        },
    ],
});
