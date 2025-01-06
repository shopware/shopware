/*
 * @package storefront
 */

import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';

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
    };

    init() {
        this._input = DomAccess.querySelector(this.el, 'input.js-quantity-selector');
        this._btnPlus = DomAccess.querySelector(this.el, '.js-btn-plus');
        this._btnMinus = DomAccess.querySelector(this.el, '.js-btn-minus');

        if (this.options.ariaLiveUpdates) {
            this._initAriaLiveUpdates();
        }

        this._registerEvents();
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
            // Delay the aria live update so the screen reader has time to read out other updates first.
            // Sometimes the update isn't read out because of other information.
            window.setTimeout(this._updateAriaLive.bind(this), 1000);
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
}
