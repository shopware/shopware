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

/* jsdom does not lay out anything, so it implements none of the scrolling APIs. Specs that assert
 * the scrolling itself replace this with a spy, the no-op only keeps the callers from throwing.
 */
if (typeof globalThis.Element !== 'undefined' && typeof globalThis.Element.prototype.scrollIntoView === 'undefined') {
    globalThis.Element.prototype.scrollIntoView = () => {};
}
