import { findMissingExtensionScripts, warnAboutMissingExtensionScripts } from 'src/dev/hot-mode/missing-extension-scripts';

/**
 * @sw-package framework
 */
describe('src/dev/hot-mode/missing-extension-scripts', () => {
    beforeEach(() => {
        jest.spyOn(console, 'warn').mockImplementation();
        document.head.innerHTML = `
            <script src="/_webpack_hot_proxy_/storefront/hot-reloading.js" defer></script>
            <script src="/_webpack_hot_proxy_/advanced-search/advanced-search.js" defer></script>
        `;
    });

    afterEach(() => {
        document.head.innerHTML = '';
    });

    test('finds the entries without a script on the page', () => {
        expect(findMissingExtensionScripts(['advanced-search', 'quick-order'])).toEqual(['quick-order']);
    });

    test('warns with the missing entries and the build command', () => {
        warnAboutMissingExtensionScripts(['advanced-search', 'quick-order', 'b2b-employee']);

        expect(console.warn).toHaveBeenCalledTimes(1);
        expect(console.warn).toHaveBeenCalledWith(expect.stringContaining('quick-order, b2b-employee.'));
        expect(console.warn).toHaveBeenCalledWith(expect.stringContaining('bin/build-storefront.sh'));
    });

    test('does not warn if every entry is loaded', () => {
        warnAboutMissingExtensionScripts(['advanced-search']);

        expect(console.warn).not.toHaveBeenCalled();
    });

    test('checks the entries that the hot mode defines when the module loads', () => {
        // The webpack DefinePlugin replaces the global in hot mode.
        global.__SW_HOT_MODE_EXTENSION_ENTRIES__ = ['advanced-search', 'quick-order'];

        try {
            jest.isolateModules(() => {
                require('src/dev/hot-mode/missing-extension-scripts');
            });
        } finally {
            delete global.__SW_HOT_MODE_EXTENSION_ENTRIES__;
        }

        expect(console.warn).toHaveBeenCalledTimes(1);
        expect(console.warn).toHaveBeenCalledWith(expect.stringContaining('quick-order.'));
    });
});
