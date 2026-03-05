export default class MediaGallery extends ShopwareComponent {
    static options = {
        zoomScale: 2.5,
    };

    init() {
        this.thumbnailButtons = this.el.querySelectorAll('.sw-media-gallery__thumbnail-button');
        this.previewsElements = this.el.querySelectorAll('.sw-media-gallery__preview');

        this._initThumbnailSwitching();
        this._initPreviewZoom();
    }

    _initThumbnailSwitching() {
        this.thumbnailButtons.forEach((button) => {
            button.addEventListener('mouseover', () => {
                const targetId = button.dataset.target;

                this.thumbnailButtons.forEach((btn) => {
                    btn.classList.toggle('is--active', btn.dataset.target === targetId);
                });

                this.previewsElements.forEach((preview) => {
                    preview.hidden = preview.dataset.mediaId !== targetId;
                });
            });
        });
    }

    _initPreviewZoom() {
        const scale = this.constructor.options.zoomScale;
        const previewsContainer = this.el.querySelector('.sw-media-gallery__previews');
        const previews = this.el.querySelectorAll('.sw-media-gallery__preview');

        if (!previewsContainer) {
            return;
        }

        previews.forEach((img) => {
            img.style.transform = 'scale(1) translate(0px, 0px)';
            img.style.transformOrigin = '0px 0px 0px';
        });

        const getVisiblePreview = () =>
            Array.from(previews).find((preview) => !preview.hidden) ?? null;

        previewsContainer.addEventListener('mouseenter', () => {
            const img = getVisiblePreview();
            if (img) {
                img.style.transform = `scale(${scale}) translate(0px, 0px)`;
                img.style.cursor = 'zoom-in';
            }
        });

        previewsContainer.addEventListener('mousemove', (e) => {
            const img = getVisiblePreview();
            if (!img) {
                return;
            }
            const rect = previewsContainer.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const tx = -x * (scale - 1) / scale;
            const ty = -y * (scale - 1) / scale;
            img.style.transform = `scale(${scale}) translate(${tx}px, ${ty}px)`;
        });

        previewsContainer.addEventListener('mouseleave', () => {
            const img = getVisiblePreview();
            if (img) {
                img.style.transform = 'scale(1) translate(0px, 0px)';
                img.style.cursor = 'default';
            }
        });
    }
}
