/**
 * Finds a form field in the Administration using the provided css selector. It tries to find the element on the page,
 * clears the value (if configured) and sets the provided value in the field.
 *
 * @param {String} selector
 * @param {String} date
 * @returns {exports}
 */
exports.command = function fillDateField(selector, date) {
    // Get selector for both fields
    const hiddenDateFieldSelector = `${selector}[type=hidden]`;
    const visibleDateFieldSelector = `${selector}.input`;

    this.waitForElementPresent(hiddenDateFieldSelector);
    this.waitForElementVisible(visibleDateFieldSelector);

    // Set hidden field temporary visible
    this.execute(
        `document.querySelector('${hiddenDateFieldSelector}').removeAttribute('type')`
    );
    this.waitForElementVisible(selector);

    // Set hidden ISO date
    const isoDate = `${date.split(' ').join('T')}:00+00:00`;
    this.setValue(selector, isoDate);
    this.expect.element(selector).to.have.value.that.equals(isoDate);

    // Set field hidden again
    this.execute(
        `document.querySelector('${selector}').setAttribute('type', 'hidden')`
    );

    // Set visible date
    this.execute(
        `document.querySelector('${visibleDateFieldSelector}').removeAttribute('readonly')`
    );

    this.setValue(visibleDateFieldSelector, date);
    this.expect.element(visibleDateFieldSelector).to.have.value.that.equals(date);

    return this;
};
