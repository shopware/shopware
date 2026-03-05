export default class MediaGallery extends ShopwareComponent {
    static options = {
        showMagnifier: true,
        thumbnailNavigationPosition: 'left',
        thumbnailNavShowsMediaOnHover: true,
        showNavigationArrows: true,
        showFullScreenGallery: true,
        zoomScale: 2.5,
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
            container.classList.remove('is--zoomed');
            return;
        }

        container.classList.add('is--zoomed');

        // Load original image
        imageEl.removeAttribute('srcset');

        // Only attach drag listeners once per container
        if (container._dragListenersAdded) {
            return;
        }

        container._dragListenersAdded = true;

        // When lightbox preview item is zoomed, it becomes a scrollable container.
        // Native mousewheel scrolls or touch swipes can scroll the container normally.
        // Since there is no native mouse "drag-to-scroll" functionality, we change the scroll position when dragging the mouse.
        let isDown = false;
        let startX, startY, scrollLeft, scrollTop;

        container.addEventListener('mousedown', (e) => {
            isDown = true;
            container._dragOccurred = false;
            container.style.cursor = 'grabbing';
            startX = e.pageX - container.offsetLeft;
            startY = e.pageY - container.offsetTop;
            scrollLeft = container.scrollLeft;
            scrollTop = container.scrollTop;
        });

        container.addEventListener('mouseleave', () => { 
            isDown = false; 
            container.style.cursor = 'zoom-out'; 
        });

        container.addEventListener('mouseup', () => { 
            isDown = false; 
            container.style.cursor = 'zoom-out'; 
        });

        container.addEventListener('mousemove', (e) => {
            if (!isDown) {
                return;
            }
            e.preventDefault();
            const x = e.pageX - container.offsetLeft;
            const y = e.pageY - container.offsetTop;
            const deltaX = x - startX;
            const deltaY = y - startY;
            if (Math.abs(deltaX) > 3 || Math.abs(deltaY) > 3) {
                container._dragOccurred = true;
            }
            container.scrollLeft = scrollLeft - deltaX;
            container.scrollTop = scrollTop - deltaY;
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
        this.thumbnailNavScrollFordwardBtn.addEventListener('click', this.scrollForwardThumbnailNav.bind(this));
        this.thumbnailNavScrollBackBtn.addEventListener('click', this.scrollBackwardThumbnailNav.bind(this));

        this.updateThumbnailNavScrollArrows();
    }

    scrollForwardThumbnailNav() {
        this.thumbnailNavInner.scrollBy({ top: this.thumbnailNavInner.clientHeight / 2, behavior: 'smooth' });
    }

    scrollBackwardThumbnailNav() {
        this.thumbnailNavInner.scrollBy({ top: -(this.thumbnailNavInner.clientHeight / 2), behavior: 'smooth' });
    }

    updateThumbnailNavScrollArrows() {
        const canScrollUp = this.thumbnailNavInner.scrollTop > 0;
        const canScrollDown = this.thumbnailNavInner.scrollTop + this.thumbnailNavInner.clientHeight < this.thumbnailNavInner.scrollHeight - 1;

        this.thumbnailNavScrollBackBtn.toggleAttribute('hidden', !canScrollUp);
        this.thumbnailNavScrollFordwardBtn.toggleAttribute('hidden', !canScrollDown);
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
