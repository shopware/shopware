import MagnifierPlugin from 'src/plugin/magnifier/magnifier.plugin';

/**
 * @package storefront
 */
describe('MagnifierPlugin tests', () => {
    let magnifierPlugin;
    let element;

    beforeEach(() => {
        // Basic DOM setup with required containers
        document.body.innerHTML = `
            <div data-magnifier>
                <div class="js-magnifier-container">
                    <img class="js-magnifier-image" src="#" />
                </div>
            </div>
            <div class="js-magnifier-zoom-image-container"></div>
        `;

        // Minimal PluginManager stub used by Plugin base class
        window.PluginManager = {
            getPluginInstancesFromElement: jest.fn(() => new Map()),
            getPlugin: jest.fn(() => new Map([["instances", []]])),
        };

        // Ensure deterministic viewport height
        Object.defineProperty(window, 'innerHeight', { value: 1000, configurable: true }); // maxHeight = 500

        element = document.querySelector('[data-magnifier]');
        magnifierPlugin = new MagnifierPlugin(element);

        // Provide a _zoomImage element with controlled bounding box
        const zoomImageEl = document.createElement('div');
        document.querySelector('.js-magnifier-zoom-image-container').appendChild(zoomImageEl);
        magnifierPlugin._zoomImage = zoomImageEl;
    });

    afterEach(() => {
        magnifierPlugin = undefined;
        element = undefined;
        document.body.innerHTML = '';
    });

    describe('_setZoomImageSize', () => {
        test('should clamp height to window.innerHeight / 2 when computed height exceeds maxHeight', () => {
            // keepAspectRatioOnZoom: true (default), scaleZoomImage: false (default)
            // zoomImageSize.y (desired) = 800 > maxHeight (500) -> expect 500
            magnifierPlugin._zoomImage.getBoundingClientRect = () => ({ width: 400, height: 800, top: 0, left: 0, right: 0, bottom: 0 });

            // imageSize doesn't affect this branch, but provide sensible values
            const imageSize = { x: 400, y: 800 };

            magnifierPlugin._setZoomImageSize(imageSize);

            expect(magnifierPlugin._zoomImage.style.height).toBe('500px');
            expect(magnifierPlugin._zoomImage.style.minHeight).toBe('500px');
        });

        test('should not clamp when computed height is smaller than maxHeight', () => {
            // keepAspectRatioOnZoom: true (default), scaleZoomImage: false (default)
            // zoomImageSize.y (desired) = 300 < maxHeight (500) -> expect 300
            magnifierPlugin._zoomImage.getBoundingClientRect = () => ({ width: 400, height: 300, top: 0, left: 0, right: 0, bottom: 0 });

            const imageSize = { x: 400, y: 800 };

            magnifierPlugin._setZoomImageSize(imageSize);

            expect(magnifierPlugin._zoomImage.style.height).toBe('300px');
            expect(magnifierPlugin._zoomImage.style.minHeight).toBe('300px');
        });

        test('should clamp when scaleZoomImage is true and computed height exceeds maxHeight', () => {
            // Activate scaleZoomImage path
            magnifierPlugin.options.scaleZoomImage = true;

            // zoomImageSize.x * factor -> 400 * (800/400) = 800 > 500 -> expect 500
            magnifierPlugin._zoomImage.getBoundingClientRect = () => ({ width: 400, height: 100, top: 0, left: 0, right: 0, bottom: 0 });
            const imageSize = { x: 400, y: 800 }; // factor = 2

            magnifierPlugin._setZoomImageSize(imageSize);

            expect(magnifierPlugin._zoomImage.style.height).toBe('500px');
            expect(magnifierPlugin._zoomImage.style.minHeight).toBe('500px');
        });
    });
});
