import Feature from 'src/helper/feature.helper';

const default_flags = {
    test1: true,
    test2: false,
};

/**
 * @package storefront
 */
describe('feature.helper.js', () => {
    beforeEach(() => {
        Feature.init(default_flags);
    });

    test('checks the flags', () => {
        expect(Feature.isActive('test1')).toBeTruthy();
        expect(Feature.isActive('test2')).toBeFalsy();
        expect(Feature.isActive('test3')).toBeFalsy();
    });

    describe('triggerDeprecationOrThrow', () => {
        let warnSpy;

        beforeEach(() => {
            warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});
        });

        afterEach(() => {
            warnSpy.mockRestore();
            delete window.debug;
        });

        test('warns when the major flag is inactive and debug is enabled', () => {
            window.debug = true;

            Feature.triggerDeprecationOrThrow('test2', 'This feature is deprecated.');

            expect(warnSpy).toHaveBeenCalledWith('[Deprecated] This feature is deprecated.');
        });

        test('is silent when debug is disabled', () => {
            window.debug = false;

            Feature.triggerDeprecationOrThrow('test2', 'This feature is deprecated.');

            expect(warnSpy).not.toHaveBeenCalled();
        });

        test('throws when the feature flag is active', () => {
            window.debug = true;

            expect(() => {
                Feature.triggerDeprecationOrThrow('test1', 'This feature is deprecated.');
            }).toThrow('Tried to access deprecated functionality: This feature is deprecated.');

            expect(warnSpy).not.toHaveBeenCalled();
        });
    });
});
