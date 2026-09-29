/**
 * @sw-package framework
 */

describe('src/app/service/feature.service', () => {
    afterEach(() => {
        jest.restoreAllMocks();
    });

    describe('triggerDeprecationOrThrow', () => {
        it.activeFeatureFlags(['v6.9.0.0'])('throws once the major flag is active', () => {
            expect(() =>
                Shopware.Service('feature').triggerDeprecationOrThrow('V6_9_0_0', 'oldService() is deprecated.'),
            ).toThrow('Tried to access deprecated functionality: oldService() is deprecated.');
        });

        it('warns while the major flag is inactive', () => {
            const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            Shopware.Service('feature').triggerDeprecationOrThrow('V6_9_0_0', 'otherService() is deprecated.');

            expect(warn).toHaveBeenCalledWith('[Deprecation]', 'otherService() is deprecated.');
        });
    });
});
