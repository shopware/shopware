/**
 * @sw-package framework
 */
import ImageDeprecationPlugin from './index';

describe('build/vite-plugins/image-deprecation', () => {
    beforeAll(() => {
        jest.spyOn(console, 'warn').mockImplementation(() => {});
    });

    afterAll(() => {
        console.warn.mockRestore();
    });

    afterEach(() => {
        console.warn.mockClear();
    });

    it('should be a function with 2 arguments', () => {
        expect(typeof ImageDeprecationPlugin).toBe('function');

        // check that the function has 1 argument (it has a second optional)
        expect(ImageDeprecationPlugin.length).toBe(1);
    });

    it('should return an object with name, enforce and resolveId property', () => {
        const plugin = ImageDeprecationPlugin('/root', []);

        // Identify plugin by name
        expect(plugin).toHaveProperty('name');
        expect(plugin.name).toBe('shopware-vite-plugin-image-deprecation');

        // check for `enforce` property
        expect(plugin).toHaveProperty('enforce');
        expect(plugin.enforce).toBe('pre');

        // check for `resolveId` property
        expect(plugin).toHaveProperty('resolveId');
    });

    it('should warn with a deprecation', () => {
        const plugin = ImageDeprecationPlugin('/root', ['/foo/some.png']);

        // try a resolve
        plugin.resolveId('../some.png', '/root/foo/other/bar.js');

        // check that a warning has been printed to the console
        expect(console.warn).toHaveBeenCalledTimes(1);

        // with including "DEPRECATION" in the warning
        expect(console.warn.mock.calls[0][0]).toMatch(/DEPRECATION:/);
    });

    it("shouldn't warn with a deprecation", () => {
        const plugin = ImageDeprecationPlugin('/root', ['/foo/some.png']);

        // try a resolve (that is not deprecated)
        plugin.resolveId('../some.png', '/root/bar/index.js');

        // check that no warning has been printed to the console
        expect(console.warn).toHaveBeenCalledTimes(0);
    });
});
