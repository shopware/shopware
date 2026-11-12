import { afterEach, describe, expect, it, vi } from 'vitest';
import 'shopware';
import MediaGallery from './Gallery.js';

function createLightboxGallery({
    withImage = true,
    clickZoomScale = 2,
} = {}) {
    document.body.innerHTML = `
        <div class="modal sw-media-gallery-lightbox">
            <section class="sw-media-gallery sw-media-gallery--is-lightbox">
                <div class="sw-media-gallery__previews">
                    <div class="sw-media-gallery__preview-item">
                        ${withImage ? '<img class="sw-media-gallery__preview" src="photo.jpg" srcset="photo-small.jpg 100w">' : '<video class="sw-media-gallery__preview"></video>'}
                    </div>
                </div>
            </section>
        </div>
    `;

    const root = document.querySelector('.sw-media-gallery');
    const container = root.querySelector('.sw-media-gallery__preview-item');
    const imageEl = container.querySelector(':scope > img');

    const component = new MediaGallery(root, { isLightbox: true, showMagnifier: false, clickZoomScale });
    component.init();

    return { component, container, imageEl };
}

function mockRects(imageEl, container, { image, viewport }) {
    vi.spyOn(imageEl, 'getBoundingClientRect').mockReturnValue(image);
    vi.spyOn(container, 'getBoundingClientRect').mockReturnValue(viewport);
}

describe('Sw:Media:Gallery lightbox click zoom', () => {
    afterEach(() => {
        vi.restoreAllMocks();
        document.body.innerHTML = '';
    });

    it('zooms around a center click', () => {
        const { component, container, imageEl } = createLightboxGallery();
        mockRects(imageEl, container, {
            image: { left: 0, top: 0, width: 400, height: 400 },
            viewport: { left: 0, top: 0, width: 400, height: 400 },
        });

        component.toggleClickZoom({
            currentTarget: container,
            clientX: 200,
            clientY: 200,
        });

        expect(container.classList.contains('is--zoomed')).toBe(true);
        expect(imageEl.style.transformOrigin).toBe('200px 200px');
        expect(imageEl.style.transform).toBe('translate(0px, 0px) scale(2)');
        expect(imageEl.hasAttribute('srcset')).toBe(false);
    });

    it('zooms around a top-left click', () => {
        const { component, container, imageEl } = createLightboxGallery();
        mockRects(imageEl, container, {
            image: { left: 0, top: 0, width: 400, height: 400 },
            viewport: { left: 0, top: 0, width: 400, height: 400 },
        });

        component.toggleClickZoom({
            currentTarget: container,
            clientX: 40,
            clientY: 60,
        });

        expect(imageEl.style.transformOrigin).toBe('40px 60px');
        expect(imageEl.style.transform).toBe('translate(0px, 0px) scale(2)');
    });

    it('zooms around a bottom-right click', () => {
        const { component, container, imageEl } = createLightboxGallery();
        mockRects(imageEl, container, {
            image: { left: 0, top: 0, width: 400, height: 400 },
            viewport: { left: 0, top: 0, width: 400, height: 400 },
        });

        component.toggleClickZoom({
            currentTarget: container,
            clientX: 360,
            clientY: 340,
        });

        expect(imageEl.style.transformOrigin).toBe('360px 340px');
        expect(imageEl.style.transform).toBe('translate(0px, 0px) scale(2)');
    });

    it('unzooms on a second click and resets the transform', () => {
        const { component, container, imageEl } = createLightboxGallery();
        mockRects(imageEl, container, {
            image: { left: 0, top: 0, width: 400, height: 400 },
            viewport: { left: 0, top: 0, width: 400, height: 400 },
        });

        component.toggleClickZoom({
            currentTarget: container,
            clientX: 200,
            clientY: 200,
        });
        component.toggleClickZoom({
            currentTarget: container,
            clientX: 200,
            clientY: 200,
        });

        expect(container.classList.contains('is--zoomed')).toBe(false);
        expect(imageEl.style.transform).toBe('scale(1)');
        expect(imageEl.style.transformOrigin).toBe('');
    });

    it('pans the zoomed image on both axes while dragging', () => {
        const { component, container, imageEl } = createLightboxGallery();
        mockRects(imageEl, container, {
            image: { left: 0, top: 0, width: 400, height: 400 },
            viewport: { left: 0, top: 0, width: 400, height: 400 },
        });

        component.toggleClickZoom({
            currentTarget: container,
            clientX: 200,
            clientY: 200,
        });

        container.dispatchEvent(new MouseEvent('mousedown', { clientX: 100, clientY: 100, bubbles: true }));
        container.dispatchEvent(new MouseEvent('mousemove', { clientX: 70, clientY: 80, bubbles: true }));

        expect(imageEl.style.transform).toBe('translate(-30px, -20px) scale(2)');
    });

    it('clamps pan so the scaled image cannot leave the viewport', () => {
        const { component, container, imageEl } = createLightboxGallery();
        mockRects(imageEl, container, {
            image: { left: 0, top: 0, width: 400, height: 400 },
            viewport: { left: 0, top: 0, width: 400, height: 400 },
        });

        component.toggleClickZoom({
            currentTarget: container,
            clientX: 200,
            clientY: 200,
        });

        container.dispatchEvent(new MouseEvent('mousedown', { clientX: 200, clientY: 200, bubbles: true }));
        container.dispatchEvent(new MouseEvent('mousemove', { clientX: -800, clientY: -800, bubbles: true }));

        expect(imageEl.style.transform).toBe('translate(-200px, -200px) scale(2)');

        container.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
        container.dispatchEvent(new MouseEvent('mousedown', { clientX: 200, clientY: 200, bubbles: true }));
        container.dispatchEvent(new MouseEvent('mousemove', { clientX: 1200, clientY: 1200, bubbles: true }));

        expect(imageEl.style.transform).toBe('translate(200px, 200px) scale(2)');
    });

    it('does not toggle zoom after a drag', () => {
        const { component, container } = createLightboxGallery();
        container._dragOccurred = true;

        component.toggleClickZoom({
            currentTarget: container,
            clientX: 200,
            clientY: 200,
        });

        expect(container.classList.contains('is--zoomed')).toBe(false);
        expect(container._dragOccurred).toBe(false);
    });

    it('does not zoom non-image preview items', () => {
        const { component, container } = createLightboxGallery({ withImage: false });

        component.toggleClickZoom({
            currentTarget: container,
            clientX: 200,
            clientY: 200,
        });

        expect(container.classList.contains('is--zoomed')).toBe(false);
    });

    it('uses clickZoomScale for the zoom transform', () => {
        const { component, container, imageEl } = createLightboxGallery({ clickZoomScale: 3 });
        mockRects(imageEl, container, {
            image: { left: 0, top: 0, width: 400, height: 400 },
            viewport: { left: 0, top: 0, width: 400, height: 400 },
        });

        component.toggleClickZoom({
            currentTarget: container,
            clientX: 200,
            clientY: 200,
        });

        expect(imageEl.style.transform).toBe('translate(0px, 0px) scale(3)');
    });

    it('zooms around the click on a centered letterboxed image', () => {
        const { component, container, imageEl } = createLightboxGallery();
        mockRects(imageEl, container, {
            image: { left: 200, top: 150, width: 400, height: 300 },
            viewport: { left: 0, top: 0, width: 800, height: 600 },
        });

        component.toggleClickZoom({
            currentTarget: container,
            clientX: 400,
            clientY: 300,
        });

        expect(imageEl.style.transformOrigin).toBe('200px 150px');
        expect(imageEl.style.transform).toBe('translate(0px, 0px) scale(2)');
    });
});
