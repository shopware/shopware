/**
 * Finds a form field in the Administration using the provided css selector. It tries to find the element on the page,
 * clears the value (if configured) and sets the provided value in the field.
 *
 * @param {String} selector
 * @param {String} value
 * @param {Boolean} [clearField=true]
 * @param {String} [type=input]
 * @returns {exports}
 */
exports.command = function fillField(selector, value, clearField = false, type = 'input') {
    this.waitForElementVisible(selector);

    if (clearField) {
        this.clearField(selector, type);
    }

    this.setValue(selector, value);

    if (type === 'editor') {
        this.expect.element(selector).to.have.text.that.contains(value);
        return this;
    }

    this.expect.element(selector).to.have.value.that.contains(value);

    return this;
};
