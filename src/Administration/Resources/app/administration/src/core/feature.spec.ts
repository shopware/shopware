/**
 * @sw-package framework
 */

import Feature from 'src/core/feature';

describe('src/core/feature', () => {
    const originalFlags = { ...Feature.flags };
    const originalEmitDeprecations = Feature.emitDeprecations;

    afterEach(() => {
        Feature.flags = { ...originalFlags };
        Feature.emitDeprecations = originalEmitDeprecations;
        jest.restoreAllMocks();
    });

    describe('isActive', () => {
        it('reports an unknown flag as inactive', () => {
            expect(Feature.isActive('V6_9_0_0')).toBe(false);
        });

        it('resolves a flag case insensitively', () => {
            Feature.init({ v6_9_0_0: true });

            expect(Feature.isActive('V6_9_0_0')).toBe(true);
            expect(Feature.isActive('v6_9_0_0')).toBe(true);
        });
    });

    describe('triggerDeprecationOrThrow', () => {
        it('throws once the major flag is active', () => {
            Feature.init({ V6_9_0_0: true });

            expect(() => Feature.triggerDeprecationOrThrow('V6_9_0_0', 'oldMethod is deprecated')).toThrow(
                'Tried to access deprecated functionality: oldMethod is deprecated',
            );
        });

        it('warns with the message and a call site while the major flag is inactive', () => {
            Feature.emitDeprecations = true;
            const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            Feature.triggerDeprecationOrThrow('V6_9_0_0', 'a unique first message');

            expect(warn).toHaveBeenCalledTimes(1);
            expect(warn.mock.calls[0].join(' ')).toContain('a unique first message');
            expect(warn.mock.calls[0].join(' ')).toContain('at ');
        });

        it('warns only once per message, because a deprecated getter can be read on every render', () => {
            Feature.emitDeprecations = true;
            const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            Feature.triggerDeprecationOrThrow('V6_9_0_0', 'a unique repeated message');
            Feature.triggerDeprecationOrThrow('V6_9_0_0', 'a unique repeated message');

            expect(warn).toHaveBeenCalledTimes(1);
        });

        it('stays silent when deprecation warnings are suppressed', () => {
            Feature.emitDeprecations = false;
            const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            Feature.triggerDeprecationOrThrow('V6_9_0_0', 'a unique suppressed message');

            expect(warn).not.toHaveBeenCalled();
        });

        it('throws even when deprecation warnings are suppressed', () => {
            Feature.emitDeprecations = false;
            Feature.init({ V6_9_0_0: true });

            expect(() => Feature.triggerDeprecationOrThrow('V6_9_0_0', 'still guarded')).toThrow(
                'Tried to access deprecated functionality: still guarded',
            );
        });

        it('suppresses warnings under Jest by default, so an unexpected console.warn cannot fail a suite', () => {
            expect(originalEmitDeprecations).toBe(false);
        });
    });
});
