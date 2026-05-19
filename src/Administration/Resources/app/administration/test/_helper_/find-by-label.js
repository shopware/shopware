/**
 * @sw-package framework
 * @private
 */
function findByLabel(wrapper, labelText) {
    const label = wrapper.findAll('label').find((l) => l.text().trim() === labelText);
    if (!label) return null;

    const forAttr = label.attributes('for');
    if (forAttr) return wrapper.find(`#${forAttr}`);

    // If no "for" attribute, check if the label wraps an input field
    const input = label.find('input, textarea, select');
    if (input.exists()) return input;

    return null;
}

export default findByLabel;
