/**
 * @param selector
 * @param value
 * @param resultPosition
 */
exports.command = function fillSingleSelect(selector, value, resultPosition = 0) {
    this.waitForElementVisible(selector)
        .click(selector)
        .waitForElementVisible(`${selector} .sw-single-select__results-list`)
        .waitForElementNotPresent(`${selector} .sw-loader__element`)
        .waitForElementVisible(`${selector} .sw-single-select__results`)
        .fillField(`${selector} .sw-single-select__input-single`, value, true)
        .assert.containsText(`${selector} .sw-single-select__results-list .sw-single-select-option--${resultPosition}`, value);

    this
        .click(`${selector} .sw-single-select__results-list .sw-single-select-option--${resultPosition}`)
        .waitForElementNotPresent(`${selector} .sw-single-select__results`);
    return this;
};
