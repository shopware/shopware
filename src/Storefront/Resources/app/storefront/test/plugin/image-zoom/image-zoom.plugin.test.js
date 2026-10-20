import ImageZoomPlugin from 'src/plugin/image-zoom/image-zoom.plugin';
import Hammer from 'hammerjs';

/**
 * @package discovery
 */
describe('ImageZoomPlugin tests', () => {
    let plugin = undefined;

    function defineSize(element, width, height) {
        Object.defineProperty(element, 'offsetWidth', { value: width, configurable: true });
        Object.defineProperty(element, 'offsetHeight', { value: height, configurable: true });
    }

    /**
     * Mirrors what Hammer delivers when a pinch ends: the END input type, but isFinal
     * false, because the two fingers never lift within the same frame.
     */
    function pinchEvent(eventType, scale) {
        return { eventType, scale, isFinal: false, deltaX: 0, deltaY: 0 };
    }

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-image-zoom-modal="true">
                <div class="zoom-modal-actions">
                    <button class="js-image-zoom-out">Zoom Out</button>
                    <button class="js-image-zoom-reset">Reset</button>
                    <button class="js-image-zoom-in">Zoom In</button>
                </div>
                <div class="tns-slide-active">
                    <div class="image-zoom-container" data-image-zoom="true">
                        <img src="#" alt="Test" class="js-image-zoom-element">
                    </div>
                </div>
            </div>
        `;

        const container = document.querySelector('[data-image-zoom="true"]');
        const image = container.querySelector('.js-image-zoom-element');

        // jsdom reports every layout box as 0, which makes _getMaxZoomValue() return 1
        // and clamp away any zoom the assertions depend on.
        defineSize(container, 100, 100);
        defineSize(image, 100, 100);
        Object.defineProperty(image, 'naturalWidth', { value: 400, configurable: true });
        Object.defineProperty(image, 'naturalHeight', { value: 400, configurable: true });

        plugin = new ImageZoomPlugin(container);
    });

    it('persists the pinched zoom level when the gesture ends', () => {
        expect(plugin._storedTransform.z).toBe(1);

        plugin._hammer.emit('pinch', pinchEvent(Hammer.INPUT_END, 2));

        expect(plugin._storedTransform.z).toBe(2);
    });

    it('persists the pinched zoom level when the gesture is cancelled', () => {
        expect(plugin._storedTransform.z).toBe(1);

        plugin._hammer.emit('pinch', pinchEvent(Hammer.INPUT_CANCEL, 3));

        expect(plugin._storedTransform.z).toBe(3);
    });

    it('does not persist while the gesture is still running', () => {
        plugin._hammer.emit('pinch', pinchEvent(Hammer.INPUT_MOVE, 2));

        expect(plugin._transform.z).toBe(2);
        expect(plugin._storedTransform.z).toBe(1);
    });
});
