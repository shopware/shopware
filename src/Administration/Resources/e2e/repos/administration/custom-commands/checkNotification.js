/**
 * Checks the notification and its message: Checks if a notification prints out the message the user expects to get. Afterwards the notification can be closed, if required
 *
 * @param {String} message
 * @param {Boolean} [toBeClosed=true]
 * @param {String} [type=.sw-alert]
 * @returns {exports}
 */
exports.command = function checkNotification(message, toBeClosed = true, type = '.sw-alert') {
    this
        .waitForElementVisible(`.sw-notifications ${type}`)
        .waitForElementVisible(`${type} ${type}__message`)
        .assert.containsText(`${type} ${type}__message`, message);

    if (toBeClosed) {
        this
            .click(`${type} ${type}__close`)
            .waitForElementNotPresent(`.sw-notifications ${type}`);
    }
    return this;
};
