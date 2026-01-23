export default class Navbar extends window.ShopwareComponent {

    static options = {
        navItemActiveClassName: 'active',
        scrollable: true,
        scrollDistance: 450,
    }

    init() {
        this._navContainer = this.el.querySelector('.sw-navbar__nav');

        this.setActiveState();
        this.setFlyoutPosition();

        if (this.options.scrollable) {
            this.attachMenuScroller();
        }
    }

    setActiveState() {
        const activeItem = this.el.querySelector(`[data-category-id="${window.activeNavigationId}"]`);

        if (!activeItem) {
            return;
        }

        activeItem.classList.add(this.options.navItemActiveClassName);
    }

    setFlyoutPosition() {
        const navHeight = this._navContainer.offsetHeight;
        const flyoutElements = this.el.querySelectorAll('.sw-flyout');

        flyoutElements.forEach((el) => {
            el.style.top = `${navHeight}px`;
        });
    }

    attachMenuScroller() {
        this._leftBtn = this.el.querySelector('.sw-navbar__scroller-button.is--left');
        this._rightBtn = this.el.querySelector('.sw-navbar__scroller-button.is--right');

        this.toggleMenuScrollerControls();

        this._leftBtn.addEventListener('click', this.scrollLeft.bind(this));
        this._rightBtn.addEventListener('click', this.scrollRight.bind(this));
        this._navContainer.addEventListener('scroll', this.toggleMenuScrollerControls.bind(this));
    }

    toggleMenuScrollerControls() {
        const atStart = this._navContainer.scrollLeft === 0;
        const atEnd = this._navContainer.scrollLeft + this._navContainer.offsetWidth >= this._navContainer.scrollWidth;

        this._leftBtn.style.display = atStart ? 'none' : 'flex';
        this._rightBtn.style.display = atEnd ? 'none' : 'flex';
    }

    scrollLeft() {
        this._navContainer.scrollBy({ left: -this.options.scrollDistance, behavior: 'smooth' });
    }

    scrollRight() {
        this._navContainer.scrollBy({ left: this.options.scrollDistance, behavior: 'smooth' });
    }
}
