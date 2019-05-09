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
        .click(`${selector} .sw-multi-select__results .sw-multi-select-option--0`)
        .waitForElementVisible(`${selector} .sw-multi-select__selections .sw-multi-select__selection-item-holder--0`)
        .assert.containsText(`${selector} .sw-multi-select__selections .sw-multi-select__selection-item-holder--0 .sw-multi-select__selection-item`, value)
        .setValue(`${selector} .sw-multi-select__input`, this.Keys.ESCAPE)
        .waitForElementNotPresent(`${selector} .sw-multi-select__results`);

    return this;
};
