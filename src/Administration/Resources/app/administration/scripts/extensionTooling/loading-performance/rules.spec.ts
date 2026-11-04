/**
 * @sw-package framework
 */
import { RuleTester } from 'eslint';
import type { Rule } from 'eslint';
import { parser } from 'typescript-eslint';
import { noEagerComponentImports, noEagerComponentGlob } from './rules';

const tester = new RuleTester({
    languageOptions: {
        parser,
        ecmaVersion: 'latest',
        sourceType: 'module',
        globals: { Shopware: 'readonly', window: 'readonly', globalThis: 'readonly' },
    },
});

const lazyImports = [
    "Shopware.Component.register('x', () => {});",
    "import config from './config'; Shopware.Component.register('x', () => { class Nested { load() { return config; } } return import('./other'); });",
    "Shopware.Component.register('x', () => Promise.resolve(import('./config')));",
    "function configure(Promise) { Shopware.Component.register('x', () => Promise.resolve(value)); }",
    "import config from './config'; function register(window) { window.Shopware.Component.register('x', () => config); }",
    "import config from './config'; const A = B; const B = A; A.register('x', () => config);",
    "const A = B; const B = A; Shopware.Component.register('x', () => A);",
    "Shopware.Component.register('x', () => import('./config'));",
    "const { Component } = Shopware; Component.override('x', async () => await import('./config'));",
    "const Registry = Shopware.Component; Registry.extend('x', 'base', function() { return import('./config'); });",
    "Shopware.Component.register('x', () => { const pending = import('./config'); return pending; });",
    "Shopware.Component.register('x', () => import('./config').then(module => module.default));",
    "const loader = () => import('./config'); Shopware.Component.register('x', loader);",
    "function loader() { return import('./config'); } Shopware.Component.register('x', loader);",
    "Shopware.Component.register('x', () => enabled ? import('./a') : import('./b'));",
    "import loader from './loader'; Shopware.Component.register('x', loader);",
    "Shopware.Component.register('x', () => resolveLicensedConfig());",
    "Shopware.Component.register('x', { computed: { label: () => 'Small override' } });",
    "const config = { template: '<div />' }; Shopware.Component.register('x', () => config);",
    "import config from './config'; const Component = OtherLibrary; Component.register('x', () => config);",
    "import config from './config'; function register(Shopware) { Shopware.Component.register('x', () => config); }",
    "import config from './config'; function register(Component) { Component.register('x', () => config); }",
    "import config from './config'; Component.register('x', () => config);",
    "import config from './config'; const { Component } = OtherLibrary; Component.register('x', () => config);",
    "import config from './config'; let Registry = Shopware.Component; Registry = OtherLibrary; Registry.register('x', () => config);",
    "import config from './config'; Shopware.Component.register('x', () => { function unused() { return config; } return import('./other'); });",
    "import type Config from './config'; Shopware.Component.register('x', () => Config);",
    "import { type Config } from './config'; Shopware.Component.register('x', () => Config);",
    "import config from './config'; Shopware.Other.register('x', () => config);",
    "Shopware.Component.extend('x', 'base');",
];

const eagerImports = [
    "import * as implementation from './config'; Shopware.Component.register('x', () => implementation.default);",
    "const pending = Promise.resolve(import('./config')); Shopware.Component.register('x', () => pending);",
    "import config from './config'; Shopware.Component.register('x', () => Promise.resolve(config));",
    "import config from './config'; Shopware.Component.register('x', () => { switch (kind) { case 'a': return config; default: return import('./other'); } });",
    "import config from './config'; Shopware.Component.register('x', () => { try { return config; } catch (error) { return import('./other'); } finally { cleanup(); } });",
    "import config from './config'; Shopware.Component.register('x', () => { for (let n = 0; n < 1; n++) { return config; } });",
    "import config from './config'; Shopware.Component.register('x', () => { while (enabled) { return config; } });",
    "import config from './config'; Shopware.Component.register('x', () => { do { return config; } while (enabled); });",
    "import config from './config'; Shopware.Component.register('x', () => { for (const key in options) { return config; } });",
    "import config from './config'; Shopware.Component.register('x', () => { for (const item of options) { return config; } });",
    "import config from './config'; Shopware.Component.register('x', () => { label: { return config; } });",
    "let pending = import('./config'); Shopware.Component.register('x', () => pending);",
    "var Registry = Shopware.Component; const pending = import('./config'); Registry.register('x', () => pending);",
    "Shopware.Component.register('x', import('./config'));",
    "const pending = import('./config'); Shopware.Component.register('x', () => pending);",
    "const pending = import('./config'); Shopware.Component.register('x', async () => await pending);",
    "const pending = import('./config').then(module => module.default); Shopware.Component.register('x', () => pending);",
    "const pending = import('./config'); const alias = pending; Shopware.Component.register('x', () => alias);",
    "import config from './config'; Shopware.Component.register('x', () => config);",
    "import config from './config'; Shopware.Component.override('x', function () { return config; });",
    "import config from './config'; Shopware.Component.extend('x', 'base', () => config);",
    "import config from './config'; const { Component: Registry } = Shopware; Registry.override('x', () => config);",
    "import config from './config'; const SW = Shopware; const Registry = SW.Component; Registry.register('x', () => config);",
    "import config from './config'; const { Component: { register: install } } = Shopware; install('x', () => config);",
    "import config from './config'; const install = Shopware.Component.register; install('x', () => config);",
    "import config from './config'; window.Shopware.Component.register('x', () => config);",
    "import config from './config'; globalThis.Shopware.Component['register']('x', () => config);",
    "import config from './config'; Shopware.Component.register('x', () => { if (enabled) return config; return import('./other'); });",
    "import config from './config'; const loader = () => config; Shopware.Component.register('x', loader);",
    "import config from './config'; function loader() { return config; } Shopware.Component.register('x', loader);",
    "import config from './config'; Shopware.Component.register('x', () => { const alias = config; return alias; });",
    "import config from './config'; Shopware.Component.register('x', () => config as ComponentConfig);",
    "import config from './config'; Shopware.Component.register('x', () => config satisfies ComponentConfig);",
    "import config from './component.vue'; Shopware.Component.register('x', config);",
];

tester.run('no-eager-component-imports', noEagerComponentImports as unknown as Rule.RuleModule, {
    valid: lazyImports,
    invalid: eagerImports.map((code) => ({ code, errors: [{ messageId: 'eager' }] })),
});

tester.run('no-eager-component-glob', noEagerComponentGlob as unknown as Rule.RuleModule, {
    valid: [
        "const components = import.meta.glob('./components/*.ts');",
        "import.meta.glob('./components/*.ts', { eager: false });",
        "import.meta.glob('./services/*.ts', { eager: true });",
        "import.meta.glob('./snippet/*.json', { eager: true });",
        "import.meta.glob('./components/**/*.override.vue', { eager: true });",
        "import.meta.glob(['./components/**/*.override.vue', '!./components/excluded.override.vue'], { eager: true });",
        "import.meta.glob('./components/*.ts', { eager: true, ...options });",
        "import.meta.glob('./components/*.ts', { eager: enabled });",
        "import.meta.glob('./components/*.ts', options);",
        "other.glob('./components/*.ts', { eager: true });",
        "import.meta.glob(['./services/*.ts', '!./components/*.ts'], { eager: true });",
    ],
    invalid: [
        "import.meta.glob('./components/*.ts', { eager: true });",
        "import.meta.glob('./component/*.js', { eager: true });",
        "import.meta.glob('./pages/*.ts', { eager: true });",
        "import.meta.glob('./view/*.js', { eager: true });",
        "import.meta.glob('./*.vue', { eager: true });",
        "import.meta.glob(['./services/*.ts', './components/*.js'], { eager: true });",
        "import.meta['glob']('./components/*.ts', { 'eager': true });",
        "import.meta.glob('./components/*.ts', { ...options, eager: true });",
        'import.meta.glob(`./components/*.ts`, { eager: true });',
        "import.meta.globEager('./components/*.ts');",
    ].map((code) => ({ code, errors: [{ messageId: 'eager' }] })),
});
