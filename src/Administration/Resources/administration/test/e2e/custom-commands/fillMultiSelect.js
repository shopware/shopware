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
    const multiSelectInput = `${selector} ${multiSelectInputCssSelector}`;

    this.waitForElementVisible(multiSelectInput);

    if (clearField) {
        this.click(removeItemCssSelector).waitForElementNotPresent(multiSelectItemCssSelector);
    }

    this.setValue(multiSelectInput, value)
        .waitForElementNotPresent('.sw-loader')
        .setValue(multiSelectInput, [this.Keys.ENTER]).setValue(multiSelectInput, [this.Keys.ESCAPE])
        .expect.element(`${selector} ${selectedItemCssSelector}`).to.have.text.that.contains(value);

    return this;
};
