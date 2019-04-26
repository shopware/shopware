const loadingIndicator = '.sw-field__select-load-placeholder';

/**
 * Finds a form field in the Administration using the provided label. The method uses a CSS selector to find the element on the page,
 * clears the value (if configured) and sets the provided value in the field.
 *
 * @param {String} selector
 * @param {String} value
 * @returns {exports}
 */
exports.command = function fillSelectField(selector, value) {
    const xpath = `//option[contains(text(), '${value}')]`;

    this
        .waitForElementVisible(selector)
        .waitForElementNotPresent(loadingIndicator)
        .setValue(selector, value)
        .useXpath();
    this.expect.element(xpath).to.be.visible.before(1000);
    this.useCss();

    return this;
};

