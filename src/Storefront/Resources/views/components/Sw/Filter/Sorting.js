({ Shopware, ShopwareComponent } = window);

class FilterSorting extends ShopwareComponent {

    static selector = '[data-component="FilterSorting"]';

    init() {
        this.registerEvents();
    }

    registerEvents() {
        this.el.addEventListener('change', this.handleChange.bind(this));
    }

    handleChange(event) {
        const sorting = event.target.value;

        Shopware.emit(`${this.componentName}:Change`, sorting);

        this.dispatchEvent(`${this.componentName}:Change`, { sorting });
    }
}

Shopware.registerComponent('FilterSorting', FilterSorting);