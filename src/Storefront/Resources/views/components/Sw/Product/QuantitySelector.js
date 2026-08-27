export default class ProductQuantitySelector extends ShopwareComponent {
    static options = { purchaseLimitUrl: null };

    init() {
        this.input = this.el.querySelector('.sw-product-quantity-selector__input');
        this.decreaseButton = this.el.querySelector('.sw-product-quantity-selector__button--decrease');
        this.increaseButton = this.el.querySelector('.sw-product-quantity-selector__button--increase');
        this.unit = this.el.querySelector('.sw-product-quantity-selector__unit');
        this.liveRegion = this.el.querySelector('.sw-product-quantity-selector__live');
        this.purchaseLimitFetched = false;

        this.onDecrease = this.handleDecrease.bind(this);
        this.decreaseButton?.addEventListener('click', this.onDecrease);

        this.onIncrease = this.handleIncrease.bind(this);
        this.increaseButton?.addEventListener('click', this.onIncrease);

        this.onChange = this.handleChange.bind(this);
        this.input?.addEventListener('change', this.onChange);

        this.onFirstInteraction = this.fetchPurchaseLimit.bind(this);
        if (this.options.purchaseLimitUrl) {
            this.input?.addEventListener('focus', this.onFirstInteraction);
            this.decreaseButton?.addEventListener('click', this.onFirstInteraction, true);
            this.increaseButton?.addEventListener('click', this.onFirstInteraction, true);
        }
    }

    destroy() {
        this.decreaseButton?.removeEventListener('click', this.onDecrease);
        this.increaseButton?.removeEventListener('click', this.onIncrease);
        this.input?.removeEventListener('change', this.onChange);

        this.input?.removeEventListener('focus', this.onFirstInteraction);
        this.decreaseButton?.removeEventListener('click', this.onFirstInteraction, true);
        this.increaseButton?.removeEventListener('click', this.onFirstInteraction, true);
    }

    step(method) {
        if (!this.input || this.input.disabled) return;

        const previous = this.input.value;

        try {
            this.input[method]();
        } catch {
            return;
        }

        if (this.input.value !== previous) {
            this.input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    handleDecrease() {
        this.step('stepDown');
    }

    handleIncrease() {
        this.step('stepUp');
    }

    handleChange() {
        this.updateUnit();
        this.updateLiveRegion();
    }

    updateUnit() {
        if (!this.unit || !this.input) return;

        const quantity = Number.parseFloat(this.input.value);

        if (!Number.isNaN(quantity) && this.unit.dataset.unitSingular) {
            this.unit.textContent =
                quantity > 1 && this.unit.dataset.unitPlural
                    ? this.unit.dataset.unitPlural
                    : this.unit.dataset.unitSingular;
        }
    }

    updateLiveRegion() {
        if (
            !this.liveRegion ||
            !this.input ||
            !this.liveRegion.dataset.ariaLiveText
        ) {
            return;
        }

        this.liveRegion.textContent = this.liveRegion.dataset.ariaLiveText
            .replace('%quantity%', this.input.value)
            .replace('%product%', this.liveRegion.dataset.ariaLiveProductName || '');
    }

    async fetchPurchaseLimit() {
        if (this.purchaseLimitFetched) {
            return;
        }

        this.purchaseLimitFetched = true;
        this.input?.removeEventListener('focus', this.onFirstInteraction);
        this.decreaseButton?.removeEventListener('click', this.onFirstInteraction, true);
        this.increaseButton?.removeEventListener('click', this.onFirstInteraction, true);

        try {
            const response = await fetch(this.options.purchaseLimitUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (response.ok) {
                this.applyPurchaseLimit(await response.json());
            }
        } catch {
            // Keep the server-rendered limits when the request fails.
        }
    }

    applyPurchaseLimit({ minPurchase, purchaseSteps, maxPurchase }) {
        if (!this.input) {
            return;
        }

        if (maxPurchase <= 0) {
            [this.input, this.decreaseButton, this.increaseButton].forEach((control) => {
                if (control) {
                    control.disabled = true;
                }
            });
            this.el.closest('form')?.dispatchEvent(new CustomEvent('QuantitySelector/OutOfStock'));
            return;
        }

        this.input.min = minPurchase;
        this.input.max = maxPurchase;
        this.input.step = purchaseSteps;

        const current = Number.parseFloat(this.input.value) || minPurchase;
        const bounded = Math.min(Math.max(current, minPurchase), maxPurchase);
        const stepped = Math.floor((bounded - minPurchase) / purchaseSteps) * purchaseSteps + minPurchase;

        if (stepped !== current) {
            this.input.value = stepped;
            this.input.dispatchEvent(new Event('change', { bubbles: true }));
            this.el
                .closest('form')
                ?.dispatchEvent(
                    new CustomEvent('QuantitySelector/StockAdjusted', {
                        detail: { quantity: stepped },
                    }),
                );
        }
    }
}
