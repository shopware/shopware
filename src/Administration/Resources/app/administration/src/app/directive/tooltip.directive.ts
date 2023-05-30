/**
 * @package admin
 */

/* eslint-disable @typescript-eslint/no-non-null-assertion */

import type { VNode } from 'vue';
import Vue from 'vue';

const { Directive } = Shopware;
const { debug } = Shopware.Utils;
const utils = Shopware.Utils;

type Placements = 'top'|'right'|'bottom'|'left';

const availableTooltipPlacements: Placements[] = [
    'top',
    'right',
    'bottom',
    'left',
];

// eslint-disable-next-line no-use-before-define
const tooltipRegistry = new Map<string, Tooltip>();

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
class Tooltip {
    private _id?: string;

    private _placement?: Placements;

    private _message: string;

    private _width: number|string;

    private _parentDOMElement: HTMLElement;

    private _showDelay: number;

    private _hideDelay: number;

    private _disabled: boolean;

    private _appearance: string;

    private _showOnDisabledElements: boolean;

    private _zIndex: number|null;

    private _isShown: boolean;

    private _state: boolean;

    private _DOMElement: HTMLElement|null;

    private _vue: Vue|null;

    private _parentDOMElementWrapper: HTMLElement|null;

    private _actualTooltipPlacement: Placements|null;

    private _timeout?: ReturnType<typeof setTimeout>;


    constructor({
        id = utils.createId(),
        placement = 'top',
        message,
        width = 200,
        element,
        showDelay = 100,
        hideDelay = showDelay,
        disabled = false,
        appearance = 'dark',
        showOnDisabledElements = false,
        zIndex = null,
    }: {
        id?: string,
        placement?: Placements,
        message?: string,
        width?: number | string,
        element: HTMLElement,
        showDelay?: number,
        hideDelay?: number,
        disabled: boolean,
        appearance?: string,
        showOnDisabledElements?: boolean,
        zIndex?: number|null,
    }) {
        this._id = id;
        this._placement = Tooltip.validatePlacement(placement);
        this._message = Tooltip.validateMessage(message);
        this._width = Tooltip.validateWidth(width);
        this._parentDOMElement = element;
        this._showDelay = showDelay ?? 100;
        this._hideDelay = hideDelay ?? 100;
        this._disabled = disabled;
        this._appearance = appearance;
        this._showOnDisabledElements = showOnDisabledElements;
        this._zIndex = zIndex;

        // initialize tooltip variables
        this._isShown = false;
        this._state = false;
        this._DOMElement = null;
        this._vue = null;
        this._parentDOMElementWrapper = null;
        this._actualTooltipPlacement = null;
    }

    /**
     * @returns {String}
     */
    get id() {
        return this._id;
    }

    /**
     * Initializes the tooltip.
     * Needs to be called after the parent DOM Element is inserted to the DOM.
     */
    init(node: VNode) {
        this._DOMElement = this.createDOMElement(node);

        if (this._showOnDisabledElements) {
            this._parentDOMElementWrapper = this.createParentDOMElementWrapper();
        }

        this.registerEvents();
    }

    /**
     * Updates the styles and/or text of the tooltip
     */
    update({
        message,
        placement,
        width,
        showDelay,
        hideDelay,
        disabled,
        appearance,
        showOnDisabledElements,
        zIndex,
    }: {
        message?: string,
        placement?: Placements,
        width?: number | string,
        showDelay?: number,
        hideDelay?: number,
        disabled?: boolean,
        appearance?: string,
        showOnDisabledElements?: boolean,
        zIndex?: number|null,
    }) {
        if (message && this._message !== message) {
            this._message = Tooltip.validateMessage(message);

            if (this._DOMElement) {
                this._DOMElement.innerHTML = this._message;
            }

            this._vue?.$destroy();
            this._vue = new Vue({
                el: this._DOMElement!,
                parent: this._vue?.$parent,
                template: this._DOMElement?.outerHTML,
            });

            this._DOMElement = this._vue.$el as HTMLElement;
            this.registerEvents();
        }

        if (width && this._width !== width) {
            this._width = Tooltip.validateWidth(width);
            this._DOMElement!.style.width = `${this._width}px`;
        }

        if (placement && this._placement !== placement) {
            this._placement = Tooltip.validatePlacement(placement);
            this._placeTooltip();
        }

        if (showDelay && this._showDelay !== showDelay) {
            this._showDelay = showDelay;
        }

        if (hideDelay && this._hideDelay !== hideDelay) {
            this._hideDelay = hideDelay;
        }

        if (disabled !== undefined && this._disabled !== disabled) {
            this._disabled = disabled;
        }

        if (appearance && this._appearance !== appearance) {
            this._DOMElement!.classList.remove(`sw-tooltip--${this._appearance}`);
            this._appearance = appearance;
            this._DOMElement!.classList.add(`sw-tooltip--${this._appearance}`);
        }

        if (showOnDisabledElements !== undefined && this._showOnDisabledElements !== showOnDisabledElements) {
            this._showOnDisabledElements = showOnDisabledElements;
        }

        if (zIndex !== this._zIndex && zIndex !== undefined) {
            this._zIndex = zIndex;
        }
    }

    /**
     * Creates a wrapper around the original DOMElement.
     * This is needed because a disabled input field does not fire any mouse events and prevents the tooltip
     * therefore from working.
     * @returns {HTMLElement}
     */
    createParentDOMElementWrapper() {
        const element = document.createElement('div');
        element.classList.add('sw-tooltip--wrapper');

        this._parentDOMElement.parentNode!.insertBefore(element, this._parentDOMElement);
        element.appendChild(this._parentDOMElement);

        return element;
    }

    createDOMElement(node: VNode): HTMLElement {
        const element = document.createElement('div');
        element.innerHTML = this._message;
        element.style.width = `${this._width}px`;
        element.setAttribute('aria-hidden', 'false');
        element.classList.add('sw-tooltip');
        element.classList.add(`sw-tooltip--${this._appearance}`);

        if (this._zIndex !== null) {
            element.style.zIndex = this._zIndex.toFixed(0);
        }

        this._vue = new Vue({
            el: element,
            parent: node.context,
            template: element.outerHTML,
        });

        return this._vue.$el as HTMLElement;
    }

    registerEvents() {
        if (this._parentDOMElementWrapper) {
            this._parentDOMElementWrapper.addEventListener('mouseenter', this.onMouseToggle.bind(this));
            this._parentDOMElementWrapper.addEventListener('mouseleave', this.onMouseToggle.bind(this));
        } else {
            this._parentDOMElement.addEventListener('mouseenter', this.onMouseToggle.bind(this));
            this._parentDOMElement.addEventListener('mouseleave', this.onMouseToggle.bind(this));
        }
        this._DOMElement!.addEventListener('mouseenter', this.onMouseToggle.bind(this));
        this._DOMElement!.addEventListener('mouseleave', this.onMouseToggle.bind(this));
    }

    /**
     * Sets the state and triggers the toggle.
     */
    onMouseToggle(event: MouseEvent) {
        this._state = (event.type === 'mouseenter');

        if (this._timeout) {
            clearTimeout(this._timeout);
        }

        this._timeout = setTimeout(this._toggle.bind(this), (this._state ? this._showDelay : this._hideDelay));
    }

    _toggle() {
        if (this._state && !this._isShown && this._doesParentExist()) {
            this.showTooltip();
            return;
        }

        if (!this._state && this._isShown) {
            this.hideTooltip();
        }
    }

    /**
     * Gets the parent element by tag name and tooltip id and returns true or false whether the element exists.
     * @returns {boolean}
     * @private
     */
    _doesParentExist() {
        const tooltipIdOfParentElement = this._parentDOMElement.getAttribute('tooltip-id') ?? '';
        const htmlTagOfParentElement = this._parentDOMElement.tagName.toLowerCase();

        return !!document.querySelector(`${htmlTagOfParentElement}[tooltip-id="${tooltipIdOfParentElement}"]`);
    }

    /**
     * Appends the tooltip to the DOM and sets a suitable position
     */
    showTooltip() {
        if (this._disabled) {
            return;
        }
        document.body.appendChild(this._DOMElement!);

        this._placeTooltip();
        this._isShown = true;
    }

    /**
     * Removes the tooltip from the DOM
     */
    hideTooltip() {
        if (this._disabled) {
            return;
        }
        this._DOMElement!.remove();
        this._vue!.$destroy();
        this._isShown = false;
    }

    _placeTooltip() {
        let possiblePlacements = availableTooltipPlacements;
        let placement = this._placement;
        possiblePlacements = possiblePlacements.filter((pos) => pos !== placement);

        // Remove previous placement class if it exists
        this._DOMElement!.classList.remove(`sw-tooltip--${this._actualTooltipPlacement!}`);

        // Set the tooltip to the desired place
        this._setDOMElementPosition(this._calculateTooltipPosition(placement ?? 'top'));
        this._actualTooltipPlacement = placement ?? null;

        // Check if the tooltip is fully visible in viewport and change position if not
        while (!this._isElementInViewport(this._DOMElement!)) {
            // The tooltip wont fit in any position
            if (possiblePlacements.length < 1) {
                this._actualTooltipPlacement = this._placement ?? null;
                this._setDOMElementPosition(this._calculateTooltipPosition(this._placement ?? 'top'));
                break;
            }
            // try the next position in the possiblePositions array
            placement = possiblePlacements.shift();
            this._setDOMElementPosition(this._calculateTooltipPosition(placement ?? 'top'));
            this._actualTooltipPlacement = placement ?? null;
        }

        this._DOMElement!.classList.add(`sw-tooltip--${this._actualTooltipPlacement ?? ''}`);
    }

    _setDOMElementPosition({ top, left }: { top: string, left: string }) {
        this._DOMElement!.style.top = top;
        this._DOMElement!.style.left = left;
    }

    _calculateTooltipPosition(placement: Placements) {
        const boundingBox = this._parentDOMElement.getBoundingClientRect();
        const secureOffset = 10;

        let top;
        let left;

        switch (placement) {
            case 'bottom':
                top = `${boundingBox.top + boundingBox.height + secureOffset}px`;
                left = `${boundingBox.left + (boundingBox.width / 2) - this._DOMElement!.offsetWidth / 2}px`;
                break;
            case 'left':
                top = `${boundingBox.top + boundingBox.height / 2 - this._DOMElement!.offsetHeight / 2}px`;
                left = `${boundingBox.left - secureOffset - this._DOMElement!.offsetWidth}px`;
                break;
            case 'right':
                top = `${boundingBox.top + boundingBox.height / 2 - this._DOMElement!.offsetHeight / 2}px`;
                left = `${boundingBox.right + secureOffset}px`;
                break;
            case 'top':
            default:
                top = `${boundingBox.top - this._DOMElement!.offsetHeight - secureOffset}px`;
                left = `${boundingBox.left + (boundingBox.width / 2) - this._DOMElement!.offsetWidth / 2}px`;
        }
        return { top: top, left: left };
    }

    _isElementInViewport(element: HTMLElement) {
        // get position
        const boundingClientRect = element.getBoundingClientRect();
        const windowHeight =
            window.innerHeight || document.documentElement.clientHeight;
        const windowWidth = window.innerWidth || document.documentElement.clientWidth;

        // calculate which borders are in viewport
        const visibleBorders = {
            top: boundingClientRect.top > 0,
            right: boundingClientRect.right < windowWidth,
            bottom: boundingClientRect.bottom < windowHeight,
            left: boundingClientRect.left > 0,
        };

        return visibleBorders.top && visibleBorders.right && visibleBorders.bottom && visibleBorders.left;
    }

    static validatePlacement<P extends Placements>(placement: P): Placements {
        if (!availableTooltipPlacements.includes(placement)) {
            debug.warn(
                'Tooltip Directive',
                `The modifier has to be one of these "${availableTooltipPlacements.join(',')}"`,
            );

            return 'top';
        }
        return placement;
    }

    static validateMessage(message?: string): string {
        if (typeof message !== 'string') {
            debug.warn('Tooltip Directive', 'The tooltip needs a message with type string');
        }

        return message ?? '';
    }

    static validateWidth(width: number|string): number|string {
        if (width === 'auto') {
            return width;
        }

        if (typeof width !== 'number' || width < 1) {
            debug.warn('Tooltip Directive', 'The tooltip width has to be a number greater 0');
            return 200;
        }

        return width;
    }

    static validateDelay(delay: number): number {
        if (typeof delay !== 'number' || delay < 1) {
            debug.warn('Tooltip Directive', 'The tooltip delay has to be a number greater 0');
            return 100;
        }

        return delay;
    }
}

/**
 * Helper function for creating or updating a tooltip instance
 */
function createOrUpdateTooltip(el: HTMLElement, { value, modifiers }: {
    value: {
        message: string,
        position: Placements,
        showDelay: number,
        hideDelay: number,
        disabled: boolean,
        appearance: string,
        width: number|string,
        showOnDisabledElements: boolean,
        zIndex: number,
    },
    modifiers: {
        [key: string]: unknown,
    }
}) {
    let message: string = typeof value === 'string' ? value : value.message;
    message = message ? message.trim() : '';

    const placement = value.position || Object.keys(modifiers)[0];
    const showDelay = value.showDelay;
    const hideDelay = value.hideDelay;
    const disabled = value.disabled;
    const appearance = value.appearance;
    const width = value.width;
    const showOnDisabledElements = value.showOnDisabledElements;
    const zIndex = value.zIndex;

    const configuration = {
        element: el,
        message: message,
        placement: placement,
        width: width,
        showDelay: showDelay,
        hideDelay: hideDelay,
        disabled: disabled,
        appearance: appearance,
        showOnDisabledElements: showOnDisabledElements,
        zIndex: zIndex,
    };

    if (el.hasAttribute('tooltip-id')) {
        const tooltip = tooltipRegistry.get(el.getAttribute('tooltip-id')!);
        tooltip!.update(configuration);

        return;
    }

    const tooltip = new Tooltip(configuration);

    tooltipRegistry.set(tooltip.id ?? '', tooltip);
    el.setAttribute('tooltip-id', tooltip.id!);
}

/**
 * Directive for tooltips
 * Usage:
 * v-tooltip="{configuration}"
 * // configuration options:
 *  message: The text to be displayed.
 *  position: Position of the tooltip relative to the original element(top, bottom etc.).
 *  width: The width of the tooltip.
 *  showDelay: The delay before the tooltip is shown when the original element is hovered.
 *  hideDelay: The delay before the tooltip is removed when the original element is not hovered.
 *  disabled: Disables the tooltip and it wont be shown.
 *  appearance: Sets a additional css class "sw-tooltip--$appearance" for styling
 *  showOnDisabledElements: Shows the tooltip also if the original element is disabled. To achieve
 *      this a wrapper div element is created around the original element because the original element
 *      prevents mouse events when disabled.
 *
 * Examples:
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
    bind: (el, binding) => {
        // @ts-expect-error - tooltip binding has some other required properties
        createOrUpdateTooltip(el, binding);
    },

    unbind: (el) => {
        if (el.hasAttribute('tooltip-id')) {
            const tooltip = tooltipRegistry.get(el.getAttribute('tooltip-id')!);
            tooltip!.hideTooltip();
        }
    },

    update: (el, binding) => {
        // @ts-expect-error - tooltip binding has some other required properties
        createOrUpdateTooltip(el, binding);
    },

    /**
     * Initialize the tooltip once it has been inserted to the DOM.
     * @param el
     * @param binding
     * @param node
     */
    inserted: (el, binding, node) => {
        if (el.hasAttribute('tooltip-id')) {
            const tooltip = tooltipRegistry.get(el.getAttribute('tooltip-id')!);
            tooltip!.init(node);
        }
    },
});
