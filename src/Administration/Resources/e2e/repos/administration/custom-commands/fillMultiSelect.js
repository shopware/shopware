/**
 * @param selector
 * @param searchTerm
 * @param value
 */
exports.command = function fillMultiSelect(selector, searchTerm, value) {
    this.waitForElementVisible(selector)
        .click(selector)
        .fillField(`${selector} .sw-multi-select__input`, searchTerm, true)
        .waitForElementNotPresent(`${selector} .sw-loader`)
        .waitForElementVisible(`${selector} .sw-multi-select__results`)
        .assert.containsText(`${selector} .sw-multi-select__results .sw-multi-select-option--0`, value)
        .click(`${selector} .sw-multi-select__results .sw-multi-select-option--0`);

    return this;
};
