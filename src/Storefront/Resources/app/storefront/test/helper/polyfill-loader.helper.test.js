jest.mock('element-closest');

describe('polyfill-loader', () => {
    test('it calls ElementClosestPolyfill', () => {
        const elementClosest = require('element-closest');

        require('src/helper/polyfill-loader.helper');
        expect(elementClosest).toBeCalled();
    })
});
