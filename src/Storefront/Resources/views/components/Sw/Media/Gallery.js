export default class MediaGallery extends ShopwareComponent {
    static options = {
        zoomScale: 2.5,
        showMagnifier: true,
        thumbnailNavigationPosition: 'left',
    };

    init() {
        this.thumbnailButtons = this.el.querySelectorAll('[data-gallery-thumbnail-button]');
        this.previewsElements = this.el.querySelectorAll('.sw-media-gallery__preview');
        this.previewsContainer = this.el.querySelector('.sw-media-gallery__previews');
        this.previewItems = this.el.querySelectorAll('.sw-media-gallery__preview-item');
        this.counterBadge = this.el.querySelector('.sw-media-gallery__preview-item-badge');
        this.lightboxElement = this.el.querySelector('.sw-media-gallery__lightbox');

        this.initThumbnailNav();
        this.initPreviewZoom();

        this.initLightbox()
        // this.initLightboxZoom();
        this.initThumbnailNavScroller();
        this.initThumbnailNavScrollSync();
        this.initNavButtons();
    }

    initLightbox() {
        this.lightboxElement.addEventListener('show.bs.modal', (event) => {
            // Show the image in the lightbox with the matching id by scrolling to the item
            
            this.initLightboxZoom();
        });
    }

    initLightboxZoom() {
        const modalImgs = document.querySelectorAll('.sw-media-gallery__fullscreen-image-media');
        modalImgs.forEach((img) => {
                img.addEventListener('click', () => {
                img.classList.toggle('is--zoomed');
            });
        });
    }

    scrollToIndex(index) {
        const clamped = Math.max(0, Math.min(index, this.previewItems.length - 1));
        this.previewsContainer.scrollTo({
            left: clamped * this.previewsContainer.clientWidth,
            behavior: 'smooth',
        });
    }

    getCurrentIndex() {
        return Math.round(this.previewsContainer.scrollLeft / this.previewsContainer.clientWidth);
    }

    initThumbnailNav() {
        this.thumbnailButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const index = parseInt(button.dataset.target, 10) - 1;
                this.scrollToIndex(index);
            });
        });
    }

    // When container is scrolled/swiped manually, update the thumbnail nav active state
    initThumbnailNavScrollSync() {
        if (!this.previewsContainer) {
            return;
        }

        this.previewsContainer.addEventListener('scroll', () => {
            const index = Math.round(this.previewsContainer.scrollLeft / this.previewsContainer.clientWidth);

            this.thumbnailButtons.forEach((btn, i) => {
                btn.classList.toggle('is--active', i === index);
            });

            if (this.counterBadge) {
                this.counterBadge.textContent = `${index + 1} / ${this.previewItems.length}`;
            }
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

        const getVisiblePreview = () => {
            const index = Math.round(previewsContainer.scrollLeft / previewsContainer.clientWidth);
            return previews[index] ?? null;
        };

        // Always reset zoom when container is scrolled
        previewsContainer.addEventListener('scroll', () => {
            const img = getVisiblePreview();
            if (img) {
                img.style.transform = 'scale(1) translate(0px, 0px)';
                img.style.cursor = 'default';
            }
        });

        // TODO: Stop mouseenter zoom events when container is scrolled
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

    initNavButtons() {
        const backward = this.el.querySelector('.sw-media-gallery__nav-button.is--backward');
        const forward = this.el.querySelector('.sw-media-gallery__nav-button.is--forward');

        if (!backward || !forward) {
            return;
        }

        backward.addEventListener('click', () => this.scrollToIndex(this.getCurrentIndex() - 1));
        forward.addEventListener('click', () => this.scrollToIndex(this.getCurrentIndex() + 1));
    }

    destroy() {
        // TODO: Implement destroy method
    }
}
