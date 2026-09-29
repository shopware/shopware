/**
 * @sw-package framework
 */

import Feature from 'src/core/feature';

describe('src/core/feature', () => {
    const originalFlags = { ...Feature.flags };

    afterEach(() => {
        Feature.flags = { ...originalFlags };
        jest.restoreAllMocks();
    });

    describe('triggerDeprecationOrThrow', () => {
        it('throws once the major flag is active', () => {
            Feature.init({ V6_9_0_0: true });

            expect(() => Feature.triggerDeprecationOrThrow('V6_9_0_0', 'oldMethod() is deprecated.')).toThrow(
                'Tried to access deprecated functionality: oldMethod() is deprecated.',
            );
        });

        it('warns once per message while the major flag is inactive', () => {
            const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            Feature.triggerDeprecationOrThrow('V6_9_0_0', 'otherMethod() is deprecated.');
            Feature.triggerDeprecationOrThrow('V6_9_0_0', 'otherMethod() is deprecated.');

            expect(warn).toHaveBeenCalledTimes(1);
            expect(warn).toHaveBeenCalledWith('[Deprecation]', 'otherMethod() is deprecated.');
        });
    });
});
