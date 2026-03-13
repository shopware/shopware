export default class MediaGallery extends ShopwareComponent {
    static options = {
        zoomScale: 2.5,
        showMagnifier: true,
        thumbnailNavigationPosition: 'left',
    };

    init() {
        this.thumbnailButtons = this.el.querySelectorAll('[data-gallery-thumbnail-button]');
        this.previewsElements = this.el.querySelectorAll('.sw-media-gallery__preview');

        this.initThumbnailSwitching();
        this.initPreviewZoom();

        this.initModalZoom();
        this.initThumbnailNavScroller();
    }

    initModalZoom() {
        const modalImgs = document.querySelectorAll('.sw-media-gallery__fullscreen-image-media');
        modalImgs.forEach((img) => {
                img.addEventListener('click', () => {
                img.classList.toggle('is--zoomed');
            });
        });
    }

    initThumbnailSwitching() {
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

    initPreviewZoom() {
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

    initThumbnailNavScroller() {
        // Vertical nav scroller
        // When scrollbar is visible, show the scroll controls
        const verticalNav = this.el.querySelector('.sw-media-gallery__thumbnail-nav');
        const verticalNavInner = this.el.querySelector('.sw-thumbnail-nav__inner');
        const verticalNavScrollControl = this.el.querySelector('.sw-thumbnail-nav__scroll-control');

        verticalNavInner.addEventListener('scroll', () => {
            console.log('scrolling', verticalNavInner.scrollHeight, verticalNavInner.clientHeight);

            const isScrollbarVisible = verticalNavInner.scrollHeight > verticalNavInner.clientHeight;
            verticalNavScrollControl.style.display = isScrollbarVisible ? 'block' : 'none';
        });
    }

    destroy() {
        // TODO: Implement destroy method
    }
}
