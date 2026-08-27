export default class ProductSlider extends ShopwareComponent {

    static options = {
        products: [],
    };

    init() {
        this.list = this.el.querySelector('.sw-product-slider__list');
        this.backwardBtn = this.el.querySelector('.sw-product-slider__nav-button.is--backward');
        this.forwardBtn = this.el.querySelector('.sw-product-slider__nav-button.is--forward');

        this.initNavigationArrows();
    }

    initNavigationArrows() {
        if (!this.list || !this.backwardBtn || !this.forwardBtn) {
            return;
        }

        this.onListScroll = this.updateNavigationArrows.bind(this);
        this.onBackwardClick = this.scrollBackward.bind(this);
        this.onForwardClick = this.scrollForward.bind(this);

        this.list.addEventListener('scroll', this.onListScroll);
        this.backwardBtn.addEventListener('click', this.onBackwardClick);
        this.forwardBtn.addEventListener('click', this.onForwardClick);

        // Whether the list overflows depends on the available width, which changes with the viewport
        this.resizeObserver = new ResizeObserver(this.onListScroll);
        this.resizeObserver.observe(this.list);

        this.updateNavigationArrows();
    }

    getScrollDistance() {
        return this.list.clientWidth;
    }

    scrollForward() {
        this.scrollListBy(this.getScrollDistance());
    }

    scrollBackward() {
        this.scrollListBy(-this.getScrollDistance());
    }

    scrollListBy(amount) {
        this.list.scrollBy({
            left: amount,
            behavior: 'smooth',
        });
    }

    updateNavigationArrows() {
        const scrollPos = this.list.scrollLeft;
        const clientSize = this.list.clientWidth;
        const scrollSize = this.list.scrollWidth;

        this.backwardBtn.toggleAttribute('hidden', scrollPos <= 0);
        this.forwardBtn.toggleAttribute('hidden', scrollPos + clientSize >= scrollSize - 1);
    }

    destroy() {
        if (this.list) {
            this.list.removeEventListener('scroll', this.onListScroll);
        }

        if (this.backwardBtn) {
            this.backwardBtn.removeEventListener('click', this.onBackwardClick);
        }

        if (this.forwardBtn) {
            this.forwardBtn.removeEventListener('click', this.onForwardClick);
        }

        this.resizeObserver?.disconnect();
    }
}