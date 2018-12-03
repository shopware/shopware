/**
 * Checks the notification and its message: Checks if a notification prints out the message the user expects to get. Afterwards the notification can be closed, if required
 *
 * @param {String} message
 * @param {String} [type=.sw-alert]
 * @param {Boolean} [toBeClosed=true]
 * @returns {exports}
 */
exports.command = function checkNotification(message, type = '.sw-alert', toBeClosed = true) {
    this
        .waitForElementVisible(`.sw-notifications ${type}`)
        .waitForElementVisible(`.sw-notifications ${type}`)
        .assert.containsText(`${type} ${type}__message`, message);

    if (toBeClosed) {
        this
            .click(`${type} ${type}__close`)
            .waitForElementNotPresent(`.sw-notifications ${type}`);
    }
    return this;
};
