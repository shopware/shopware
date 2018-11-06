const multiSelectInputCssSelector = '.sw-multi-select__input';
const multiSelectItemCssSelector = '.sw-multi-select__selection-item';
const selectedItemCssSelector = '.sw-multi-select__selection-text';
const removeItemCssSelector = '.sw-multi-select__selection-dismiss';

/**
 * Finds a multiselect field in the Administration. The method uses a css selector to find the element on the page,
 * removes a preselected item (if configured) and sets the provided value in the field.
 *
 * @param {String} selector
 * @param {String} value
 * @param {Boolean} [clearField=true]
 * @returns {exports}
 */
exports.command = function fillMultiSelect(selector, value, clearField = false) {
    this.waitForElementVisible(selector);

    if (clearField) {
        this.click(removeItemCssSelector).waitForElementNotPresent(multiSelectItemCssSelector);
    }

    this.setValue(multiSelectInputCssSelector, [value, this.Keys.ENTER]).setValue(multiSelectInputCssSelector, [this.Keys.ESCAPE]);
    this.expect.element(selectedItemCssSelector).to.have.text.that.contains(value);

    return this;
};