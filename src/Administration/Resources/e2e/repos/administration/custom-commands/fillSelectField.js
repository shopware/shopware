/**
 * Finds a form field in the Administration using the provided label. The method uses a CSS selector to find the element on the page,
 * clears the value (if configured) and sets the provided value in the field.
 *
 * @param {String} selector
 * @param {String} value
 * @param {Boolean} [clearField=true]
 * @returns {exports}
 */
exports.command = function fillSelectField(selector, value, clearField = true) {
    this.waitForElementVisible(selector);

    if (clearField) {
        this.clearValue(selector);
    }

    this.setValue(selector, value);
    this.waitForText(value, true);
    this.useCss();

    return this;
};