const searchInputCssSelector = 'input.sw-search-bar__input';

/**
 * Uses the global search input field in the Administration for finding a product or other entity.
 *
 * @param {String} value
 * @param {Boolean} [clearField=true]
 * @returns {exports}
 */
exports.command = function fillGlobalSearchField(value, clearField = true) {
    this.waitForElementVisible(searchInputCssSelector);

    if (clearField) {
        this.clearValue(searchInputCssSelector);
    }

    this.setValue(searchInputCssSelector, [value, this.Keys.ENTER]);
    this.expect.element(searchInputCssSelector).to.have.value.that.equals(value);

    return this;
};