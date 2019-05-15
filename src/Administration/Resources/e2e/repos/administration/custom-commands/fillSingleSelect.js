/**
 * @param selector
 * @param value
 * @param resultPosition
 */
exports.command = function fillSingleSelect(selector, value, resultPosition = 0) {
    this.waitForElementVisible(selector)
        .click(selector)
        .waitForElementVisible(`${selector} .sw-single-select__results`)
        .expect.element(`${selector} .sw-single-select__results-list .sw-single-select-option--${resultPosition}`).to.have.text.that.contains(value);

    this
        .click(`${selector} .sw-single-select__results-list .sw-single-select-option--${resultPosition}`)
        .waitForElementNotPresent(`${selector} .sw-single-select__results`);
    return this;
};
