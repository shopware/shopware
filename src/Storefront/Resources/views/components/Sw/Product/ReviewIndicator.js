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

        if (tabTrigger) {
            // Open the reviews tab and move focus to its tab button.
            // `preventScroll` keeps `focus()` from jumping and cutting off the smooth scroll.
            tabTrigger.click();
            tabTrigger.focus({ preventScroll: true });
            (tabTrigger.closest('.sw-tabs') ?? tabTrigger).scrollIntoView({ behavior: 'smooth', block: 'start' });

            return;
        }

        // Without a tab, focus the reviews section itself. `tabindex="-1"` makes it focusable by script without adding it to the tab order.
        if (!this.reviews.hasAttribute('tabindex')) {
            this.reviews.setAttribute('tabindex', '-1');
        }

        this.reviews.focus({ preventScroll: true });
        this.reviews.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}
