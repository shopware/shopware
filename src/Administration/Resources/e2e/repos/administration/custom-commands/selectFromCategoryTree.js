/**
 * Finds a sw-select component in the Administration. The method uses a css selector to find the element on the page,
 * removes a preselected items (if configured). If a search term is provided it will be entered to the input and after
 * the search the first result gets selected.
 *
 * @param {String} selector
 * @param {String} value
 * @returns {exports}
 */
exports.command = function selectFromCategoryTree(
    selector,
    value
) {
    this
        .click(`${selector} .sw-category-tree__input-field`)
        .fillField(`${selector} .sw-category-tree__input-field`, value, true)
        .waitForElementVisible(`${selector} .sw-category-tree-field__search-results`)
        .waitForElementVisible(`${selector} .sw-category-tree-field__search-result`)
        .assert.containsText(`${selector} .sw-category-tree-field__search-result .sw-highlight-text__highlight`, value);

    this.setValue(`${selector} .sw-category-tree__input-field`, this.Keys.ENTER)
        .assert.containsText(`${selector} .sw-category-tree-field__label-property`, value)
        .setValue(`${selector} .sw-category-tree__input-field`, this.Keys.ESCAPE)
        .waitForElementNotPresent(`${selector} .sw-category-tree-field__search-results`)
        .waitForElementNotPresent(`${selector} .sw-tree-item__element`);
};
