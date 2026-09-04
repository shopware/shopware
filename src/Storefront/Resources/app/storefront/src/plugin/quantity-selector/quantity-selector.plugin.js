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

        /**
         * Submit the surrounding form as soon as the user finishes an edit, by leaving the
         * input or confirming the value with `Enter`.
         *
         * Used where the form applies the quantity on its own, like the cart. Both are
         * deliberate "apply now" actions, so they submit directly instead of going through
         * the delay those forms use to bundle repeated clicks on the `[+]` and `[-]` buttons.
         */
        submitOnFinish: false,
    };

    init() {
        this._input = this.el.querySelector('input.js-quantity-selector');
        this._btnPlus = this.el.querySelector('.js-btn-plus');
        this._btnMinus = this.el.querySelector('.js-btn-minus');
        this._unitLabel = this.el.querySelector('.js-quantity-selector-unit');
        this._purchaseLimitFetched = false;
        this._committedValue = this._input.value;
        this._isCommitting = false;

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

        this._input.addEventListener('keydown', this._onKeyDown.bind(this));
        this._input.addEventListener('change', this._onChange.bind(this));
        this._input.addEventListener('blur', this._onBlur.bind(this));
    }

    /**
     * Withhold `change` events that are emitted while the user is still editing.
     *
     * The native stepping of `input[type=number]` emits a `change` event on every single
     * arrow key press. Form level listeners such as the `FormAutoSubmitPlugin` treat each of
     * those as a completed edit and submit, which reloads the page underneath a keyboard or
     * screen reader user while they are still choosing a value. Those events are held back
     * until the edit is finished, which is either on blur or on `Enter`.
     *
     * The input only keeps the focus for edits made with the keyboard. Typing a value emits
     * `change` on blur, when the focus has already moved on, and the `[+]` and `[-]` buttons
     * trigger the event themselves. Both still pass through immediately.
     *
     * @param {Event} event
     *
     * @private
     */
    _onChange(event) {
        if (this._isCommitting) {
            this._committedValue = this._input.value;
        } else {
            event.stopPropagation();
        }

        this._updateUnitLabel();
    }

    /**
     * Apply the current value on `Enter`, so a keyboard user does not have to leave the
     * input for it to take effect.
     *
     * @param {KeyboardEvent} event
     *
     * @private
     */
    _onKeyDown(event) {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        this._applyEdit();
    }

    /**
     * @private
     */
    _onBlur(event) {
        // Moving on to the `[+]` or `[-]` buttons is not leaving the control. Their own
        // commit applies the value, including the step the user is about to make.
        if (this.el.contains(event.relatedTarget)) {
            return;
        }

        this._applyEdit();
    }

    /**
     * Apply a value the user is done editing.
     *
     * Leaving the input and confirming with `Enter` are both deliberate, so the form is
     * submitted right away where it applies the quantity itself. Everywhere else the value
     * is passed on as a `change` and whatever listens decides what to do with it.
     *
     * @private
     */
    _applyEdit() {
        if (!this.options.submitOnFinish) {
            this._commit();
            return;
        }

        if (this._input.value === this._committedValue) {
            return;
        }

        this._committedValue = this._input.value;
        this._announceChange();
        this._input.form?.requestSubmit();
    }

    /**
     * Pass on a value the user is done editing, even when the input still holds the focus.
     *
     * @param {'up'|'down'|undefined} btn
     *
     * @private
     */
    _commit(btn) {
        if (this._input.value === this._committedValue) {
            return;
        }

        this._isCommitting = true;
        this._triggerChange(btn);
        this._isCommitting = false;
    }

    /**
     * trigger change event on input element
     *
     * @private
     */
    _triggerChange(btn) {
        const event = new Event('change', { bubbles: true, cancelable: false });
        this._input.dispatchEvent(event);

        this._announceChange();

        if (btn === 'up') {
            this._btnPlus.dispatchEvent(event);
        } else if (btn === 'down') {
            this._btnMinus.dispatchEvent(event);
        }
    }

    /**
     * Announce the new quantity, either right away or after the page the form submits to has
     * loaded.
     *
     * @private
     */
    _announceChange() {
        if (this.options.ariaLiveUpdateMode === 'live') {
            this._updateAriaLive();
        } else if (this.options.ariaLiveUpdateMode === 'onload') {
            window.localStorage.setItem('lastQuantityChange', this.ariaLiveProductName);
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
            this._commit('up');
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
            this._commit('down');
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
            this._commit();
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
