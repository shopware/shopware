jest.mock('element-closest');

/**
 * @package storefront
 */
describe('polyfill-loader', () => {
    test('it calls ElementClosestPolyfill', () => {
        const elementClosest = require('element-closest');

        require('src/helper/polyfill-loader.helper');
        expect(elementClosest).toBeCalled();
    })
});
