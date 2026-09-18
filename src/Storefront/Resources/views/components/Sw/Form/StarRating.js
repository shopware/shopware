export default class StarRating extends ShopwareComponent {

    static options = {
        pointAttr: 'data-star-point',
        textAttr: 'data-star-text',
        activeClass: 'is-active',
        hiddenClass: 'd-none',
    };

    init() {
        this.stars = this.el.querySelectorAll(`[${this.options.pointAttr}]`);
        this.texts = this.el.querySelectorAll(`[${this.options.textAttr}]`);

        this.onStarClick = this.onStarClick.bind(this);
        this.stars.forEach((star) => star.addEventListener('click', this.onStarClick));
    }

    destroy() {
        this.stars.forEach((star) => star.removeEventListener('click', this.onStarClick));
    }

    onStarClick(event) {
        // The native label click also checks the radio inside, so the submitted value follows the fill.
        this.setRating(event.currentTarget.getAttribute(this.options.pointAttr));
    }

    setRating(points) {
        this.stars.forEach((star) => {
            const value = star.getAttribute(this.options.pointAttr);
            star.classList.toggle(this.options.activeClass, Number(value) <= Number(points));
        });

        this.texts.forEach((text) => {
            text.classList.toggle(this.options.hiddenClass, text.getAttribute(this.options.textAttr) !== String(points));
        });
    }
}
