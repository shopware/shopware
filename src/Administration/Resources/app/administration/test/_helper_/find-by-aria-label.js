/**
 * @sw-package framework
 * @private
 */
function findByAriaLabel(wrapper, selector, text) {
    const elements = wrapper.findAll(selector);
    return elements.find((el) => el.attributes('aria-label')?.trim() === text) || null;
}

export default findByAriaLabel;
