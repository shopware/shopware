export default class RangeFilter extends ShopwareComponent {

    static options = {
        unit: '',
        minLabel: 'Min',
        maxLabel: 'Max',
    };

    init() {
        this.rangeMinInput = this.el.querySelector('.sw-range-filter__min-input');
        this.rangeMaxInput = this.el.querySelector('.sw-range-filter__max-input');

        this.minParamName = this.rangeMinInput.getAttribute('name').trim();
        this.maxParamName = this.rangeMaxInput.getAttribute('name').trim();

        this.setStateFromUrl();
        this.registerEvents();
    }

    registerEvents() {
        this.rangeMinInput.addEventListener('input', this.debounce(this.handleRangeInput.bind(this)));
        this.rangeMaxInput.addEventListener('input', this.debounce(this.handleRangeInput.bind(this)));

        Shopware.on(`Filter:Remove:${this.minParamName}`, this.handleFilterRemove.bind(this));
        Shopware.on(`Filter:Remove:${this.maxParamName}`, this.handleFilterRemove.bind(this));
    }

    reset() {
        this.rangeMinInput.value = '';
        this.rangeMaxInput.value = '';
    }

    handleRangeInput(event) {
        const inputElement = event.target;
        let value = inputElement.value;
        let unit = this.options.unit;
        let paramName = inputElement.getAttribute('name').trim();
        let label = paramName === this.minParamName ? this.getMinLabel(value) : this.getMaxLabel(value);

        ({ paramName, value, label, unit } = Shopware.emitInterception('RangeFilter:PreChange', { paramName, value, label, unit }));

        Shopware.emit('RangeFilter:Change', { paramName, value, label, unit });
        Shopware.emit('Filter:Change', { paramName, value, label, unit });

        this.updateBadge();
    }

    handleFilterRemove({ paramName }) {
        if (paramName === this.minParamName) {
            this.rangeMinInput.value = '';
        } else if (paramName === this.maxParamName) {
            this.rangeMaxInput.value = '';
        }

        this.updateBadge();
    }

    setStateFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        const minValue = urlParams.get(this.minParamName);
        const maxValue = urlParams.get(this.maxParamName);

        this.rangeMinInput.value = minValue || null;
        this.rangeMaxInput.value = maxValue || null;

        if (minValue) {
            Shopware.emitQueued('Filter:Init', {
                paramName: this.minParamName,
                value: minValue,
                label: this.getMinLabel(minValue),
                unit: this.options.unit,
            });
        }

        if (maxValue) {
            Shopware.emitQueued('Filter:Init', {
                paramName: this.maxParamName,
                value: maxValue,
                label: this.getMaxLabel(maxValue),
                unit: this.options.unit,
            });
        }

        this.updateBadge();
    }

    updateBadge() {
        const min = this.rangeMinInput.value;
        const max = this.rangeMaxInput.value;
        const unit = this.options.unit;

        let content = min ? max > 0 ? `${min}${unit}` : `>= ${min}${unit}` : '';
        content += min && max ? ' - ' : '';
        content += max ? min > 0 ? `${max}${unit}` : `<= ${max}${unit}` : '';

        this.dispatchEvent('Filter:UpdateBadge', {
            content,
        });
    }

    getMinLabel(value) {
        return value > 0 ? `${this.options.minLabel}: ${value}${this.options.unit}` : '';
    }

    getMaxLabel(value) {
        return value > 0 ? `${this.options.maxLabel}: ${value}${this.options.unit}` : '';
    }

    destroy() {
        this.rangeMinInput.removeEventListener('input', this.debounce(this.handleRangeInput.bind(this)));
        this.rangeMaxInput.removeEventListener('input', this.debounce(this.handleRangeInput.bind(this)));

        Shopware.off(`Filter:Remove:${this.minParamName}`, this.handleFilterRemove.bind(this));
        Shopware.off(`Filter:Remove:${this.maxParamName}`, this.handleFilterRemove.bind(this));
    }
}
