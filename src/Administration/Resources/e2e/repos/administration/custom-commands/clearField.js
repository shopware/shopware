/**
 * Clears an input field and making sure that the field is in fact empty afterwards
 *
 * @param {String} selector
 * @param {String} type
 * @returns {exports}
 */
exports.command = function clearField(selector, type = 'input') {
    this.clearValue(selector);

    if (this.getValue(selector) !== '') {
        this.setValue(selector, ['', this.Keys.CONTROL, 'a']);
        this.setValue(selector, ['', this.Keys.DELETE]);
    }

    if (type === 'editor') {
        this.waitForElementPresent('.ql-blank');

        return this;
    }

    this.expect.element(selector).to.have.value.that.equals('');
    return this;
};

