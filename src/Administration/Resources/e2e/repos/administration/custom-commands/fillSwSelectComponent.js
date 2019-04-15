const swSelectInputCssSelector = '.sw-select__input-single';
const swMultiSelectInputCssSelector = '.sw-select__input';
const swMultiSelectItemCssSelector = '.sw-label';
const swSelectedItemCssSelector = '.sw-select__selection-item';
const swMultiSelectRemoveItemCssSelector = '.sw-label__dismiss';
const swSelectResultsCssSelector = '.sw-select__results';
const swSelectLoaderCssSelector = '.sw-select__indicators .sw-loader';
const swSelectPlaceholderCssSelector = '.sw-select__placeholder';
const swSelectLabelCssSelector = '.sw-select__single-selection';


/**
 * Finds a sw-select component in the Administration. The method uses a css selector to find the element on the page,
 * removes a preselected items (if configured). If a search term is provided it will be entered to the input and after
 * the search the first result gets selected.
 *
 * @param {String} selector
 * @param {Object} obj
 * @param {String} obj.value
 * @param {Boolean} obj.clearField
 * @param {Boolean} obj.isMulti
 * @param {String} obj.searchTerm
 * @returns {exports}
 */
exports.command = function fillSwSelectComponent(
    selector,
    { value, clearField = false, isMulti = false, searchTerm = '' }
) {
    const inputCssSelector = (isMulti) ? swMultiSelectInputCssSelector : swSelectInputCssSelector;
    const me = this;

    this.waitForElementVisible(selector);

    if (clearField && isMulti) {
        this.click(`${selector} ${swMultiSelectRemoveItemCssSelector}`)
            .waitForElementNotPresent(`${selector} ${swMultiSelectItemCssSelector}`);
    }

    if (!isMulti) {
        // open results list
        this
            .waitForElementPresent(selector)
            .waitForElementVisible(selector)
            .click(selector, function waitForResults(clickResult) {
                me.click(selector);
                global.logger.error(`Element click: "${clickResult.status}" / Retry.`);
            })
            .waitForElementVisible(`${selector} ${swSelectResultsCssSelector}`);
    }

    // type in the search term if available
    if (searchTerm) {
        this.fillField(`${selector} ${inputCssSelector}`, searchTerm);
        this.waitForElementNotPresent(`${selector} ${swSelectLoaderCssSelector}`)
            .waitForElementVisible('.sw-select__results');
    }
    this.assert.containsText('.sw-select-option--0', value);

    // select the first result
    this.keys(this.Keys.ENTER);

    if (!isMulti) {
        // expect the placeholder for an empty select field not be shown and search for the value
        this.waitForElementNotPresent(`${selector} ${swSelectPlaceholderCssSelector}`)
            .expect.element(`${selector} ${swSelectLabelCssSelector}`).to.have.text.that.contains(value);

        return this;
    }

    // in multi selects we can check if the value is a selected item
    this.expect.element(`${selector} ${swSelectedItemCssSelector}`).to.have.text.that.contains(value);

    // close search results
    this.setValue(`${selector} ${inputCssSelector}`, this.Keys.ESCAPE)
        .waitForElementNotPresent(`${selector} ${swSelectResultsCssSelector}`);
    return this;
};
