/**
 * @param selector
 * @param searchTerm
 * @param value
 * @param position
 */
exports.command = function fillMultiSelect(selector, searchTerm, value, position = 0) {
    this.waitForElementVisible(selector)
        .click(selector)
        .fillField(`${selector} .sw-multi-select__input`, searchTerm, true)
        .waitForElementNotPresent(`${selector} .sw-loader`)
        .waitForElementVisible(`${selector} .sw-multi-select__results`)
        .assert.containsText(`${selector} .sw-multi-select__results .sw-multi-select-option--0`, value)
        .click(`${selector} .sw-multi-select__results .sw-multi-select-option--0`)
        .expect.element(`${selector} .sw-multi-select__selections .sw-multi-select__selection-item-holder--${position} .sw-multi-select__selection-item`).to.have.text.that.contains(value);

    this.click(`${selector} .sw-multi-select__input`)
        .clearValueManual(`${selector} .sw-multi-select__input`)
        .setValue(`${selector} .sw-multi-select__input`, this.Keys.ESCAPE)
        .waitForElementNotPresent(`${selector} .sw-multi-select__results`);

    return this;
};
