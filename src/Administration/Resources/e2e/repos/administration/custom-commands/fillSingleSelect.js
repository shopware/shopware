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
        .fillField(`${selector} .sw-single-select__input-single`, value, true)
        .assert.containsText(`${selector} .sw-single-select__results-list .sw-single-select-option--0`, value);

    this
        .click(`${selector} .sw-single-select__results-list .sw-single-select-option--0`)
        .waitForElementNotPresent(`${selector} .sw-single-select__results`);
    return this;
};
