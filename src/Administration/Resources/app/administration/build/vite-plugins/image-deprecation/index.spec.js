/**
 * @sw-package framework
 */
import ImageDeprecationPlugin from './index';
import deprecatedList from './deprecated-list';
import assets from '../../../src/app/deprecation-registry/definitions/assets';

describe('build/vite-plugins/image-deprecation', () => {
    beforeEach(() => {
        // Setup spy before each test since restoreMocks: true in jest.config.js
        // automatically restores mocks after each test
        jest.spyOn(console, 'warn').mockImplementation(() => {});
    });

    afterEach(() => {
        console.warn.mockRestore();
    });

    it('should be a function with 2 arguments', () => {
        expect(typeof ImageDeprecationPlugin).toBe('function');

        // check that the function has 1 argument (it has a second optional)
        expect(ImageDeprecationPlugin).toHaveLength(1);
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
        // an image without a registry migration still gets guidance
        expect(console.warn.mock.calls[0][0]).toContain('Replace it before it is removed');
    });

    it("shouldn't warn with a deprecation", () => {
        const plugin = ImageDeprecationPlugin('/root', ['/foo/some.png']);

        // try a resolve (that is not deprecated)
        plugin.resolveId('../some.png', '/root/bar/index.js');

        // check that no warning has been printed to the console
        expect(console.warn).toHaveBeenCalledTimes(0);
    });

    it('appends the registry migration description to the warning', () => {
        const plugin = ImageDeprecationPlugin('/root');

        plugin.resolveId('../static/img/sw-login-background.png', '/root/src/foo.js');

        expect(console.warn).toHaveBeenCalledTimes(1);
        expect(console.warn.mock.calls[0][0]).toMatch(/DEPRECATION:/);
        expect(console.warn.mock.calls[0][0]).toContain('WebP variants');
    });

    it('builds the default deprecated list from the asset migration files of the registry', () => {
        expect(deprecatedList).toEqual(assets.flatMap((migration) => migration.files ?? []));
        expect(deprecatedList).toContain('/static/img/sw-login-background.png');
    });
});
