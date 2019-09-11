const { Directive } = Shopware;

/**
 * Directive for automatic edge detection of the element place
 *
 * Usage:
 * v-placement
 */

// add virtual scrolling
const virtualScrollingElements = new Map();

Directive.register('popover', {
    inserted(element, binding, vnode) {
        calculateOutsideEdges(element);
        setElementPosition(element, vnode.context.$el);

        // append to body
        document.body.appendChild(element);

        registerVirtualScrollingElement(element, vnode.context);
    },

    unbind(element, binding, vnode) {
        // remove element from body
        if (element.parentNode) {
            element.parentNode.removeChild(element);
        }

        unregisterVirtualScrollingElement(vnode.context);
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

    // set class name for each border
    const outsideClasses = {
        top: '--placement-top-outside',
        right: '--placement-right-outside',
        bottom: '--placement-bottom-outside',
        left: '--placement-left-outside'
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

function setElementPosition(element, refElement) {
    const elementPosition = refElement ? refElement.getBoundingClientRect() : element.getBoundingClientRect();

    // add inline styling
    element.style.position = 'absolute';
    element.style.top = `${elementPosition.top}px`;
    element.style.left = `${elementPosition.left}px`;
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
        setElementPosition(entry.el, entry.ref);
    });
}

function registerVirtualScrollingElement(modifiedElement, vnodeContext) {
    const uid = vnodeContext._uid;

    if (!uid) {
        return;
    }

    if (virtualScrollingElements.size <= 0) {
        startVirtualScrolling();
    }

    virtualScrollingElements.set(uid, {
        el: modifiedElement,
        ref: vnodeContext.$el
    });
}

function unregisterVirtualScrollingElement(vnodeContext) {
    const uid = vnodeContext._uid;

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
