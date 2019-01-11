/**
 * Finds a form field in the Administration using the provided selector. The method uses that selector to find the element on the page and ticks it.
 *
 * @param {String} selector
 * @param {String} value
 * @returns {exports}
 */
exports.command = function tickCheckbox(selector, value) {
    this.waitForElementPresent(selector);

    this.click(selector)
        .expect.element(selector).to.have.value.that.equals(value);

    return this;
};