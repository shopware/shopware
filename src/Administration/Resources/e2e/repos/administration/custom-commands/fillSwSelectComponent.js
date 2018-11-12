const swSelectInputCssSelector = '.sw-select__input-single';
const swMultiSelectInputCssSelector = '.sw-select__input';
const swMultiSelectItemCssSelector = '.sw-select__selection-item';
const swSelectedItemCssSelector = '.sw-select__selection-text';
const swMultiSelectRemoveItemCssSelector = '.sw-select__selection-dismiss';
const swSelectResultsCssSelector = '.sw-select__results';
const swSelectLoaderCssSelector = '.sw-select__indicators .sw-loader';
const swSelectPlaceholder = '.sw-select__placeholder';

/**
 * Finds a sw-select component in the Administration. The method uses a css selector to find the element on the page,
 * removes a preselected items (if configured). If a search term is provided it will be entered to the input and after
 * the search the first result gets selected.
 *
 * @param {String} selector
 * @param {String} value
 * @param {Boolean} [clearField=false]
 * @param {Boolean} [isMulti=false]
 * @param {String} [searchTerm]
 * @returns {exports}
 */
exports.command = function fillSwSelectComponent(selector, value, clearField = false, isMulti = false, searchTerm = '') {
    const inputCssSelector = (isMulti) ? swMultiSelectInputCssSelector : swSelectInputCssSelector;
    this.waitForElementVisible(selector);

    if (clearField && isMulti) {
        this.click(`${selector} ${swMultiSelectRemoveItemCssSelector}`)
            .waitForElementNotPresent(`${selector} ${swMultiSelectItemCssSelector}`);
    }

    if (!isMulti) {
        // open results list
        this.click(`${selector}`).waitForElementVisible(`${selector} ${swSelectResultsCssSelector}`);
    }

    // type in the search term if available
    if (searchTerm) {
        this.setValue(`${selector} ${inputCssSelector}`, searchTerm);
        this.waitForElementNotPresent(`${selector} ${swSelectLoaderCssSelector}`);
    }

    // select the first result
    this.keys(this.Keys.ENTER);

    if (!isMulti) {
        // expect the placeholder for an empty select field not be shown and search for the value
        this.waitForElementNotPresent(`${selector} ${swSelectPlaceholder}`);
        this.waitForText(value);

        return this;
    }

    // in multi selects we can check if the value is a selected item
    this.expect.element(`${selector} ${swSelectedItemCssSelector}`).to.have.text.that.contains(value);

    return this;
};
