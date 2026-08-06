import MagnifierPlugin from 'src/plugin/magnifier/magnifier.plugin';
import { Vector2 } from 'src/helper/vector.helper';

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
            getPlugin: jest.fn(() => new Map([['instances', []]])),
            initializePluginsInParentElement: jest.fn(),
        };

        // Ensure deterministic viewport height
        Object.defineProperty(window, 'innerHeight', { value: 2000, configurable: true }); // maxHeight = 1000

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

    describe('init', () => {
        test('should not register events when zoom image container is missing (e.g. CMS pages without product context)', () => {
            document.body.innerHTML = `
                <div data-magnifier>
                    <div class="js-magnifier-container">
                        <img class="js-magnifier-image" src="#" />
                    </div>
                </div>
            `;

            const el = document.querySelector('[data-magnifier]');
            const plugin = new MagnifierPlugin(el);

            expect(plugin._zoomImageContainer).toBeNull();

            const image = el.querySelector('.js-magnifier-image');
            expect(() => {
                image.dispatchEvent(new MouseEvent('mousemove'));
            }).not.toThrow();
        });
    });

    describe('_getObjectFitScale', () => {
        function imageWithFit(objectFit) {
            const image = document.querySelector('.js-magnifier-image');
            jest.spyOn(window, 'getComputedStyle').mockReturnValue({ objectFit });
            return image;
        }

        afterEach(() => {
            jest.restoreAllMocks();
        });

        test('should scale uniformly by the smaller factor for object-fit: contain (storefront default)', () => {
            const image = imageWithFit('contain');
            // element 400x400, natural 800x1600 -> scaleX 0.5, scaleY 0.25 -> min 0.25
            const scale = magnifierPlugin._getObjectFitScale(image, new Vector2(400, 400), new Vector2(800, 1600));

            expect(scale.x).toBeCloseTo(0.25);
            expect(scale.y).toBeCloseTo(0.25);
        });

        test('should scale uniformly by the larger factor for object-fit: cover', () => {
            const image = imageWithFit('cover');
            const scale = magnifierPlugin._getObjectFitScale(image, new Vector2(400, 400), new Vector2(800, 1600));

            expect(scale.x).toBeCloseTo(0.5);
            expect(scale.y).toBeCloseTo(0.5);
        });

        test('should scale per-axis for object-fit: fill', () => {
            const image = imageWithFit('fill');
            const scale = magnifierPlugin._getObjectFitScale(image, new Vector2(400, 400), new Vector2(800, 1600));

            expect(scale.x).toBeCloseTo(0.5);
            expect(scale.y).toBeCloseTo(0.25);
        });

        test('should not scale for object-fit: none', () => {
            const image = imageWithFit('none');
            const scale = magnifierPlugin._getObjectFitScale(image, new Vector2(400, 400), new Vector2(800, 1600));

            expect(scale.x).toBe(1);
            expect(scale.y).toBe(1);
        });

        test('should never scale up for object-fit: scale-down', () => {
            const image = imageWithFit('scale-down');
            // image smaller than element -> contain would scale up, scale-down must stay at 1
            const scale = magnifierPlugin._getObjectFitScale(image, new Vector2(800, 800), new Vector2(400, 200));

            expect(scale.x).toBe(1);
            expect(scale.y).toBe(1);
        });

        test('should fall back to contain for an unknown/empty object-fit value (e.g. jsdom)', () => {
            const image = imageWithFit('');
            const scale = magnifierPlugin._getObjectFitScale(image, new Vector2(400, 400), new Vector2(800, 1600));

            expect(scale.x).toBeCloseTo(0.25);
            expect(scale.y).toBeCloseTo(0.25);
        });
    });

    describe('_getZoomGeometry', () => {
        function image({ natural, rect, objectFit = 'contain' }) {
            const img = document.querySelector('.js-magnifier-image');
            Object.defineProperty(img, 'naturalWidth', { value: natural.x, configurable: true });
            Object.defineProperty(img, 'naturalHeight', { value: natural.y, configurable: true });
            img.getBoundingClientRect = () => ({ width: rect.x, height: rect.y, top: 0, left: 0, right: 0, bottom: 0 });
            jest.spyOn(window, 'getComputedStyle').mockReturnValue({ objectFit });
            return img;
        }

        afterEach(() => {
            jest.restoreAllMocks();
        });

        test('should return null when the image has no layout yet', () => {
            const img = image({ natural: { x: 0, y: 0 }, rect: { x: 0, y: 0 } });

            expect(magnifierPlugin._getZoomGeometry(img)).toBeNull();
        });

        test('should letterbox a portrait image inside a square element (contain)', () => {
            // natural 800x1600 (portrait), element 400x400 -> scale 0.25, rendered 200x400
            const img = image({ natural: { x: 800, y: 1600 }, rect: { x: 400, y: 400 } });

            const geometry = magnifierPlugin._getZoomGeometry(img);

            expect(geometry.region.size.x).toBeCloseTo(200);
            expect(geometry.region.size.y).toBeCloseTo(400);
            // horizontally centered inside the 400px wide element
            expect(geometry.region.offset.x).toBeCloseTo(100);
            expect(geometry.region.offset.y).toBeCloseTo(0);
            // whole image is visible -> no cropped source offset
            expect(geometry.source.offset.x).toBeCloseTo(0);
            expect(geometry.source.offset.y).toBeCloseTo(0);
            expect(geometry.source.size.x).toBeCloseTo(800);
            expect(geometry.source.size.y).toBeCloseTo(1600);
        });

        test('should crop a portrait image inside a square element (cover)', () => {
            // natural 800x1600, element 400x400, cover -> scale 0.5, rendered 400x800, region clamped to 400x400
            const img = image({ natural: { x: 800, y: 1600 }, rect: { x: 400, y: 400 }, objectFit: 'cover' });

            const geometry = magnifierPlugin._getZoomGeometry(img);

            expect(geometry.region.size.x).toBeCloseTo(400);
            expect(geometry.region.size.y).toBeCloseTo(400);
            expect(geometry.region.offset.x).toBeCloseTo(0);
            expect(geometry.region.offset.y).toBeCloseTo(0);
            // source is 800x800 centered vertically inside the 800x1600 image
            expect(geometry.source.size.x).toBeCloseTo(800);
            expect(geometry.source.size.y).toBeCloseTo(800);
            expect(geometry.source.offset.x).toBeCloseTo(0);
            expect(geometry.source.offset.y).toBeCloseTo(400);
        });
    });

    describe('_getEffectiveZoomFactor', () => {
        test('should keep the configured zoom factor when the zoomed image covers the window', () => {
            magnifierPlugin.options.zoomFactor = 3;
            // region 400x400 zoomed x3 = 1200x1200 >= window 500x300
            const factor = magnifierPlugin._getEffectiveZoomFactor(new Vector2(400, 400), new Vector2(500, 300));

            expect(factor).toBe(3);
        });

        test('should raise the zoom factor so the zoomed image never underflows the window', () => {
            magnifierPlugin.options.zoomFactor = 2;
            // region 100x400, window 500x300 -> needs factor 5 horizontally
            const factor = magnifierPlugin._getEffectiveZoomFactor(new Vector2(100, 400), new Vector2(500, 300));

            expect(factor).toBe(5);
        });
    });

    describe('_getOverlaySize', () => {
        beforeEach(() => {
            magnifierPlugin._overlay = document.createElement('div');
        });

        test('should size the lens to the image area visible in the zoom window', () => {
            // window 600x300, factor 3 -> lens 200x100
            const size = magnifierPlugin._getOverlaySize(new Vector2(400, 400), new Vector2(600, 300), 3);

            expect(size.x).toBeCloseTo(200);
            expect(size.y).toBeCloseTo(100);
            expect(magnifierPlugin._overlay.style.width).toBe('200px');
            expect(magnifierPlugin._overlay.style.height).toBe('100px');
        });

        test('should never exceed the visible image region', () => {
            // window 900x900, factor 3 -> raw lens 300x300, but region is only 200x400
            const size = magnifierPlugin._getOverlaySize(new Vector2(200, 400), new Vector2(900, 900), 3);

            expect(size.x).toBeCloseTo(200);
            expect(size.y).toBeCloseTo(300);
        });
    });

    describe('_getZoomProgress', () => {
        test('should map the cursor position into a 0..1 range with the lens centered on the cursor', () => {
            // region 400x400, lens 100x100 -> range 300x300; cursor at 200,200 -> lensPos 150,150 -> 0.5
            const progress = magnifierPlugin._getZoomProgress(new Vector2(200, 200), new Vector2(400, 400), new Vector2(100, 100));

            expect(progress.x).toBeCloseTo(0.5);
            expect(progress.y).toBeCloseTo(0.5);
        });

        test('should clamp progress to the reachable range at the edges', () => {
            const top = magnifierPlugin._getZoomProgress(new Vector2(0, 0), new Vector2(400, 400), new Vector2(100, 100));
            expect(top.x).toBe(0);
            expect(top.y).toBe(0);

            const bottom = magnifierPlugin._getZoomProgress(new Vector2(400, 400), new Vector2(400, 400), new Vector2(100, 100));
            expect(bottom.x).toBe(1);
            expect(bottom.y).toBe(1);
        });
    });

    describe('_getZoomBackgroundOffset', () => {
        function geometryFor({ natural, scale, region, source }) {
            return {
                natural: new Vector2(natural.x, natural.y),
                scale: new Vector2(scale.x, scale.y),
                region: { offset: new Vector2(region.offset.x, region.offset.y), size: new Vector2(region.size.x, region.size.y) },
                source: { offset: new Vector2(source.offset.x, source.offset.y), size: new Vector2(source.size.x, source.size.y) },
            };
        }

        test('should keep the whole image height reachable for a portrait image in a landscape window (regression for issue 14498)', () => {
            // portrait image, letterboxed contain: natural 800x1066, rendered region 323x430
            const scale = 430 / 1066; // rendered region height 430 -> region width ~322.7
            const geometry = geometryFor({
                natural: { x: 800, y: 1066 },
                scale: { x: scale, y: scale },
                region: { offset: { x: 172, y: 0 }, size: { x: 800 * scale, y: 430 } },
                source: { offset: { x: 0, y: 0 }, size: { x: 800, y: 1066 } },
            });
            const zoomWindow = new Vector2(543, 277); // landscape zoom window (ratio 1.96)
            const zoomFactor = magnifierPlugin._getEffectiveZoomFactor(geometry.region.size, zoomWindow);
            const backgroundSize = magnifierPlugin._getZoomBackgroundSize(geometry, zoomFactor);

            const top = magnifierPlugin._getZoomBackgroundOffset(geometry, zoomWindow, zoomFactor, new Vector2(0.5, 0));
            const bottom = magnifierPlugin._getZoomBackgroundOffset(geometry, zoomWindow, zoomFactor, new Vector2(0.5, 1));

            // top of the image must be reachable...
            expect(top.y).toBeCloseTo(0);
            // ...and the bottom edge of the window must reach the bottom edge of the image
            expect(bottom.y + zoomWindow.y).toBeCloseTo(backgroundSize.y);
        });

        test('should keep the whole image width reachable for a landscape image in a portrait window', () => {
            // landscape image, letterboxed contain: natural 1600x800, rendered region 430x215
            const scale = 430 / 1600;
            const geometry = geometryFor({
                natural: { x: 1600, y: 800 },
                scale: { x: scale, y: scale },
                region: { offset: { x: 0, y: 107 }, size: { x: 430, y: 800 * scale } },
                source: { offset: { x: 0, y: 0 }, size: { x: 1600, y: 800 } },
            });
            const zoomWindow = new Vector2(277, 543); // portrait zoom window (ratio 0.51)
            const zoomFactor = magnifierPlugin._getEffectiveZoomFactor(geometry.region.size, zoomWindow);
            const backgroundSize = magnifierPlugin._getZoomBackgroundSize(geometry, zoomFactor);

            const left = magnifierPlugin._getZoomBackgroundOffset(geometry, zoomWindow, zoomFactor, new Vector2(0, 0.5));
            const right = magnifierPlugin._getZoomBackgroundOffset(geometry, zoomWindow, zoomFactor, new Vector2(1, 0.5));

            expect(left.x).toBeCloseTo(0);
            expect(right.x + zoomWindow.x).toBeCloseTo(backgroundSize.x);
        });

        test('should skip the cropped part of the image for object-fit: cover', () => {
            // cover: source is vertically centered, 400px cropped at the top in source coords
            const geometry = geometryFor({
                natural: { x: 800, y: 1600 },
                scale: { x: 0.5, y: 0.5 },
                region: { offset: { x: 0, y: 0 }, size: { x: 400, y: 400 } },
                source: { offset: { x: 0, y: 400 }, size: { x: 800, y: 800 } },
            });
            const zoomWindow = new Vector2(400, 400);
            const zoomFactor = 3;

            const top = magnifierPlugin._getZoomBackgroundOffset(geometry, zoomWindow, zoomFactor, new Vector2(0, 0));

            // the first visible row skips the cropped 400px (in source) * scale 0.5 * factor 3 = 600px
            expect(top.y).toBeCloseTo(600);
        });
    });

    describe('_setZoomImageSize', () => {
        test('should clamp height to window.innerHeight / 2 when computed height exceeds maxHeight', () => {
            Object.defineProperty(window, 'innerHeight', { value: 1000, configurable: true }); // maxHeight = 500
            magnifierPlugin._zoomImage.getBoundingClientRect = () => ({ width: 400, height: 800, top: 0, left: 0, right: 0, bottom: 0 });

            magnifierPlugin._setZoomImageSize(new Vector2(400, 800));

            expect(magnifierPlugin._zoomImage.style.height).toBe('500px');
            expect(magnifierPlugin._zoomImage.style.minHeight).toBe('500px');
        });

        test('should not clamp when computed height is smaller than maxHeight', () => {
            Object.defineProperty(window, 'innerHeight', { value: 1000, configurable: true }); // maxHeight = 500
            magnifierPlugin._zoomImage.getBoundingClientRect = () => ({ width: 400, height: 300, top: 0, left: 0, right: 0, bottom: 0 });

            magnifierPlugin._setZoomImageSize(new Vector2(400, 800));

            expect(magnifierPlugin._zoomImage.style.height).toBe('300px');
            expect(magnifierPlugin._zoomImage.style.minHeight).toBe('300px');
        });

        test('should derive the height from the region ratio when scaleZoomImage is enabled', () => {
            Object.defineProperty(window, 'innerHeight', { value: 4000, configurable: true }); // maxHeight = 2000 (no clamp)
            magnifierPlugin.options.scaleZoomImage = true;
            // width 400, region ratio y/x = 800/400 = 2 -> height = 400 * 2 = 800
            magnifierPlugin._zoomImage.getBoundingClientRect = () => ({ width: 400, height: 100, top: 0, left: 0, right: 0, bottom: 0 });

            magnifierPlugin._setZoomImageSize(new Vector2(400, 800));

            expect(magnifierPlugin._zoomImage.style.height).toBe('800px');
            expect(magnifierPlugin._zoomImage.style.minHeight).toBe('800px');
        });

        test('should clamp the scaleZoomImage height to maxHeight as well', () => {
            Object.defineProperty(window, 'innerHeight', { value: 1000, configurable: true }); // maxHeight = 500
            magnifierPlugin.options.scaleZoomImage = true;
            // width 400 * ratio 2 = 800 -> clamped to 500
            magnifierPlugin._zoomImage.getBoundingClientRect = () => ({ width: 400, height: 100, top: 0, left: 0, right: 0, bottom: 0 });

            magnifierPlugin._setZoomImageSize(new Vector2(400, 800));

            expect(magnifierPlugin._zoomImage.style.height).toBe('500px');
            expect(magnifierPlugin._zoomImage.style.minHeight).toBe('500px');
        });
    });

    describe('_createOverlay and _createZoomImage', () => {
        test('should return the created elements instead of the insertAdjacentHTML return value', () => {
            const imageContainer = document.querySelector('.js-magnifier-container');

            const overlay = magnifierPlugin._createOverlay(imageContainer);
            const zoomImage = magnifierPlugin._createZoomImage();

            expect(overlay).toBeInstanceOf(HTMLElement);
            expect(zoomImage).toBeInstanceOf(HTMLElement);
            expect(imageContainer.querySelector('.js-magnifier-overlay')).toBe(overlay);
            expect(document.querySelector('.js-magnifier-zoom-image-container .js-magnifier-zoom-image')).toBe(zoomImage);
        });
    });

    describe('_onMouseMove', () => {
        test('should continue the first hover flow when the zoom containers start empty', () => {
            const imageContainer = document.querySelector('.js-magnifier-container');
            const image = document.querySelector('.js-magnifier-image');

            image.setAttribute('data-full-image', '/full/image.jpg');
            Object.defineProperty(image, 'naturalWidth', { value: 1200, configurable: true });
            Object.defineProperty(image, 'naturalHeight', { value: 800, configurable: true });
            image.getBoundingClientRect = () => ({ width: 400, height: 300, top: 20, left: 10, right: 0, bottom: 0 });
            imageContainer.getBoundingClientRect = () => ({ top: 20, left: 10, width: 400, height: 300, right: 0, bottom: 0 });
            jest.spyOn(window, 'getComputedStyle').mockReturnValue({ objectFit: 'contain' });

            jest.spyOn(magnifierPlugin, '_isActive').mockReturnValue(true);
            const setOverlayPositionSpy = jest.spyOn(magnifierPlugin, '_setOverlayPosition');
            const setZoomImageSpy = jest.spyOn(magnifierPlugin, '_setZoomImage');

            magnifierPlugin._zoomImageContainer.innerHTML = '';
            magnifierPlugin._overlay = undefined;
            magnifierPlugin._zoomImage = undefined;

            expect(() => {
                magnifierPlugin._onMouseMove({ pageX: 100, pageY: 120 }, imageContainer, image);
            }).not.toThrow();

            expect(magnifierPlugin._overlay).toBeInstanceOf(HTMLElement);
            expect(magnifierPlugin._zoomImage).toBeInstanceOf(HTMLElement);
            expect(setOverlayPositionSpy).toHaveBeenCalled();
            expect(setZoomImageSpy).toHaveBeenCalled();
        });

        test('should map the bottom of a portrait image in a landscape window to the bottom of the zoom (regression for issue 14498)', () => {
            const imageContainer = document.querySelector('.js-magnifier-container');
            const image = document.querySelector('.js-magnifier-image');

            // portrait image 800x1066 rendered contain into a 323x430 element at the top-left
            image.setAttribute('data-full-image', '/full/image.jpg');
            Object.defineProperty(image, 'naturalWidth', { value: 800, configurable: true });
            Object.defineProperty(image, 'naturalHeight', { value: 1066, configurable: true });
            image.getBoundingClientRect = () => ({ width: 323, height: 430, top: 0, left: 0, right: 0, bottom: 0 });
            imageContainer.getBoundingClientRect = () => ({ top: 0, left: 0, width: 323, height: 430, right: 0, bottom: 0 });
            jest.spyOn(window, 'getComputedStyle').mockReturnValue({ objectFit: 'contain' });
            // landscape zoom window 543x277 (pinned; _createZoomImage replaces the element during the flow)
            jest.spyOn(magnifierPlugin, '_getZoomImageSize').mockReturnValue(new Vector2(543, 277));
            jest.spyOn(magnifierPlugin, '_isActive').mockReturnValue(true);

            // hover the very bottom center of the image
            magnifierPlugin._onMouseMove({ pageX: 161, pageY: 430 }, imageContainer, image);

            const parse = (value) => value.split(' ').map((part) => parseFloat(part));
            const [, bgHeight] = parse(magnifierPlugin._zoomImage.style.backgroundSize);
            const [, bgPosY] = parse(magnifierPlugin._zoomImage.style.backgroundPosition);
            const windowHeight = 277;

            // region height 430 * zoomFactor 3 = background height 1290
            expect(bgHeight).toBeCloseTo(1290);
            // bottom edge of the window must align with the bottom edge of the image
            expect(-bgPosY + windowHeight).toBeCloseTo(bgHeight);
        });

        test('should not throw when the image has no layout yet', () => {
            const imageContainer = document.querySelector('.js-magnifier-container');
            const image = document.querySelector('.js-magnifier-image');

            image.setAttribute('data-full-image', '/full/image.jpg');
            Object.defineProperty(image, 'naturalWidth', { value: 0, configurable: true });
            Object.defineProperty(image, 'naturalHeight', { value: 0, configurable: true });
            image.getBoundingClientRect = () => ({ width: 0, height: 0, top: 0, left: 0, right: 0, bottom: 0 });
            jest.spyOn(window, 'getComputedStyle').mockReturnValue({ objectFit: 'contain' });
            jest.spyOn(magnifierPlugin, '_isActive').mockReturnValue(true);
            const setZoomImageSpy = jest.spyOn(magnifierPlugin, '_setZoomImage');

            expect(() => {
                magnifierPlugin._onMouseMove({ pageX: 10, pageY: 10 }, imageContainer, image);
            }).not.toThrow();

            expect(setZoomImageSpy).not.toHaveBeenCalled();
        });
    });
});
