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
});
