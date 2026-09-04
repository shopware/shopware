export default class RatingFilter extends ShopwareComponent {

    static options = {
        maxStars: 5,
        displayName: 'Rating',
    };

    init() {
        this.inputs = this.el.querySelectorAll('input');
        this.paramName = this.inputs[0].getAttribute('name').trim();

        this.setStateFromUrl();
        this.registerEvents();
    }

    registerEvents() {
        this.inputs.forEach(input => {
            input.addEventListener('change', this.handleChange.bind(this));
        });

        Shopware.on(`Filter:Remove:${this.paramName}`, this.handleFilterRemove.bind(this));
    }

    reset() {
        this.inputs.forEach(input => {
            input.checked = false;
        });
    }

    handleChange(event) {
        const inputElement = event.target;
        let value = inputElement.value;
        let paramName = this.paramName;
        let label = this.getLabel(value);

        ({ paramName, value, label } = Shopware.emitInterception('RatingFilter:PreChange', { paramName, value, label }));

        Shopware.emit('RatingFilter:Change', { paramName, value, label });
        Shopware.emit('Filter:Change', { paramName, value, label });

        this.updateBadge(value, this.options.maxStars);
    }

    handleFilterRemove({ paramName }) {
        if (paramName === this.paramName) {
            this.inputs.forEach(input => {
                input.checked = false;
            });

            this.updateBadge(0, this.options.maxStars);
        }
    }

    setStateFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        const value = urlParams.get(this.paramName);
        let inputElement = null;

        this.inputs.forEach(input => {
            input.checked = input.value === value;
            if (input.checked) {
                inputElement = input;
            }
        });

        if (inputElement) {
            Shopware.emitQueued('Filter:Init', {
                paramName: this.paramName,
                value,
                label: this.getLabel(value),
            });

            this.updateBadge(value, this.options.maxStars);
        }
    }

    updateBadge(value, max) {
        this.dispatchEvent('Filter:UpdateBadge', {
            content: value > 0 ? `${value}/${max}` : '',
        });
    }

    getLabel(value, max = this.options.maxStars) {
        return value > 0 ? `${this.options.displayName} ${value}/${max}` : '';
    }

    destroy() {
        this.inputs.forEach(input => {
            input.removeEventListener('change', this.handleChange.bind(this));
        });

        Shopware.off(`Filter:Remove:${this.paramName}`, this.handleFilterRemove.bind(this));
    }
}
