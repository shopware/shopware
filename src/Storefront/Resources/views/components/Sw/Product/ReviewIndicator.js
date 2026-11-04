export default class ProductReviewIndicator extends ShopwareComponent {

    static options = {
        linkSelector: '.sw-product-review-indicator__link',
        reviewsSelector: '[data-component="Sw:Product:Reviews"]',
    };

    init() {
        this.link = this.el.querySelector(this.options.linkSelector);
        this.reviews = document.querySelector(this.options.reviewsSelector);

        if (!this.link || !this.reviews) {
            return;
        }

        this.onLinkClick = this.handleLinkClick.bind(this);
        this.link.addEventListener('click', this.onLinkClick);
    }

    destroy() {
        this.link?.removeEventListener('click', this.onLinkClick);
    }

    handleLinkClick() {
        const paneId = this.reviews.closest('.tab-pane')?.id;
        const tabTrigger = paneId ? document.querySelector(`[data-bs-target="#${paneId}"]`) : null;

        tabTrigger?.click();

        (tabTrigger?.closest('.sw-tabs') ?? this.reviews).scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}
