export default class ProductReviewFilters extends ShopwareComponent {
    init() {
        this.onChange = this.onChange.bind(this);
        this.controls = this.el.querySelectorAll('.js-reviews-sort, .js-reviews-language-toggle');
        this.controls.forEach((el) => el.addEventListener('change', this.onChange));

        // A submitted review resets the list to the unfiltered default, so keep these controls in sync.
        this.onReviewSubmitted = this.reset.bind(this);
        window.Shopware.on('ReviewForm:Submitted', this.onReviewSubmitted);
    }

    destroy() {
        this.controls.forEach((el) => el.removeEventListener('change', this.onChange));
        window.Shopware.off('ReviewForm:Submitted', this.onReviewSubmitted);
    }

    reset() {
        const sort = this.el.querySelector('.js-reviews-sort');
        if (sort) {
            sort.value = 'createdAt';
        }

        const language = this.el.querySelector('.js-reviews-language-toggle');
        if (language) {
            language.checked = false;
        }
    }

    onChange() {
        const params = {};

        const sort = this.el.querySelector('.js-reviews-sort');
        if (sort && sort.value) {
            params.sort = sort.value;
        }

        const language = this.el.querySelector('.js-reviews-language-toggle');
        if (language && language.checked) {
            params.language = language.value;
        }

        // The Sw:Product:Reviews element on the page listens and reloads its list; the filters render none.
        window.Shopware.emit('ReviewFilters:Change', params);
    }
}
