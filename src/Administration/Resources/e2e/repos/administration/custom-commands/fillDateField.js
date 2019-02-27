/**
 * Finds a form field in the Administration using the provided css selector. It tries to find the element on the page,
 * clears the value (if configured) and sets the provided value in the field.
 *
 * @param {String} selector
 * @param {String} date
 * @returns {exports}
 */
exports.command = function fillDateField(selector, date) {
    this.waitForElementVisible(selector);

    this.execute(
        `document.querySelector('${selector}').removeAttribute('readonly')`
    );
    this.setValue(selector, date);

    this.expect.element(selector).to.have.value.that.equals(date);

    return this;
};
