/**
 * @sw-package framework
 */

import { _overridesMap } from 'src/app/adapter/composition-extension-system';
import { convertOptionsApiOverrideToCompositionApi } from 'src/app/adapter/options-composition-shim';

describe('Options composition shim', () => {
    beforeEach(() => {
        const entries = [...Object.keys(_overridesMap)];
        entries.forEach((key) => {
            delete _overridesMap[key];
        });

        jest.clearAllMocks();
    });
    describe('Deprecation warning:', () => {
        it('should log deprecation warning when shim is activated', () => {
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            convertOptionsApiOverrideToCompositionApi('originalComponent', {
                methods: { foo() {} },
            });

            expect(consoleWarn).toHaveBeenCalledWith(expect.stringContaining('[Deprecation Warning]'));
            expect(consoleWarn).toHaveBeenCalledWith(expect.stringContaining('originalComponent'));
            expect(consoleWarn).toHaveBeenCalledWith(expect.stringContaining('overrideComponentSetup()'));

            consoleWarn.mockRestore();
        });

        it('should include migration docs link in deprecation warning', () => {
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            convertOptionsApiOverrideToCompositionApi('originalComponent', {
                methods: { foo() {} },
            });

            expect(consoleWarn).toHaveBeenCalledWith(
                expect.stringContaining(
                    'https://developer.shopware.com/docs/resources/references/core-reference/administration-reference/composition-api',
                ),
            );

            consoleWarn.mockRestore();
        });
    });
});
