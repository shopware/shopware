class Pagination extends ShopwareComponent {

    static selector = 'nav[data-component="Pagination"]';

    static options = {
        useHref: true,
    };

    init() {
        this.items = this.el.querySelectorAll('a[data-page]');

        this.items.forEach(item => {
            item.addEventListener('click', this.handleClick.bind(this, item));
        });
    }

    handleClick(item, event) {
        if (!this.options.useHref) {
            event.preventDefault();
        }

        let page = parseInt(item.getAttribute('data-page') ?? 1, 10);

        ({ page } = window.Shopware.emitInterception(`${this.componentName}:PrePageChange`, { page }));

        window.Shopware.emit(`${this.componentName}:PageChange`, page);
    }

    destroy() {
        this.items.forEach(item => {
            item.removeEventListener('click', this.handleClick.bind(this, item));
        });
    }
}

window.Shopware.registerComponent('Pagination', Pagination);
