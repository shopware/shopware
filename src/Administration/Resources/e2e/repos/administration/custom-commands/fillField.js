/**
 * Finds a form field in the Administration using the provided css selector. It tries to find the element on the page,
 * clears the value (if configured) and sets the provided value in the field.
 *
 * @param {String} selector
 * @param {String} value
 * @param {Boolean} [clearField=true]
 * @returns {exports}
 */
exports.command = function fillField(selector, value, clearField = true) {
    this.waitForElementVisible(selector);

    if (clearField) {
        this.clearValue(selector);
    }

    this.setValue(selector, value);
    this.expect.element(selector).to.have.value.that.equals(value);

    return this;
};