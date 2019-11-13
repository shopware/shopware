const { Directive } = Shopware;
const { debug } = Shopware.Utils;
const { hasOwnProperty } = Shopware.Utils.object;
const utils = Shopware.Utils;

const availableTooltipPlacements = [
    'top',
    'right',
    'bottom',
    'left'
];

const tooltipRegistry = new Map();

class Tooltip {
    /**
     * @param {object} obj
     * @param {string} obj.id
     * @param {string} obj.position
     * @param {string} obj.message
     * @param {number} obj.width
     * @param {HTMLElement} obj.element
     * @param {number} obj.showDelay
     * @param {number} obj.hideDelay
     */
    constructor({
        id = utils.createId(),
        position = 'top',
        message,
        width = 200,
        element,
        showDelay = 100,
        hideDelay = showDelay,
        disabled = false,
        appearance = 'dark'
    }) {
        this._id = id;
        this._position = Tooltip.validatePosition(position);
        this._message = Tooltip.validateMessage(message);
        this._width = Tooltip.validateWidth(width);
        this._parentDOMElement = element;
        this._showDelay = showDelay;
        this._hideDelay = hideDelay;
        this._disabled = disabled;
        this._appearance = appearance;
        this._isShown = false;
        this._state = false;

        this.init();
    }

    /**
     * @returns {String}
     */
    get id() {
        return this._id;
    }

    init() {
        this._DOMElement = this.createDOMElement();
        this.registerEvents();
    }

    /**
     * Updates the styles and/or text of the tooltip
     *
     * @param {object} obj
     * @param {string} obj.message
     * @param {string} obj.position
     * @param {number} obj.width
     * @param {number} obj.showDelay
     * @param {number} obj.hideDelay
     */
    update({ message, position, width, showDelay, hideDelay, disabled, appearance }) {
        if (message && this._message !== message) {
            this._message = Tooltip.validateMessage(message);
            this._DOMElement.innerHTML = this._message;
        }

        if (width && this._width !== width) {
            this._width = Tooltip.validateWidth(width);
            this._setTooltipDOMElementPosition();
        }

        if (position && this._position !== position) {
            this._DOMElement.classList.remove(`sw-tooltip--${this._position}`);
            this._position = Tooltip.validatePosition(position);
            this._DOMElement.classList.add(`sw-tooltip--${this._position}`);
            this._setTooltipDOMElementPosition();
        }

        if (showDelay && this._showDelay !== showDelay) {
            this._showDelay = showDelay;
        }

        if (hideDelay && this._hideDelay !== hideDelay) {
            this._hideDelay = hideDelay;
        }

        if (disabled && this._disabled !== disabled) {
            this._disabled = disabled;
        }

        if (appearance && this._appearance !== appearance) {
            this._DOMElement.classList.remove(`sw-tooltip--${this._appearance}`);
            this._appearance = appearance;
            this._DOMElement.classList.add(`sw-tooltip--${this._appearance}`);
        }
    }

    /**
     * @returns {HTMLElement}
     */
    createDOMElement() {
        const element = document.createElement('div');
        element.innerHTML = this._message;
        element.setAttribute('aria-hidden', 'false');
        element.classList.add('sw-tooltip');
        element.classList.add(`sw-tooltip--${this._position}`);
        element.classList.add(`sw-tooltip--${this._appearance}`);

        return element;
    }

    registerEvents() {
        this._parentDOMElement.addEventListener('mouseenter', this.onMouseToggle.bind(this));
        this._parentDOMElement.addEventListener('mouseleave', this.onMouseToggle.bind(this));
        this._DOMElement.addEventListener('mouseenter', this.onMouseToggle.bind(this));
        this._DOMElement.addEventListener('mouseleave', this.onMouseToggle.bind(this));
    }

    /**
     * Sets the state and triggers the toggle.
     *
     * @param {EventListenerObject} event
     */
    onMouseToggle(event) {
        this._state = (event.type === 'mouseenter');

        if (this._timeout) {
            clearTimeout(this._timeout);
        }

        this._timeout = setTimeout(this._toggle.bind(this), (this._state ? this._showDelay : this._hideDelay));
    }

    /**
     * Shows or hides the tooltip.
     */
    _toggle() {
        if (this._disabled) {
            return;
        }

        if (this._state && !this._isShown) {
            document.body.appendChild(this._DOMElement);
            this._setTooltipDOMElementPosition();
            this._isShown = true;
            return;
        }

        if (!this._state && this._isShown) {
            this._DOMElement.remove();
            this._isShown = false;
        }
    }

    _setTooltipDOMElementPosition() {
        const boundingBox = this._parentDOMElement.getBoundingClientRect();
        const secureOffset = 10;

        this._setTooltipDOMElementWidth();

        switch (this._position) {
            case 'bottom':
                this._DOMElement.style.top = `${boundingBox.top + boundingBox.height + secureOffset}px`;
                this._DOMElement.style.left =
                        `${boundingBox.left + (boundingBox.width / 2) - this._DOMElement.offsetWidth / 2}px`;
                break;
            case 'left':
                this._DOMElement.style.top =
                        `${boundingBox.top + boundingBox.height / 2 - this._DOMElement.offsetHeight / 2}px`;
                this._DOMElement.style.left =
                        `${boundingBox.left - secureOffset - this._DOMElement.offsetWidth}px`;
                break;
            case 'right':
                this._DOMElement.style.top =
                        `${boundingBox.top + boundingBox.height / 2 - this._DOMElement.offsetHeight / 2}px`;
                this._DOMElement.style.left =
                        `${boundingBox.right + secureOffset}px`;
                break;
            case 'top':
            default:
                this._DOMElement.style.top =
                        `${boundingBox.top - this._DOMElement.offsetHeight - secureOffset}px`;
                this._DOMElement.style.left =
                        `${boundingBox.left + (boundingBox.width / 2) - this._DOMElement.offsetWidth / 2}px`;
        }
    }

    _setTooltipDOMElementWidth() {
        this._DOMElement.style.width = `${this._width}px`;
    }

    /**
     * @param {string} position
     * @returns {string}
     */
    static validatePosition(position) {
        if (!availableTooltipPlacements.includes(position)) {
            debug.warn(
                'Tooltip Directive',
                `The modifier has to be one of these "${availableTooltipPlacements.join(',')}"`
            );
            return 'top';
        }
        return position;
    }

    /**
     * @param {string} message
     * @returns {string}
     */
    static validateMessage(message) {
        if (!message) {
            debug.warn('Tooltip Directive', 'The tooltip needs a message');
        }
        return message;
    }

    /**
     * @param {number} width
     * @returns {number}
     */
    static validateWidth(width) {
        if (typeof width !== 'number' || width < 1) {
            debug.warn('Tooltip Directive', 'The tooltip width has to be a number greater 0');
            return 200;
        }

        return width;
    }

    /**
     * @param {number} delay
     * @returns {number}
     */
    static validateDelay(delay) {
        if (typeof delay !== 'number' || delay < 1) {
            debug.warn('Tooltip Directive', 'The tooltip delay has to be a number greater 0');
            return 100;
        }

        return delay;
    }
}

/**
 * Directive for tooltips
 *
 * Usage:
 * // tooltip with default width of 200px and default position top:
 * v-tooltip="'Some text'"
 * // tooltip with position bottom by modifier:
 * v-tooltip.bottom="'Some text'"
 * // tooltip with position bottom and width 300px:
 * v-tooltip="{ message: 'Some Text', width: 200, position: 'bottom' }"
 * // Alternative tooltip with position bottom and width 300px:
 * v-tooltip.bottom="{ message: 'Some Text', width: 200 }"
 * // adjusting the delay:
 * v-tooltip.bottom="{ message: 'Some Text', width: 200, showDelay: 200, hideDelay: 300 }"
 *
 * *Note that the position variable has a higher priority as the modifier
 */
Directive.register('tooltip', {
    bind: (el, { value, modifiers }) => {
        let message = hasOwnProperty(value, 'message') ? value.message : value;
        message = message ? message.trim() : '';

        const position = value.position || Object.keys(modifiers)[0];
        const showDelay = value.showDelay;
        const hideDelay = value.hideDelay;
        const disabled = value.disabled;
        const appearance = value.appearance;
        const width = value.width;
        const tooltip = new Tooltip({
            message: message,
            width: width,
            position: position,
            element: el,
            showDelay: showDelay,
            hideDelay: hideDelay,
            disabled: disabled,
            appearance: appearance
        });

        tooltipRegistry.set(tooltip.id, tooltip);
        el.setAttribute('tooltip-id', tooltip.id);
    },

    unbind: (el) => {
        if (el.hasAttribute('tooltip-id')) {
            const tooltip = tooltipRegistry.get(el.getAttribute('tooltip-id'));
            tooltip.onMouseToggle(false);
        }
    },

    update: (el, { value, modifiers }) => {
        let message = hasOwnProperty(value, 'message') ? value.message : value;
        message = message ? message.trim() : '';

        const position = value.position || Object.keys(modifiers)[0];
        const showDelay = value.showDelay;
        const hideDelay = value.hideDelay;
        const disabled = value.disabled;
        const appearance = value.appearance || 'dark';
        const width = value.width;

        if (el.hasAttribute('tooltip-id')) {
            const tooltip = tooltipRegistry.get(el.getAttribute('tooltip-id'));
            tooltip.update({
                message: message,
                position: position,
                width: width,
                showDelay: showDelay,
                hideDelay: hideDelay,
                disabled: disabled,
                appearance: appearance
            });
        }
    }
});
