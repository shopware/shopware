const searchInputCssSelector = 'input.sw-search-bar__input';

/**
 * Uses the global search input field in the Administration for finding a product or other entity.
 *
 * @param {String} value
 * @param {Boolean} [clearField=false]
 * @returns {exports}
 */
exports.command = function fillGlobalSearchField(value, clearField = false) {
    this.waitForElementVisible(searchInputCssSelector);

    if (clearField) {
        this.clearField(searchInputCssSelector);
    }

    this.setValue(searchInputCssSelector, [value, this.Keys.ENTER]);
    this.expect.element(searchInputCssSelector).to.have.value.that.equals(value);

    return this;
};