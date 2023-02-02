/*
 * @package storefront
 */

import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';

export default class QuantitySelectorPlugin extends Plugin {
    _init() {
        this._input = DomAccess.querySelector(this.el, 'input.js-quantity-selector');
        this._btnPlus = DomAccess.querySelector(this.el, '.js-btn-plus');
        this._btnMinus = DomAccess.querySelector(this.el, '.js-btn-minus');
        this._registerEvents();
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
        })
    }

    /**
     * trigger change event on input element
     *
     * @private
     */
    _triggerChange() {
        const event = document.createEvent('HTMLEvents');
        event.initEvent('change', true, false);
        this._input.dispatchEvent(event);
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
            this._triggerChange();
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
            this._triggerChange();
        }
    }
}
