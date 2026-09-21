export default class MediaGallery extends ShopwareComponent {
    static options = {
        showMagnifier: true,
        thumbnailNavigationPosition: 'left',
        thumbnailNavShowsMediaOnHover: false,
        showNavigationArrows: true,
        showFullScreenGallery: true,
        zoomScale: 2.5,
        clickZoomScale: 2,
        isLightbox: false,
        counterDeviderLabel: 'of',
    };

    init() {
        // Thumbnail navigation buttons
        this.thumbnailButtons = this.el.querySelectorAll('.sw-thumbnail-nav__button');
        this.thumbnailNavInner = this.el.querySelector('.sw-thumbnail-nav__inner');        
        this.thumbnailNavScrollBackBtn = this.el.querySelector('.sw-thumbnail-nav__scroll-control.is--backward');
        this.thumbnailNavScrollFordwardBtn = this.el.querySelector('.sw-thumbnail-nav__scroll-control.is--forward');

        // Previews scroll container
        this.previewsContainer = this.el.querySelector('.sw-media-gallery__previews');
        this.previewItems = this.el.querySelectorAll('.sw-media-gallery__preview-item');

        // Counter
        this.counterBadge = this.el.querySelector('.sw-media-galley__counter-info');

        // Arrow navigation buttons
        this.backwardBtn = this.el.querySelector('.sw-media-gallery__nav-button.is--backward');
        this.forwardBtn = this.el.querySelector('.sw-media-gallery__nav-button.is--forward');

        this.initThumbnailNav();
        this.initThumbnailNavScrollArrows();
        this.initThumbnailNavScrollSync();

        if (this.options.showMagnifier) {
            this.initPreviewZoom();
        }

        if (this.options.showNavigationArrows) {
            this.initNavigationArrows();
        }

        if (this.options.isLightbox) {
            this.initLightbox();
        }
    }

    initNavigationArrows() {
        if (!this.backwardBtn || !this.forwardBtn) {
            return;
        }

        this.onBackwardClick = () => this.scrollToIndex(this.getCurrentIndex() - 1);
        this.onForwardClick = () => this.scrollToIndex(this.getCurrentIndex() + 1);
        this.backwardBtn.addEventListener('click', this.onBackwardClick);
        this.forwardBtn.addEventListener('click', this.onForwardClick);
    }

    initLightbox() {
        // Find parent modal element
        this.modalElement = this.el.closest('.modal.sw-media-gallery-lightbox');

        if (!this.modalElement) {
            return;
        }

        // After modal is shown, scroll to the correct item
        this.onSetLightboxScrollPosition = this.setLightboxScrollPosition.bind(this);
        this.modalElement.addEventListener('shown.bs.modal', this.onSetLightboxScrollPosition);

        this.initLightboxClickZoom();
    }

    setLightboxScrollPosition(event) {
        // Find index of item that triggered the lightbox modal
        const targetIndex = event.relatedTarget?.dataset?.mediaId;

        if (!targetIndex) {
            return;
        }

        const index = parseInt(targetIndex, 10) - 1;
        this.scrollToIndex(index, 'instant');
    }

    initLightboxClickZoom() {
        if (!this.previewItems.length) {
            return;
        }

        this.onToggleClickZoom = this.toggleClickZoom.bind(this);
        this.previewItems.forEach((container) => {
            container.addEventListener('click', this.onToggleClickZoom);
        });
    }

    toggleClickZoom(event) {
        const container = event.currentTarget;
        const imageEl = container.querySelector(':scope > img');

        // Only enable click zoom when container has an image
        if (!imageEl) {
            return;
        }

        // Suppress the click that fires after a drag ends
        if (container._dragOccurred) {
            container._dragOccurred = false;
            return;
        }

        if (container.classList.contains('is--zoomed')) {
            this.disableClickZoom(container);
            return;
        }

        this.enableClickZoom(container, imageEl, event);
    }

    enableClickZoom(container, imageEl, event) {
        const scale = this.options.clickZoomScale;
        const imageRect = imageEl.getBoundingClientRect();
        const containerRect = container.getBoundingClientRect();

        if (imageRect.width === 0 || imageRect.height === 0) {
            return;
        }

        const originX = Math.min(Math.max(event.clientX - imageRect.left, 0), imageRect.width);
        const originY = Math.min(Math.max(event.clientY - imageRect.top, 0), imageRect.height);

        container._clickZoom = {
            imageEl,
            scale,
            originX,
            originY,
            panX: 0,
            panY: 0,
            imageWidth: imageRect.width,
            imageHeight: imageRect.height,
            imageOffsetX: imageRect.left - containerRect.left,
            imageOffsetY: imageRect.top - containerRect.top,
        };

        this.applyClickZoomTransform(container._clickZoom);
        container.classList.add('is--zoomed');
        imageEl.removeAttribute('srcset');

        this.initZoomDrag(container);
    }

    disableClickZoom(container) {
        const zoom = container._clickZoom;
        container.classList.remove('is--zoomed', 'is--panning');

        if (zoom?.imageEl) {
            zoom.imageEl.style.transform = 'scale(1)';
            zoom.imageEl.style.removeProperty('transform-origin');
        }

        container._clickZoom = null;
    }

    applyClickZoomTransform(zoom) {
        zoom.imageEl.style.transformOrigin = `${zoom.originX}px ${zoom.originY}px`;
        zoom.imageEl.style.transform = `translate(${zoom.panX}px, ${zoom.panY}px) scale(${zoom.scale})`;
    }

    /**
     * Keep the scaled image covering the preview when it is larger than the viewport,
     * and fully inside it when it is smaller — never pan so far that the image leaves the canvas.
     */
    clampClickZoomPan(container, zoom) {
        const viewport = container.getBoundingClientRect();
        const originShiftX = zoom.originX * (1 - zoom.scale);
        const originShiftY = zoom.originY * (1 - zoom.scale);
        const scaledWidth = zoom.imageWidth * zoom.scale;
        const scaledHeight = zoom.imageHeight * zoom.scale;

        const visLeft = this.clampClickZoomAxis(
            zoom.imageOffsetX + originShiftX + zoom.panX,
            scaledWidth,
            viewport.width,
        );
        const visTop = this.clampClickZoomAxis(
            zoom.imageOffsetY + originShiftY + zoom.panY,
            scaledHeight,
            viewport.height,
        );

        zoom.panX = visLeft - zoom.imageOffsetX - originShiftX;
        zoom.panY = visTop - zoom.imageOffsetY - originShiftY;
    }

    clampClickZoomAxis(position, scaledSize, viewportSize) {
        if (scaledSize >= viewportSize) {
            return Math.min(0, Math.max(viewportSize - scaledSize, position));
        }

        return Math.min(viewportSize - scaledSize, Math.max(0, position));
    }

    initZoomDrag(container) {
        if (container._dragListenersAdded) {
            return;
        }

        container._dragListenersAdded = true;

        let isDown = false;
        let startX = 0;
        let startY = 0;
        let startPanX = 0;
        let startPanY = 0;

        container.addEventListener('mousedown', (e) => {
            const zoom = container._clickZoom;
            if (!zoom) {
                return;
            }
            isDown = true;
            container._dragOccurred = false;
            container.classList.add('is--panning');
            container.style.cursor = 'grabbing';
            startX = e.clientX;
            startY = e.clientY;
            startPanX = zoom.panX;
            startPanY = zoom.panY;
        });

        const endPan = () => {
            isDown = false;
            container.classList.remove('is--panning');
            container.style.cursor = 'zoom-out';
        };

        container.addEventListener('mouseleave', endPan);
        container.addEventListener('mouseup', endPan);

        container.addEventListener('mousemove', (e) => {
            const zoom = container._clickZoom;
            if (!isDown || !zoom) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            const deltaX = e.clientX - startX;
            const deltaY = e.clientY - startY;
            if (Math.abs(deltaX) > 3 || Math.abs(deltaY) > 3) {
                container._dragOccurred = true;
            }
            zoom.panX = startPanX + deltaX;
            zoom.panY = startPanY + deltaY;
            this.clampClickZoomPan(container, zoom);
            this.applyClickZoomTransform(zoom);
        });
    }

    scrollToIndex(index, behavior = 'smooth') {
        const clamped = Math.max(0, Math.min(index, this.previewItems.length - 1));
        this.previewsContainer.scrollTo({
            left: clamped * this.previewsContainer.clientWidth,
            behavior: behavior,
        });
    }

    getCurrentIndex() {
        return Math.round(this.previewsContainer.scrollLeft / this.previewsContainer.clientWidth);
    }

    initThumbnailNav() {
        this.thumbnailButtons.forEach((button) => {
            const index = parseInt(button.dataset.target, 10) - 1;

            button.addEventListener('click', () => {
                this.scrollToIndex(index, 'smooth');
            });

            if (this.options.thumbnailNavShowsMediaOnHover) {
                button.addEventListener('mouseover', () => {
                    this.scrollToIndex(index, 'instant');
                });
            }
        });
    }

    // When container is scrolled/swiped manually, update the thumbnail nav active state
    initThumbnailNavScrollSync() {
        if (!this.previewsContainer) {
            return;
        }

        this.onPreviewsContainerScroll = this.onPreviewsContainerScroll.bind(this);
        this.previewsContainer.addEventListener('scroll', this.onPreviewsContainerScroll);
    }

    onPreviewsContainerScroll() {
        const index = this.getCurrentIndex();
        this.updateThumbnailNavActiveState(index);
        this.updateCounter(index);
    }

    updateThumbnailNavActiveState(index) {
        this.thumbnailButtons.forEach((btn, i) => {
            if (i === index) {
                btn.classList.add('is--active');
                btn.setAttribute('aria-current', 'true');
            } else {
                btn.classList.remove('is--active');
                btn.removeAttribute('aria-current');
            }
        });
    }

    updateCounter(index) {
        if (!this.counterBadge) {
            return;
        }    
        this.counterBadge.textContent = `${index + 1} ${this.options.counterDeviderLabel} ${this.previewItems.length}`;
    }

    initPreviewZoom() {
        const scale = this.options.zoomScale;

        if (!this.previewsContainer) {
            return;
        }

        this.previewItems.forEach((preview) => {
            preview.style.transform = 'scale(1) translate(0px, 0px)';
            preview.style.transformOrigin = '0px 0px 0px';
        });

        const getVisiblePreview = () => {
            const index = Math.round(this.previewsContainer.scrollLeft / this.previewsContainer.clientWidth);
            // Only allow zoom when image is direct child of the preview item
            const preview = this.previewItems[index] ?? null;
            return preview?.querySelector(':scope > img') ? preview : null;
        };

        // Always reset zoom when container is scrolled
        this.previewsContainer.addEventListener('scroll', () => {
            const preview = getVisiblePreview();
            if (preview) {
                preview.style.transform = 'scale(1) translate(0px, 0px)';
                preview.style.cursor = 'default';
            }
        });

        this.previewsContainer.addEventListener('mouseenter', () => {
            const preview = getVisiblePreview();
            if (preview) {
                preview.style.transform = `scale(${scale}) translate(0px, 0px)`;
                preview.style.cursor = 'zoom-in';
            }
        });

        this.previewsContainer.addEventListener('mousemove', (e) => {
            const preview = getVisiblePreview();
            if (!preview) {
                return;
            }
            const rect = this.previewsContainer.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const tx = -x * (scale - 1) / scale;
            const ty = -y * (scale - 1) / scale;
            preview.style.transform = `scale(${scale}) translate(${tx}px, ${ty}px)`;
        });

        this.previewsContainer.addEventListener('mouseleave', () => {
            const preview = getVisiblePreview();
            if (preview) {
                preview.style.transform = 'scale(1) translate(0px, 0px)';
                preview.style.cursor = 'default';
            }
        });
    }

    initThumbnailNavScrollArrows() {
        if (!this.thumbnailNavInner || !this.thumbnailNavScrollBackBtn || !this.thumbnailNavScrollFordwardBtn) {
            return;
        }

        this.thumbnailNavInner.addEventListener('scroll', this.updateThumbnailNavScrollArrows.bind(this));
        this.thumbnailNavScrollFordwardBtn.addEventListener('click', this.scrollThumbnailNavForward.bind(this));
        this.thumbnailNavScrollBackBtn.addEventListener('click', this.scrollThumbnailNavBackward.bind(this));

        this.updateThumbnailNavScrollArrows();
    }

    isThumbnailNavHorizontal() {
        return this.options.thumbnailNavigationPosition === 'bottom';
    }

    getThumbnailNavScrollDistance() {
        if (this.isThumbnailNavHorizontal()) {
            return this.thumbnailNavInner.clientWidth / 2;
        }

        return this.thumbnailNavInner.clientHeight / 2;
    }

    scrollThumbnailNavForward() {
        this.scrollThumbnailNavBy(this.getThumbnailNavScrollDistance());
    }

    scrollThumbnailNavBackward() {
        this.scrollThumbnailNavBy(-this.getThumbnailNavScrollDistance());
    }

    scrollThumbnailNavBy(amount) {
        this.thumbnailNavInner.scrollBy({
            left: this.isThumbnailNavHorizontal() ? amount : 0,
            top: this.isThumbnailNavHorizontal() ? 0 : amount,
            behavior: 'smooth',
        });
    }

    updateThumbnailNavScrollArrows() {
        const isHorizontal = this.isThumbnailNavHorizontal();
        const scrollPos = isHorizontal ? this.thumbnailNavInner.scrollLeft : this.thumbnailNavInner.scrollTop;
        const clientSize = isHorizontal ? this.thumbnailNavInner.clientWidth : this.thumbnailNavInner.clientHeight;
        const scrollSize = isHorizontal ? this.thumbnailNavInner.scrollWidth : this.thumbnailNavInner.scrollHeight;

        this.thumbnailNavScrollBackBtn.toggleAttribute('hidden', scrollPos <= 0);
        this.thumbnailNavScrollFordwardBtn.toggleAttribute('hidden', scrollPos + clientSize >= scrollSize - 1);
    }

    destroy() {
        if (this.previewsContainer) {
            this.previewsContainer.removeEventListener('scroll', this.onPreviewsContainerScroll);
        }

        if (this.backwardBtn) {
            this.backwardBtn.removeEventListener('click', this.onBackwardClick);
        }

        if (this.forwardBtn) {
            this.forwardBtn.removeEventListener('click', this.onForwardClick);
        }

        if (this.modalElement) {
            this.modalElement.removeEventListener('shown.bs.modal', this.onSetLightboxScrollPosition);
        }

        if (this.options.isLightbox) {
            this.previewItems.forEach((container) => {
                container.removeEventListener('click', this.onToggleClickZoom);
            });
        }
    }
}