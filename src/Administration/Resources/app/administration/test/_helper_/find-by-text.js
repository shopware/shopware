/**
 * @sw-package framework
 * @private
 */
function findByText(wrapper, selector, text) {
    const elements = wrapper.findAll(selector);
    return elements.find((el) => el.text().trim() === text) || null;
}

export default findByText;
