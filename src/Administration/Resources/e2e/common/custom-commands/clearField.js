/**
 * Clears an input field and making sure that the field is in fact empty afterwards
 *
 * @param {String} selector
 * @param {String} type
 * @returns {exports}
 */
exports.command = function clearField(selector, type = 'input') {
    this.waitForElementVisible(selector)
        .setValue(selector, ['', this.Keys.CONTROL, 'a'])
        .setValue(selector, this.Keys.DELETE)
        .clearValue(selector);

    if (type === 'editor') {
        this.waitForElementPresent('.sw-text-editor__content-editor');
        return this;
    }

    this.expect.element(selector).to.have.value.that.equals('');
    return this;
};

