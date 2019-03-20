/**
 * Finds a form field in the Administration using the provided selector. The method uses that selector to find the element on the page and ticks it.
 *
 * @param {String} selector
 * @param {String} checked
 * @returns {exports}
 */
exports.command = function tickCheckbox(selector, checked) {
    this.waitForElementPresent(selector);

    this.click(selector);

    if (checked) {
        this.expect.element(selector).to.be.selected;
        return this;
    }
    this.expect.element(selector).to.not.be.selected;

    return this;
};
