import GallerySliderPlugin from 'src/plugin/slider/gallery-slider.plugin';
import NativeEventEmitter from 'src/helper/emitter.helper';

/**
 * @package storefront
 */
describe('GallerySliderPlugin tests', () => {
    let gallerySliderPlugin;
    let mockObserverDisconnect;
    let mockObserverObserve;
    let OriginalIntersectionObserver;

    function createGalleryDom() {
        document.body.innerHTML = `
            <div class="row gallery-slider-row is-loading js-gallery-zoom-modal-container js-slider-initialized"
                 data-gallery-slider="true">
                <div class="gallery-slider-col col order-1 order-md-2">
                    <div class="base-slider gallery-slider">
                        <div class="gallery-slider-container" data-gallery-slider-container="true">
                            <div class="gallery-slider-item-container">
                                <div class="gallery-slider-item">
                                    <img src="#" class="gallery-slider-image" alt="img1">
                                </div>
                            </div>
                            <div class="gallery-slider-item-container">
                                <div class="gallery-slider-item">
                                    <img src="#" class="gallery-slider-image" alt="img2">
                                </div>
                            </div>
                            <div class="gallery-slider-item-container">
                                <div class="gallery-slider-item">
                                    <img src="#" class="gallery-slider-image" alt="img3">
                                </div>
                            </div>
                        </div>
                        <div class="gallery-slider-controls" data-gallery-slider-controls="true">
                            <button class="gallery-slider-controls-prev">Prev</button>
                            <button class="gallery-slider-controls-next">Next</button>
                        </div>
                    </div>
                </div>
                <div class="gallery-slider-thumbnails-col col-0 col-md-auto order-2 order-md-1 is-left">
                    <div class="gallery-slider-thumbnails-container">
                        <div class="gallery-slider-thumbnails" data-gallery-slider-thumbnails="true">
                            <div class="gallery-slider-thumbnails-item"><div class="gallery-slider-thumbnails-item-inner"><img src="#" class="gallery-slider-thumbnails-image" alt="thumb1"></div></div>
                            <div class="gallery-slider-thumbnails-item"><div class="gallery-slider-thumbnails-item-inner"><img src="#" class="gallery-slider-thumbnails-image" alt="thumb2"></div></div>
                            <div class="gallery-slider-thumbnails-item"><div class="gallery-slider-thumbnails-item-inner"><img src="#" class="gallery-slider-thumbnails-image" alt="thumb3"></div></div>
                        </div>
                        <div data-thumbnail-slider-controls="true" class="gallery-slider-thumbnails-controls">
                            <button class="gallery-slider-thumbnails-controls-prev">Prev</button>
                            <button class="gallery-slider-thumbnails-controls-next">Next</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function createMockSliderEvents() {
        const listeners = {};
        return {
            on: jest.fn((event, cb) => {
                if (!listeners[event]) listeners[event] = [];
                listeners[event].push(cb);
            }),
            off: jest.fn(),
            _listeners: listeners,
        };
    }

    beforeEach(() => {
        mockObserverDisconnect = jest.fn();
        mockObserverObserve = jest.fn();

        OriginalIntersectionObserver = window.IntersectionObserver;
        window.IntersectionObserver = jest.fn(() => ({
            disconnect: mockObserverDisconnect,
            observe: mockObserverObserve,
            unobserve: jest.fn(),
        }));

        window.breakpoints = { lg: 992, md: 768, sm: 576, xl: 1200, xs: 0 };
        window.router = [];

        window.PluginManager = {
            getPluginInstancesFromElement: () => new Map(),
            getPlugin: () => ({ get: () => [] }),
            initializePlugin: jest.fn(() => Promise.resolve()),
            initializePlugins: jest.fn(),
            initializePluginsInParentElement: jest.fn(),
            register: jest.fn(),
        };

        document.$emitter = new NativeEventEmitter();

        createGalleryDom();
    });

    afterEach(() => {
        window.IntersectionObserver = OriginalIntersectionObserver;
        gallerySliderPlugin = undefined;
    });

    function initPluginWithMockedSliders() {
        const element = document.querySelector('[data-gallery-slider]');
        gallerySliderPlugin = new GallerySliderPlugin(element);

        const mockEvents = createMockSliderEvents();
        const thumbnailItems = document.querySelectorAll('.gallery-slider-thumbnails-item');

        gallerySliderPlugin._slider = {
            goTo: jest.fn(),
            destroy: jest.fn(),
            getInfo: () => ({ index: 0, cloneCount: 0 }),
            events: mockEvents,
        };

        gallerySliderPlugin._thumbnailSlider = {
            goTo: jest.fn(),
            destroy: jest.fn(),
            getInfo: () => ({
                index: 0,
                cloneCount: 0,
                slideItems: thumbnailItems,
            }),
        };

        gallerySliderPlugin.getCurrentSliderIndex = jest.fn(() => 0);

        return { mockEvents, thumbnailItems };
    }

    test('gallery slider plugin exists', () => {
        const element = document.querySelector('[data-gallery-slider]');
        gallerySliderPlugin = new GallerySliderPlugin(element);
        expect(typeof gallerySliderPlugin).toBe('object');
    });

    test('_thumbnailObserver is initialized to null', () => {
        const element = document.querySelector('[data-gallery-slider]');
        gallerySliderPlugin = new GallerySliderPlugin(element);
        expect(gallerySliderPlugin._thumbnailObserver).toBeNull();
    });

    test('_navigateThumbnailSlider creates and stores an IntersectionObserver', () => {
        const { thumbnailItems } = initPluginWithMockedSliders();

        gallerySliderPlugin._navigateThumbnailSlider();

        expect(window.IntersectionObserver).toHaveBeenCalledTimes(1);
        expect(gallerySliderPlugin._thumbnailObserver).not.toBeNull();
        expect(mockObserverObserve).toHaveBeenCalledTimes(thumbnailItems.length);
    });

    test('destroy() disconnects the IntersectionObserver', () => {
        initPluginWithMockedSliders();

        gallerySliderPlugin._navigateThumbnailSlider();
        expect(gallerySliderPlugin._thumbnailObserver).not.toBeNull();

        gallerySliderPlugin.destroy();

        expect(mockObserverDisconnect).toHaveBeenCalledTimes(1);
        expect(gallerySliderPlugin._thumbnailObserver).toBeNull();
    });

    test('destroy() without observer does not throw', () => {
        const element = document.querySelector('[data-gallery-slider]');
        gallerySliderPlugin = new GallerySliderPlugin(element);

        expect(() => gallerySliderPlugin.destroy()).not.toThrow();
    });

    test('multiple _navigateThumbnailSlider calls reuse the same observer reference', () => {
        initPluginWithMockedSliders();

        gallerySliderPlugin._navigateThumbnailSlider();
        const firstObserver = gallerySliderPlugin._thumbnailObserver;

        gallerySliderPlugin._navigateThumbnailSlider();
        const secondObserver = gallerySliderPlugin._thumbnailObserver;

        expect(window.IntersectionObserver).toHaveBeenCalledTimes(2);
        expect(firstObserver).not.toBe(secondObserver);
    });

    test('rebuild cycle properly cleans up and recreates observer', () => {
        const { thumbnailItems } = initPluginWithMockedSliders();

        gallerySliderPlugin._navigateThumbnailSlider();
        expect(gallerySliderPlugin._thumbnailObserver).not.toBeNull();

        gallerySliderPlugin.destroy();
        expect(mockObserverDisconnect).toHaveBeenCalledTimes(1);
        expect(gallerySliderPlugin._thumbnailObserver).toBeNull();

        const newMockEvents = createMockSliderEvents();
        gallerySliderPlugin._slider = {
            goTo: jest.fn(),
            destroy: jest.fn(),
            getInfo: () => ({ index: 0, cloneCount: 0 }),
            events: newMockEvents,
        };
        gallerySliderPlugin._thumbnailSlider = {
            goTo: jest.fn(),
            destroy: jest.fn(),
            getInfo: () => ({ index: 0, cloneCount: 0, slideItems: thumbnailItems }),
        };
        gallerySliderPlugin.getCurrentSliderIndex = jest.fn(() => 0);

        gallerySliderPlugin._navigateThumbnailSlider();
        expect(gallerySliderPlugin._thumbnailObserver).not.toBeNull();
        expect(window.IntersectionObserver).toHaveBeenCalledTimes(2);
    });
});
