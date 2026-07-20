/*
 * @sw-package framework
 */

import Plugin from 'src/plugin-system/plugin.class';

export default class QuantitySelectorPlugin extends Plugin {

    static options = {
        ariaLiveUpdates: true,
        /**
         * The quantity select is used in different areas.
         * Depending on the use case, a different mode should be used.
         *
         * "live" - Will update the aria live immediately on every change. (default)
         * "onload" - Will update the aria live on first load. Used for auto submit forms.
         */
        ariaLiveUpdateMode: 'live',
        ariaLiveTextValueToken: '%quantity%',
        ariaLiveTextProductToken: '%product%',
        purchaseLimitUrl: null,
    };

    init() {
        this._input = this.el.querySelector('input.js-quantity-selector');
        this._btnPlus = this.el.querySelector('.js-btn-plus');
        this._btnMinus = this.el.querySelector('.js-btn-minus');
        this._unitLabel = this.el.querySelector('.js-quantity-selector-unit');
        this._purchaseLimitFetched = false;

        if (this.options.ariaLiveUpdates) {
            this._initAriaLiveUpdates();
        }

        this._registerEvents();
        this._registerLivePurchaseLimitEvents();
    }

    /**
     * @private
     */
    _initAriaLiveUpdates() {
        this.ariaLiveContainer = this.el.nextElementSibling;

        if (!this.ariaLiveContainer || !this.ariaLiveContainer.hasAttribute('aria-live')) {
            return;
        }

        this.ariaLiveText = this.ariaLiveContainer.dataset.ariaLiveText;
        this.ariaLiveProductName = this.ariaLiveContainer.dataset.ariaLiveProductName;

        if (this.options.ariaLiveUpdateMode === 'onload') {
            const lastQuantityChange = window.localStorage.getItem('lastQuantityChange');

            if (lastQuantityChange && lastQuantityChange === this.ariaLiveProductName) {
                window.localStorage.removeItem('lastQuantityChange');

                // Delay the aria live update so the screen reader has time to read out other updates first.
                // Sometimes the update isn't read out because of other information.
                window.setTimeout(this._updateAriaLive.bind(this), 1000);
            }
        }
    }

    /**
     * register all needed events
     *
     * @private
     */
    _registerEvents() {
        this._btnPlus.addEventListener('click', this._stepUp.bind(this));
        this._btnMinus.addEventListener('click', this._stepDown.bind(this));

        // prevent default submit on
        this._input.addEventListener('keydown', (event) => {
            if (event.keyCode === 13) {
                event.preventDefault();
                this._triggerChange();
                return false;
            }
        });

        this._input.addEventListener('change', this._updateUnitLabel.bind(this));
    }

    /**
     * trigger change event on input element
     *
     * @private
     */
    _triggerChange(btn) {
        const event = new Event('change', { bubbles: true, cancelable: false });
        this._input.dispatchEvent(event);

        if (this.options.ariaLiveUpdateMode === 'live') {
            this._updateAriaLive();
        } else if (this.options.ariaLiveUpdateMode === 'onload') {
            window.localStorage.setItem('lastQuantityChange', this.ariaLiveProductName);
        }

        if (btn === 'up') {
            this._btnPlus.dispatchEvent(event);
        } else if (btn === 'down') {
            this._btnMinus.dispatchEvent(event);
        }
    }

    /**
     * call stepUp on element
     *
     * @private
     */
    _stepUp() {
        const before = this._input.value;
        this._input.stepUp();
        if (this._input.value !== before) {
            this._triggerChange('up');
        }
    }

    /**
     * call stepDown on element
     *
     * @private
     */
    _stepDown() {
        const before = this._input.value;
        this._input.stepDown();
        if (this._input.value !== before) {
            this._triggerChange('down');
        }
    }

    /**
     * Update the aria live element for the screen reader to read out quantity changes.
     *
     * @private
     */
    _updateAriaLive() {
        if (!this.options.ariaLiveUpdates || !this.ariaLiveText || !this.ariaLiveContainer) {
            return;
        }

        const quantityValue = this._input.value;
        let text = this.ariaLiveText.replace(this.options.ariaLiveTextValueToken, quantityValue);

        if (this.options.ariaLiveTextProductToken && this.ariaLiveProductName) {
            text = text.replace(this.options.ariaLiveTextProductToken, this.ariaLiveProductName);
        }

        this.ariaLiveContainer.innerHTML = text;
    }

    /**
     * Update the visible unit label when singular and plural pack units are configured.
     *
     * @private
     */
    _updateUnitLabel() {
        if (!this._unitLabel) {
            return;
        }

        const { unitSingular, unitPlural } = this._unitLabel.dataset;

        if (!unitSingular) {
            return;
        }

        const quantityValue = parseFloat(this._input.value);

        if (Number.isNaN(quantityValue)) {
            return;
        }

        this._unitLabel.textContent = quantityValue > 1 && unitPlural ? unitPlural : unitSingular;
    }

    /**
     * Register one-time interaction listeners that trigger the live purchase limit fetch.
     * The fetch fires once on the first focus or button click, then listeners are removed.
     *
     * @private
     */
    _registerLivePurchaseLimitEvents() {
        const url = this.options.purchaseLimitUrl;

        if (!url) {
            return;
        }

        this._onFirstInteraction = this._fetchLivePurchaseLimit.bind(this, url);

        this._input.addEventListener('focus', this._onFirstInteraction);
        this._btnPlus.addEventListener('click', this._onFirstInteraction, true);
        this._btnMinus.addEventListener('click', this._onFirstInteraction, true);
    }

    /**
     * Remove the one-time interaction listeners for live purchase limit fetching.
     *
     * @private
     */
    _removeLivePurchaseLimitEvents() {
        this._input.removeEventListener('focus', this._onFirstInteraction);
        this._btnPlus.removeEventListener('click', this._onFirstInteraction, true);
        this._btnMinus.removeEventListener('click', this._onFirstInteraction, true);
    }

    /**
     * Fetch live purchase limits from the server and apply them to the input.
     * Fires only once – subsequent calls are no-ops. Falls back silently on failure.
     *
     * @param {string} url
     * @private
     */
    _fetchLivePurchaseLimit(url) {
        if (this._purchaseLimitFetched) {
            return;
        }

        this._purchaseLimitFetched = true;

        this._removeLivePurchaseLimitEvents();

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((response) => {
                if (!response.ok) {
                    return null;
                }

                return response.json();
            })
            .then((data) => {
                if (data) {
                    this._applyPurchaseLimit(data);
                }
            })
            .catch((error) => {
                console.warn('Unable to fetch live quantity limits, keeping rendered values.', error);
            });
    }

    /**
     * Apply fetched purchase limits to the input element.
     * Clamps the current value to the new constraints and dispatches events for the form to handle.
     *
     * @param {{ minPurchase: number, purchaseSteps: number, maxPurchase: number }} limits
     * @private
     */
    _applyPurchaseLimit(limits) {
        if (!this._input) {
            return;
        }

        const max = limits.maxPurchase;

        if (max <= 0) {
            this._disableControls();
            this._dispatchFormEvent('QuantitySelector/OutOfStock');
            return;
        }

        const min = limits.minPurchase;
        const step = limits.purchaseSteps;

        this._input.setAttribute('min', min);
        this._input.setAttribute('max', max);
        this._input.setAttribute('step', step);

        const currentValue = parseInt(this._input.value, 10) || min;
        const clampedValue = Math.min(Math.max(currentValue, min), max);
        const steppedValue = Math.floor((clampedValue - min) / step) * step + min;

        if (steppedValue !== currentValue) {
            this._input.value = steppedValue;
            this._triggerChange();
            this._dispatchFormEvent('QuantitySelector/StockAdjusted', { quantity: steppedValue });
        }
    }

    /**
     * Disable quantity selector controls when the product is no longer purchasable.
     *
     * @private
     */
    _disableControls() {
        this._input.disabled = true;
        this._btnPlus.disabled = true;
        this._btnMinus.disabled = true;
    }

    /**
     * Dispatch a CustomEvent on the parent form so form-level plugins can react.
     *
     * @param {string} eventName
     * @param {Object} detail
     * @private
     */
    _dispatchFormEvent(eventName, detail = {}) {
        this.el.closest('form')?.dispatchEvent(new CustomEvent(eventName, { detail }));
    }
}
