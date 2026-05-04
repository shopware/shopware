/**
 * @sw-package framework
 *
 * Polyfills for globals missing in the jsdom test environment.
 * ESLint 9's RuleTester requires structuredClone which jsdom 21 doesn't provide.
 */
if (typeof globalThis.structuredClone === 'undefined') {
    const v8 = require('v8');
    globalThis.structuredClone = (value) => v8.deserialize(v8.serialize(value));
}
