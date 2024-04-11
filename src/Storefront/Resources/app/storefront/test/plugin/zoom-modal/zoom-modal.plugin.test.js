import ZoomModalPlugin from 'src/plugin/zoom-modal/zoom-modal.plugin';

/**
 * @package storefront
 */
describe('ZoomModalPlugin tests', () => {
    let zoomModalPlugin = undefined;

    beforeEach(() => {
        document.body.innerHTML = `
            <div class="js-gallery-zoom-modal-container">
                <div data-zoom-modal="true">
                
                    <div class="gallery-slider-container">
                        <img src="#" alt="" class="gallery-slider-image magnifier-image js-magnifier-image">
                        <img src="#" alt="" class="gallery-slider-image magnifier-image js-magnifier-image">
                        <img src="#" alt="" class="gallery-slider-image magnifier-image js-magnifier-image">
                    </div>
    
                    <div class="js-zoom-modal">
                        <div class="modal">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-body">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        window.PluginManager.register = jest.fn();
        window.PluginManager.initializePlugin = jest.fn();

        const element = document.querySelector('[data-zoom-modal]');

        zoomModalPlugin = new ZoomModalPlugin(element);
    });

    afterEach(() => {
        zoomModalPlugin = undefined;
    });

    test('zoom modal plugin exists', () => {
        expect(typeof zoomModalPlugin).toBe('object');
    });

    test('zoom modal show modal has only one listener', () => {
        zoomModalPlugin.$emitter.publish = jest.fn();

        const modal = document.querySelector('.js-zoom-modal');

        zoomModalPlugin._showModal(modal);
        zoomModalPlugin._showModal(modal);

        expect(zoomModalPlugin.$emitter.publish).toHaveBeenCalledWith('modalShow', { modal });
        expect(zoomModalPlugin.$emitter.publish).toHaveBeenCalledTimes(2);
    });

    test('zoom modal opens via click event on img trigger element', () => {
        const triggerImgElement = document.querySelector('img');
        const modal = document.querySelector('.js-zoom-modal');

        // Click on image trigger element to open the zoom modal
        triggerImgElement.dispatchEvent(new Event('click'));

        // Expect zoom modal to be shown and backdrop to be present
        expect(modal.classList.contains('show')).toBe(true);
        expect(document.querySelector('.modal-backdrop.show')).toBeTruthy();
    });

    test('zoom modal closes via ESC key', () => {
        const triggerImgElement = document.querySelector('img');
        const modal = document.querySelector('.js-zoom-modal');
        const escEvent = new Event('keydown');
        escEvent.key = 'Escape';

        // Click on image trigger element to open the zoom modal
        triggerImgElement.dispatchEvent(new Event('click'));

        // Expect zoom modal to be shown and backdrop to be present
        expect(modal.classList.contains('show')).toBe(true);
        expect(document.querySelector('.modal-backdrop.show')).toBeTruthy();

        // Now close the modal via ESC key
        modal.dispatchEvent(escEvent);

        // Expect zoom modal to be closed again
        expect(modal.classList.contains('show')).toBe(false);
        expect(document.querySelector('.modal-backdrop.show')).toBeFalsy();
    });
});
