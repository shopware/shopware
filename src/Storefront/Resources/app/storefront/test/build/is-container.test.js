const isContainer = require('../../build/is-container');

/**
 * @sw-package framework
 */
describe('is-container', () => {
    test.each([
        ['Docker', '/.dockerenv'],
        ['Podman', '/run/.containerenv'],
    ])('detects %s', (_, markerFile) => {
        expect(isContainer((file) => file === markerFile)).toBe(true);
    });

    test('returns false without a marker file', () => {
        expect(isContainer(() => false)).toBe(false);
    });
});
