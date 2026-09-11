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
    describe('Extended unsupported features:', () => {
        it('accepts local component registrations', () => {
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            convertOptionsApiOverrideToCompositionApi('originalComponent', {
                components: { SomeComponent: {} } as any,
                methods: { foo() {} },
            });

            const relevantWarnings = consoleWarn.mock.calls.filter(
                (call) => typeof call[0] === 'string' && call[0].includes('"components" is not supported'),
            );
            expect(relevantWarnings).toHaveLength(0);

            consoleWarn.mockRestore();
        });

        it('should accept the provide option', () => {
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            convertOptionsApiOverrideToCompositionApi('originalComponent', {
                provide: { someKey: 'someValue' } as any,
                methods: { foo() {} },
            });

            const relevantWarnings = consoleWarn.mock.calls.filter(
                (call) => typeof call[0] === 'string' && call[0].includes('"provide" is not supported'),
            );
            expect(relevantWarnings).toHaveLength(0);

            consoleWarn.mockRestore();
        });

        it('should warn when override uses template option', () => {
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            convertOptionsApiOverrideToCompositionApi('originalComponent', {
                template: '<div>override</div>',
                methods: { foo() {} },
            });

            const relevantWarnings = consoleWarn.mock.calls.filter(
                (call) => typeof call[0] === 'string' && call[0].includes('"template" is not supported'),
            );
            expect(relevantWarnings).toHaveLength(1);

            consoleWarn.mockRestore();
        });

        it('accepts component definition options together', () => {
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            convertOptionsApiOverrideToCompositionApi('originalComponent', {
                components: { Foo: {} } as any,
                directives: { focus: {} } as any,
                emits: ['foo'] as any,
                methods: { foo() {} },
            });

            const shimWarnings = consoleWarn.mock.calls.filter(
                (call) => typeof call[0] === 'string' && call[0].includes('is not supported by the compatibility shim'),
            );
            expect(shimWarnings).toHaveLength(0);

            consoleWarn.mockRestore();
        });
    });
});
