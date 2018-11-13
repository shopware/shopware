/**
 * Tries to find an element with the provided text. When the element isn't present in the defined timeout,
 * an error will be triggered.
 *
 * Usage:
 * ```
 * this.waitForText('Erfolgreich');
 * ```
 *
 * Exact match usage example:
 * ```
 * this.waitForText('Erfolgreich', true);
 * ```
 *
 * Using this parameter finds all text elements that equal the provided text after removing leading and
 * trailing whitespaces. Please keep in mind nightwatch uses the first element it can find.
 *
 * @param {String} text
 * @param {Boolean} [exactMatch=false]
 * @param {Number} [timeout=5000]
 * @returns {exports}
 */
exports.command = function waitForText(text, exactMatch = false, timeout = 5000) {
    // Removing leading and trailing whitespaces
    let xpath = `//*[contains(text(), '${text}')]`;

    if (exactMatch) {
        xpath = `//*/text()[normalize-space(.)='${text}']/parent::*`;
    }

    this.useXpath().waitForElementVisible(xpath, timeout);
    this.expect.element(xpath).to.have.text.that.contains(text);
    this.useCss();

    return this;
};