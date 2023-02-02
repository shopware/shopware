/* eslint-disable */

const { Directive } = Shopware;

/**
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

const defaultConfig = {
    active: false,
    targetSelector: '',
    resizeWidth: false,
    style: {}
};

const customStylingBlacklist = ['width', 'position', 'top', 'left', 'right', 'bottom'];

Directive.register('popover', {
    inserted(element, binding, vnode) {
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
        setElementPosition(element, vnode.context.$el, config);

        // Resize the width of the element
        if (config.resizeWidth) {
            element.style.width = `${vnode.context.$el.clientWidth}px`;
        }

        // append to target element
        calculateOutsideEdges(element);

        registerVirtualScrollingElement(element, vnode.context, config);
    },

    unbind(element, binding, vnode) {
        // remove element from body
        if (element.parentNode) {
            element.parentNode.removeChild(element);
        }

        unregisterVirtualScrollingElement(vnode.context._uid);
    }
});

/**
 * Helper functions
 *
 * Usage:
 * v-placement
 */

function calculateOutsideEdges(el) {
    // get position
    const boundingClientRect = el.getBoundingClientRect();
    const windowHeight =
        window.innerHeight || document.documentElement.clientHeight;
    const windowWidth = window.innerWidth || document.documentElement.clientWidth;

    // calculate which edges are in viewport
    const visibleEdges = {
        top: boundingClientRect.top > 0,
        right: boundingClientRect.right < windowWidth,
        bottom: boundingClientRect.bottom < windowHeight,
        left: boundingClientRect.left > 0
    };

    // remove all existing placement classes
    el.classList.remove(...Object.values(outsideClasses));

    // get new classes for placement
    const placementClasses = Object.entries(visibleEdges).reduce((acc, edge) => {
        const edgeName = edge[0];
        const isVisible = edge[1];

        // when edge is not visible
        if (isVisible) {
            return acc;
        }

        // add to classes
        acc.push(outsideClasses[edgeName]);

        return acc;
    }, []);

    // add new classes to element
    el.classList.add(...placementClasses);
}

function setElementPosition(element, refElement, config) {
    const originElement = refElement ? refElement : element;
    const elementPosition = originElement.getBoundingClientRect();

    let targetElement = originElement;
    let targetPosition = {
        top: 0,
        left: 0
    };

    if (config.targetSelector && config.targetSelector.length > 0) {
        targetElement = originElement.closest(config.targetSelector);
        targetPosition = targetElement.getBoundingClientRect();
    }

    // set custom inline element styling
    Object.entries(config.style).forEach(([key, value]) => {
        if (customStylingBlacklist.includes(key)) {
            return;
        }

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

function virtualScrollingHandler() {
    if (virtualScrollingElements.size <= 0) {
        stopVirtualScrolling();
        return;
    }

    virtualScrollingElements.forEach((entry) => {
        setElementPosition(entry.el, entry.ref, entry.config);
    });
}

function registerVirtualScrollingElement(modifiedElement, vnodeContext, config) {
    const uid = vnodeContext._uid;

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

function unregisterVirtualScrollingElement(uid) {
    if (!uid) {
        return;
    }

    virtualScrollingElements.delete(uid);

    if (virtualScrollingElements.size <= 0) {
        stopVirtualScrolling();
    }
}

export default {
    virtualScrollingElements,
    registerVirtualScrollingElement,
    unregisterVirtualScrollingElement
};
