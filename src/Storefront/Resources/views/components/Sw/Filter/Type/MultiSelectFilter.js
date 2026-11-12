export default class MultiSelectFilter extends ShopwareComponent {

    init() {
        this.activeOptions = [];
        this.searchInput = this.el.querySelector('.sw-multi-select-filter__search');
        this.options = this.el.querySelectorAll('.sw-multi-select-filter__list-item');
        this.optionInputs = this.el.querySelectorAll('.sw-multi-select-filter__list-item-checkbox');
        this.paramName = this.optionInputs[0].getAttribute('name').trim();

        this.setStateFromUrl();
        this.registerEvents();
    }

    registerEvents() {
        this.searchInput.addEventListener('input', this.handleSearchInput.bind(this));

        this.optionInputs.forEach(input => {
            input.addEventListener('change', this.handleOptionChange.bind(this));
        });

        Shopware.on(`Filter:Remove:${this.paramName}`, this.handleFilterRemove.bind(this));
    }

    reset() {
        this.activeOptions = [];
        this.optionInputs.forEach(input => {
            input.checked = false;
        });
    }

    handleSearchInput(event) {
        let searchTerm = event.target.value;

        ({ searchTerm } = Shopware.emitInterception('MultiSelectFilter:PreSearch', { searchTerm }));

        if (searchTerm.length === 0) {
            this.options.forEach(option => {
                option.classList.remove('is--hidden');
            });

            return;
        }

        this.options.forEach(option => {
            if (option.textContent.toLowerCase().includes(searchTerm.toLowerCase())) {
                option.classList.remove('is--hidden');
            } else {
                option.classList.add('is--hidden');
            }
        });

        Shopware.emit('MultiSelectFilter:Search', searchTerm);
    }

    handleOptionChange(event) {
        const inputElement = event.target;
        const removedOptions = [];
        let option = inputElement.value;
        let value = inputElement.checked;
        let paramName = this.paramName;
        let label = this.getLabelFromInput(inputElement);

        ({ paramName, value, option, label } = Shopware.emitInterception('MultiSelectFilter:PreChange', { paramName, value, option, label }));

        if (value) {
            this.activeOptions.push(option);
        } else {
            this.activeOptions = this.activeOptions.filter(o => o !== option);
            removedOptions.push(option);
        }

        const eventData = { paramName, value, option, label, activeOptions: this.activeOptions, removedOptions };

        Shopware.emit('MultiSelectFilter:Change', eventData);
        Shopware.emit('Filter:Change', eventData);

        this.updateBadge();
    }

    handleFilterRemove({ paramName, option }) {
        if (paramName === this.paramName && option) {
            this.activeOptions = this.activeOptions.filter(o => o !== option);
            this.optionInputs.forEach(input => {
                if (input.value === option) {
                    input.checked = false;
                }
            });

            this.updateBadge();
        }
    }

    setStateFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        const value = urlParams.get(this.paramName);
        const options = value ? value.split('|') : [];

        this.optionInputs.forEach(input => {
            if (options.includes(input.value)) {
                input.checked = true;
                this.activeOptions.push(input.value);

                Shopware.emitQueued('Filter:Init', {
                    paramName: this.paramName,
                    value: true,
                    option: input.value,
                    label: this.getLabelFromInput(input),
                });
            }
        });

        this.updateBadge();
    }

    updateBadge() {
        this.dispatchEvent('Filter:UpdateBadge', {
            content: this.activeOptions.length > 0 ? `${this.activeOptions.length}` : '',
        });
    }

    getLabelFromInput(input) {
        const displayType = input.getAttribute('data-display-type');
        const labelEl = this.el.querySelector('label[for="' + input.getAttribute('id') + '"]');
        const labelText = labelEl ? labelEl.textContent.trim() : this.paramName;

        if (displayType === 'color') {
            return `<span style="background-color: ${input.getAttribute('data-color')};"></span> ${labelText}`;
        } 

        if (displayType === 'media') {
            return `<span style="background-image: url(${input.getAttribute('data-media')});"></span> ${labelText}`;
        }

        return labelText;
    }

    destroy() {
        this.searchInput.removeEventListener('input', this.handleSearchInput.bind(this));

        this.optionInputs.forEach(input => {
            input.removeEventListener('change', this.handleOptionChange.bind(this));
        });

        Shopware.off(`Filter:Remove:${this.paramName}`, this.handleFilterRemove.bind(this));
    }
}
