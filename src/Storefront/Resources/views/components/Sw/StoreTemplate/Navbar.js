export default class Navbar extends window.ShopwareComponent {

    static options = {
        navItemActiveClassName: 'active',
    }

    init() {
        this.setActiveState();
    }

    setActiveState() {
        const activeItem = this.el.querySelector(`[data-category-id="${window.activeNavigationId}"]`);

        if (!activeItem) {
            return;
        }

        activeItem.classList.add(this.options.navItemActiveClassName);
    }
}
