/**
 * @sw-package framework
 */

describe('src/app/service/feature.service', () => {
    beforeAll(() => {
        Shopware.Feature.init({ V7_0_0_0: false });
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    describe('triggerDeprecationOrThrow', () => {
        it.activeFeatureFlags(['v7.0.0.0'])('throws once the major flag is active', () => {
            expect(() =>
                Shopware.Service('feature').triggerDeprecationOrThrow('V7_0_0_0', 'oldService() is deprecated.'),
            ).toThrow('Tried to access deprecated functionality: oldService() is deprecated.');
        });

        it('warns while the major flag is inactive', () => {
            const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            Shopware.Service('feature').triggerDeprecationOrThrow('V7_0_0_0', 'otherService() is deprecated.');

            expect(warn).toHaveBeenCalledWith('[Deprecation]', 'otherService() is deprecated.');
        });

        it('logs an error for a flag that is not registered', () => {
            const error = jest.spyOn(console, 'error').mockImplementation(() => {});

            Shopware.Service('feature').triggerDeprecationOrThrow('V6_8_0', 'typoService() is deprecated.');

            expect(error).toHaveBeenCalledWith(
                '[Deprecation]',
                'typoService() is deprecated.\nNote: this deprecation has a typo: "V6_8_0" is an unknown feature flag. Please fix it.',
            );
        });
    });
});
