/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-explicit-any */

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
    describe('Unsupported features:', () => {
        it('accepts a custom render function for definition preparation', () => {
            const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            convertOptionsApiOverrideToCompositionApi('originalComponent', {
                render() {
                    return null;
                },
                methods: { foo() {} },
            });

            expect(consoleError).not.toHaveBeenCalledWith(
                expect.stringContaining('Custom render() functions are not supported'),
            );

            consoleError.mockRestore();
            consoleWarn.mockRestore();
        });

        it('should accept inherited Options configuration', () => {
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            const baseComponent = { methods: { base() {} } };
            convertOptionsApiOverrideToCompositionApi('originalComponent', {
                extends: baseComponent as any,
                methods: { foo() {} },
            });

            expect(consoleWarn).not.toHaveBeenCalledWith(expect.stringContaining('"extends" is not supported'));

            consoleWarn.mockRestore();
        });
    });
});
