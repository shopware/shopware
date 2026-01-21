/**
 * @sw-package framework
 * This file will act as the replacement for the real lodash-es/debounce module.
 */

module.exports = jest.fn((fn) => {
    // Return the function immediately, bypassing the debounce delay.
    const debouncedFn = fn;
    debouncedFn.flush = jest.fn();
    return debouncedFn;
});