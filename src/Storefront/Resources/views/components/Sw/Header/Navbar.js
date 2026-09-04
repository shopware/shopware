export default class Navbar extends window.ShopwareComponent {

    static options = {
        navItemActiveClassName: 'active',
        scrollable: true,
        scrollDistance: 450,
    };

    init() {
        this.navContainer = this.el.querySelector('.sw-navbar__nav');

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
        const navHeight = this.navContainer.offsetHeight;
        const flyoutElements = this.el.querySelectorAll('.sw-flyout');

        flyoutElements.forEach((el) => {
            el.style.top = `${navHeight}px`;
        });
    }

    attachMenuScroller() {
        this.leftBtn = this.el.querySelector('.sw-navbar__scroller-button.is--left');
        this.rightBtn = this.el.querySelector('.sw-navbar__scroller-button.is--right');

        this.handleScrollLeft = this.scrollLeft.bind(this);
        this.handleScrollRight = this.scrollRight.bind(this);
        this.handleMenuScrollerControls = this.toggleMenuScrollerControls.bind(this);

        this.toggleMenuScrollerControls();

        this.leftBtn.addEventListener('click', this.handleScrollLeft);
        this.rightBtn.addEventListener('click', this.handleScrollRight);
        this.navContainer.addEventListener('scroll', this.handleMenuScrollerControls);
    }

    toggleMenuScrollerControls() {
        const atStart = this.navContainer.scrollLeft === 0;
        const atEnd = this.navContainer.scrollLeft + this.navContainer.offsetWidth >= this.navContainer.scrollWidth;

        this.leftBtn.style.display = atStart ? 'none' : 'flex';
        this.rightBtn.style.display = atEnd ? 'none' : 'flex';
    }

    scrollLeft() {
        this.navContainer.scrollBy({ left: -this.options.scrollDistance, behavior: 'smooth' });
    }

    scrollRight() {
        this.navContainer.scrollBy({ left: this.options.scrollDistance, behavior: 'smooth' });
    }

    destroy() {
        this.leftBtn.removeEventListener('click', this.handleScrollLeft);
        this.rightBtn.removeEventListener('click', this.handleScrollRight);
        this.navContainer.removeEventListener('scroll', this.handleMenuScrollerControls);
    }
}
