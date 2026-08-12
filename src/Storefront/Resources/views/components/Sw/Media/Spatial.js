export default class MediaSpatial extends ShopwareComponent {
    static options = {
        dragClickThreshold: 10,
    };

    init() {
        this.spatialBaseViewerInstance = null;
        this.canvasElement = this.el.querySelector('[data-spatial-base-viewer]');
        this.loaderElement = this.el.querySelector('.sw-media-spatial__loader');

        this.pointerDown = false;
        this.pixelsMoved = 0;
        this.lightboxTrigger = this.el.closest('[data-bs-toggle="modal"]');

        this.initSuppressClickAfterDrag();

        this.initializeObserver({
            childList: true,
            attributes: true,
            subtree: true
        });
    }

    onAttributeUpdate(mutationRecord) {
        // Wait until SpatialBaseViewer initialized the canvas
        if (mutationRecord.attributeName === 'data-engine') {
            this.spatialBaseViewerInstance = window.PluginManager.getPluginInstanceFromElement(this.canvasElement, 'SpatialBaseViewer');
            this.initVisibilityObserver();
        }
    }

    initVisibilityObserver() {
        if (!this.spatialBaseViewerInstance) {
            return;
        }

        this.observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    this.loaderElement.classList.add('d-none');
                    this.spatialBaseViewerInstance.startRendering();
                } else {
                    this.spatialBaseViewerInstance.stopRendering();
                    this.loaderElement.classList.remove('d-none');
                }
            });
        });

        this.observer.observe(this.el);
    }

    /**
     * Dragging the 3D model ends with a click that would open the lightbox
     * (data-bs-toggle="modal"). Bootstrap listens for that click on document in the
     * capture phase, so stopPropagation on the canvas is too late. Instead, briefly
     * remove the toggle attribute after a drag so the unintended click is ignored.
     */
    initSuppressClickAfterDrag() {
        if (!this.canvasElement || !this.lightboxTrigger) {
            return;
        }

        this.onPointerDown = () => {
            this.pointerDown = true;
            this.pixelsMoved = 0;
        };

        this.onPointerMove = () => {
            if (this.pointerDown) {
                this.pixelsMoved += 1;
            }
        };

        this.onPointerUp = () => {
            this.pointerDown = false;

            if (this.pixelsMoved <= this.options.dragClickThreshold) {
                return;
            }

            this.lightboxTrigger.removeAttribute('data-bs-toggle');

            setTimeout(() => {
                this.lightboxTrigger.setAttribute('data-bs-toggle', 'modal');
                this.pixelsMoved = 0;
            }, 0);
        };

        this.canvasElement.addEventListener('pointerdown', this.onPointerDown);
        this.canvasElement.addEventListener('pointermove', this.onPointerMove);
        this.canvasElement.addEventListener('pointerup', this.onPointerUp);
    }

    destroy() {
        if (this.observer) {
            this.observer.disconnect();
        }

        if (this.canvasElement) {
            this.canvasElement.removeEventListener('pointerdown', this.onPointerDown);
            this.canvasElement.removeEventListener('pointermove', this.onPointerMove);
            this.canvasElement.removeEventListener('pointerup', this.onPointerUp);
        }

        // Ensure the trigger is restored if the component is destroyed mid-drag
        if (this.lightboxTrigger && !this.lightboxTrigger.hasAttribute('data-bs-toggle')) {
            this.lightboxTrigger.setAttribute('data-bs-toggle', 'modal');
        }
    }
}
