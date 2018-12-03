/**
 * Checks if a notification prints out the message the user expects to get.
 *
 * @param {String} message
 * @param {Boolean} [toBeClosed=true]
 * @returns {exports}
 */
exports.command = function checkNotification(message, toBeClosed = true) {
    this
        .waitForElementVisible('.sw-notifications .sw-alert')
        .assert.containsText('.sw-alert .sw-alert__message', message);

    if (toBeClosed) {
        this
            .click('.sw-alert .sw-alert__close')
            .waitForElementNotPresent('.sw-notifications .sw-alert');
    }
    return this;
};
