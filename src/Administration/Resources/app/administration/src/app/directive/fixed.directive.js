const { Directive } = Shopware;

let rootParent = null;

function updateElement(el) {
    // append to rootParent
    rootParent.appendChild(el);

    // remove inline styling
    el.style.removeProperty('position');
    el.style.removeProperty('zIndex');
    el.style.removeProperty('top');
    el.style.removeProperty('left');
    el.style.removeProperty('width');
    el.style.removeProperty('height');

    // get element position
    const elPosition = el.getBoundingClientRect();

    // add inline styling
    el.style.position = 'fixed';
    el.style.zIndex = '1500';
    el.style.top = `${elPosition.top}px`;
    el.style.left = `${elPosition.left}px`;
    el.style.width = `${elPosition.width}px`;
    el.style.height = `${elPosition.height}px`;

    // append to body
    document.body.appendChild(el);
}

/**
 * @deprecated tag:v6.4.0
 * Directive for fixed
 *
 * Usage:
 * v-fixed
 */

Directive.register('fixed', {

    bind() {},
    inserted(el) {
        // save parentNode of element
        rootParent = el.parentNode;

        // update element
        updateElement(el);
    },
    update() {},
    componentUpdated(el) {
        // update element
        updateElement(el);
    },
    unbind(el) {
        // remove element from body
        if (el.parentNode) {
            el.parentNode.removeChild(el);
        }
    }

});
