({ Shopware, ShopwareComponent } = window);

class BooleanFilter extends ShopwareComponent {
    static selector = '[data-component="BooleanFilter"]';

    init() {
        this.checkbox = this.el.querySelector('.sw-boolean-filter__input');
        this.paramName = this.checkbox.getAttribute('name').trim();
        this.label = this.getLabel();

        this.setStateFromUrl();
        this.registerEvents();
    }

    registerEvents() {
        this.checkbox.addEventListener('change', this.handleChange.bind(this));

        Shopware.on(`Filter:Remove:${this.paramName}`, this.handleFilterRemove.bind(this));
    }

    handleChange() {
        let input = this.checkbox;
        let value = input.checked;
        let paramName = this.paramName;
        let label = this.label;

        ({ paramName, value, label } = Shopware.emitInterception(`${this.componentName}:PreChange`, { paramName, value, label }));

        Shopware.emit(`${this.componentName}:Change`, { paramName, value, label });
        Shopware.emit('Filter:Change', { paramName, value, label });

        this.dispatchEvent(`${this.componentName}:Update`, {
            paramName,
            value,
            label,
        });
    }

    handleFilterRemove({ paramName }) {
        if (paramName === this.paramName) {
            this.checkbox.checked = false;
        }
    }

    setStateFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        const value = urlParams.get(this.paramName);
        this.checkbox.checked = value === 'true' || value === '1';

        if (this.checkbox.checked) {
            Shopware.emit('Filter:Init', {
                paramName: this.paramName,
                value: true,
                label: this.label,
            });
        }
    }

    getLabel() {
        const labelEl = this.el.querySelector('label[for="' + this.checkbox.getAttribute('id') + '"]');
        return labelEl ? labelEl.textContent.trim() : this.paramName;
    }
}

Shopware.registerComponent('BooleanFilter', BooleanFilter);