/* eslint-disable */

import { VNode } from "vue";
import { ComponentPublicInstance } from "vue";

const { Directive } = Shopware;

/**
 * @package admin
 *
 * Directive for automatic edge detection of the element place
 *
 * Usage:
 * v-popover="{ active: true, targetSelector: '.my-element', resizeWidth: true }"
 */

// add virtual scrolling
const virtualScrollingElements = new Map();

// set class name for each border
const outsideClasses = {
    top: '--placement-top-outside',
    right: '--placement-right-outside',
    bottom: '--placement-bottom-outside',
    left: '--placement-left-outside'
};

interface PopoverConfig {
    active: boolean;
    targetSelector: string;
    resizeWidth: boolean;
    style: Record<string, string>;
    stopScrollPropagation?: boolean;
}

const defaultConfig: PopoverConfig = {
    active: false,
    targetSelector: '',
    resizeWidth: false,
    style: {},
    stopScrollPropagation: false
};

const customStylingBlacklist = ['width', 'position', 'top', 'left', 'right', 'bottom'];

Directive.register('popover', {
    mounted(element, binding) {
        // We need a configuration
        if (!binding.value) {
            return false;
        }

        // Merge user config with default config
        const config = { ...defaultConfig, ...binding.value };
        if (!config.active) {
            return false;
        }

        // Configurable target element
        let targetElement = document.body;
        if (config.targetSelector && config.targetSelector.length > 0) {
            targetElement = element.closest(config.targetSelector);
        }

        targetElement.appendChild(element);
        setElementPosition(element, binding.instance?.$el, config);

        // Resize the width of the element
        if (config.resizeWidth) {
            element.style.width = `${binding.instance?.$el.clientWidth}px`;
        }

        // @ts-expect-error
        // append to target element
        calculateOutsideEdges(element, binding.instance!);

        // @ts-expect-error
        registerVirtualScrollingElement(element, binding.instance!, config);
    },

    unmounted(element, binding, vnode) {
        // remove element from body
        if (element.parentNode) {
            element.parentNode.removeChild(element);
        }

        // @ts-expect-error - _uid exists on the context but is private
        unregisterVirtualScrollingElement(vnode.context?._uid);
    }
});

/**
 * Helper functions
 *
 * Usage:
 * v-placement
 */

function calculateOutsideEdges(el: HTMLElement, vnode: VNode | ComponentPublicInstance) {
    // @ts-expect-error - parent exists on the vnode but is private
    // orientation element is needed for calculating the available space
    const orientationElement = vnode.$parent?.$el;

    // get position
    const boundingClientRect = orientationElement.getBoundingClientRect();
    const windowHeight =
        window.innerHeight || document.documentElement.clientHeight;
    const windowWidth = window.innerWidth || document.documentElement.clientWidth;

    // calculate which edges are in viewport
    const visibleEdges = {
        topSpace: boundingClientRect.top,
        rightSpace: windowWidth - boundingClientRect.right,
        bottomSpace: windowHeight - boundingClientRect.bottom,
        leftSpace: boundingClientRect.left
    };

    // remove all existing placement classes
    el.classList.remove(...Object.values(outsideClasses));

    // get new classes for placement
    const placementClasses = [
        visibleEdges.bottomSpace < visibleEdges.topSpace ? outsideClasses.bottom : outsideClasses.top,
        visibleEdges.rightSpace > visibleEdges.leftSpace ? outsideClasses.left : outsideClasses.right
    ]
    // add new classes to element
    el.classList.add(...placementClasses);
}

function setElementPosition(element: HTMLElement, refElement: Element | undefined, config: PopoverConfig) {
    const originElement = refElement ? refElement : element;
    const elementPosition = originElement.getBoundingClientRect();

    let targetElement = originElement;
    let targetPosition = {
        top: 0,
        left: 0
    };

    if (config.targetSelector && config.targetSelector.length > 0) {
        targetElement = originElement.closest(config.targetSelector)!;
        targetPosition = targetElement.getBoundingClientRect();
    }

    // set custom inline element styling
    Object.entries(config.style).forEach(([key, value]) => {
        if (customStylingBlacklist.includes(key)) {
            return;
        }

        // @ts-expect-error - key can be set
        element.style[key] = value;
    });

    // add inline styling
    element.style.position = 'absolute';
    element.style.top = `${(elementPosition.top - targetPosition.top) + originElement.clientHeight}px`;
    element.style.left = `${elementPosition.left - targetPosition.left}px`;
}

/*
* Virtual Scrolling
*/

function startVirtualScrolling() {
    window.addEventListener('scroll', virtualScrollingHandler, true);
}

function stopVirtualScrolling() {
    window.removeEventListener('scroll', virtualScrollingHandler, true);
}

function virtualScrollingHandler(event: Event) {
    if (virtualScrollingElements.size <= 0) {
        stopVirtualScrolling();
        return;
    }

    virtualScrollingElements.forEach((entry) => {
        setElementPosition(entry.el, entry.ref, entry.config);
        if (entry.config.stopScrollPropagation) {
            event.stopPropagation();
        }
    });
}

function registerVirtualScrollingElement(modifiedElement: HTMLElement, vnodeContext: ComponentPublicInstance | undefined, config: PopoverConfig) {
    // @ts-expect-error - _uid exists on the context but is private
    const uid = vnodeContext?._uid;

    if (!uid) {
        return;
    }

    if (virtualScrollingElements.size <= 0) {
        startVirtualScrolling();
    }

    virtualScrollingElements.set(uid, {
        el: modifiedElement,
        ref: vnodeContext.$el,
        config
    });
}

function unregisterVirtualScrollingElement(uid?: string) {
    if (!uid) {
        return;
    }

    virtualScrollingElements.delete(uid);

    if (virtualScrollingElements.size <= 0) {
        stopVirtualScrolling();
    }
}

/**
 * @private
 */
export default {
    virtualScrollingElements,
    registerVirtualScrollingElement,
    unregisterVirtualScrollingElement
};
