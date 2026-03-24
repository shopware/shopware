({ Shopware, ShopwareComponent } = window);

export default class BooleanFilter extends ShopwareComponent {

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

        ({ paramName, value, label } = Shopware.emitInterception(`BooleanFilter:PreChange`, { paramName, value, label }));

        Shopware.emit(`BooleanFilter:Change`, { paramName, value, label });
        Shopware.emit('Filter:Change', { paramName, value, label });

        this.dispatchEvent(`BooleanFilter:Update`, {
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
            Shopware.emitQueued('Filter:Init', {
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

    destroy() {
        this.checkbox.removeEventListener('change', this.handleChange.bind(this));

        Shopware.off(`Filter:Remove:${this.paramName}`, this.handleFilterRemove.bind(this));
    }
}
