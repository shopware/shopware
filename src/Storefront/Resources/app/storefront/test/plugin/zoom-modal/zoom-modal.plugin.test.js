import ZoomModalPlugin from 'src/plugin/zoom-modal/zoom-modal.plugin';
import GallerySliderPlugin from 'src/plugin/slider/gallery-slider.plugin';

/**
 * @package storefront
 */
describe('ZoomModalPlugin tests', () => {
    let zoomModalPlugin = undefined;
    let gallerySliderPlugin = undefined;

    beforeEach(() => {
        document.body.innerHTML = `
            <div class="js-gallery-zoom-modal-container">
                <div data-zoom-modal="true">

                    <div id="gallery-slider" class="gallery-slider-container">
                        <img src="#" alt="" class="gallery-slider-image magnifier-image js-magnifier-image" tabindex="0">
                        <img src="#" alt="" class="gallery-slider-image magnifier-image js-magnifier-image" tabindex="0">
                        <img src="#" alt="" class="gallery-slider-image magnifier-image js-magnifier-image" tabindex="0">
                    </div>

                    <div class="modal js-zoom-modal">
                        <div class="modal-dialog">
                            <div class="modal-content" data-modal-gallery-slider="true">
                                <div class="modal-body">
                                    <div class="zoom-modal-actions">
                                        <button class="image-zoom-btn js-image-zoom-out">Zoom Out</button>
                                        <button class="image-zoom-btn js-image-zoom-reset">Reset</button>
                                        <button class="image-zoom-btn js-image-zoom-in">Zoom In</button>
                                    </div>
                                    <div id="zoom-modal-gallery-slider" class="gallery-slider">
                                        <div class="gallery-slider-item">
                                            <div class="image-zoom-container">
                                                <img src="#" alt="Test" class="gallery-slider-image" tabindex="0">
                                            </div>
                                        </div>
                                        <div class="gallery-slider-item">
                                            <div class="image-zoom-container">
                                                <img src="#" alt="Test" class="gallery-slider-image" tabindex="0">
                                            </div>
                                        </div>
                                        <div class="gallery-slider-item">
                                            <div class="image-zoom-container">
                                                <img src="#" alt="Test" class="gallery-slider-image" tabindex="0">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        window.PluginManager.register = jest.fn();
        window.PluginManager.initializePlugin = jest.fn(() => Promise.resolve());

        window.focusHandler = {
            saveFocusState: jest.fn(),
            resumeFocusState: jest.fn(),
            setFocus: jest.fn(),
        };

        window.breakpoints = {
            lg: 992,
            md: 768,
            sm: 576,
            xl: 1200,
            xs: 0,
        }

        const element = document.querySelector('[data-zoom-modal]');
        const modalGalleryElement = document.querySelector('[data-modal-gallery-slider]');

        zoomModalPlugin = new ZoomModalPlugin(element);
        gallerySliderPlugin = new GallerySliderPlugin(modalGalleryElement);

        gallerySliderPlugin._slider = {
            goTo: jest.fn(),
            events: {
                off: jest.fn(),
                on: jest.fn,
            },
        }

        zoomModalPlugin.gallerySliderPlugin = gallerySliderPlugin;
    });

    afterEach(() => {
        zoomModalPlugin = undefined;
        gallerySliderPlugin = undefined;
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
        const gallerySlider = document.getElementById('gallery-slider');
        const zoomModalSlider = document.getElementById('zoom-modal-gallery-slider');

        const activeIndex = 2;

        const triggerImgElement = gallerySlider.querySelectorAll('img').item(activeIndex);
        const activeModalImgElement = zoomModalSlider.querySelectorAll('img').item(activeIndex);
        const modal = document.querySelector('.js-zoom-modal');

        const getParentSliderIndexSpy = jest.spyOn(zoomModalPlugin, '_getParentSliderIndex');

        // Click on image trigger element to open the zoom modal
        triggerImgElement.dispatchEvent(new Event('click'));

        expect(window.focusHandler.saveFocusState).toHaveBeenCalled();

        // Expect zoom modal to be shown and backdrop to be present
        expect(modal.classList.contains('show')).toBe(true);
        expect(document.querySelector('.modal-backdrop.show')).toBeTruthy();

        expect(getParentSliderIndexSpy).toHaveBeenCalled();
        expect(zoomModalPlugin.gallerySliderPlugin._slider.goTo).toHaveBeenCalledWith(activeIndex - 1);
        expect(window.focusHandler.setFocus).toHaveBeenCalledWith(activeModalImgElement);
    });

    test('zoom modal opens via enter key on img trigger element', () => {
        const gallerySlider = document.getElementById('gallery-slider');
        const zoomModalSlider = document.getElementById('zoom-modal-gallery-slider');

        const activeIndex = 2;

        const triggerImgElement = gallerySlider.querySelectorAll('img').item(activeIndex);
        const activeModalImgElement = zoomModalSlider.querySelectorAll('img').item(activeIndex);
        const modal = document.querySelector('.js-zoom-modal');

        const getParentSliderIndexSpy = jest.spyOn(zoomModalPlugin, '_getParentSliderIndex');

        const keydownEvent = new Event('keydown');
        keydownEvent.key = 'Enter';

        triggerImgElement.dispatchEvent(keydownEvent);

        expect(window.focusHandler.saveFocusState).toHaveBeenCalled();

        // Expect zoom modal to be shown and backdrop to be present
        expect(modal.classList.contains('show')).toBe(true);
        expect(document.querySelector('.modal-backdrop.show')).toBeTruthy();

        expect(getParentSliderIndexSpy).toHaveBeenCalled();
        expect(zoomModalPlugin.gallerySliderPlugin._slider.goTo).toHaveBeenCalledWith(activeIndex - 1);
        expect(window.focusHandler.setFocus).toHaveBeenCalledWith(activeModalImgElement);
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
        expect(window.focusHandler.resumeFocusState).toHaveBeenCalled();
    });
});
