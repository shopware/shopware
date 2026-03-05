export default class MediaSpatial extends ShopwareComponent {
    init() {
        this.spatialBaseViewerInstance = null;
        this.canvasElement = this.el.querySelector('[data-spatial-base-viewer]');
        this.loaderElement = this.el.querySelector('.sw-media-spatial__loader');

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

    destroy() {
        if (this.observer) {
            this.observer.disconnect();
        }
    }
}