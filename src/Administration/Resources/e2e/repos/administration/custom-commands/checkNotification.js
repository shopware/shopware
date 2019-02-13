const defaultNotificationIndex = '.sw-notifications__notification--0';

/**
 * Checks the notification and its message: Checks if a notification prints out the message the user expects to get. Afterwards the notification can be closed, if required
 *
 * @param {String} message
 * @param {String} [notification=.sw-notifications__notification--0]
 * @param {Boolean} [toBeClosed=true]
 * @param {String} [type=.sw-alert]
 * @returns {exports}
 */
exports.command = function checkNotification(message, notification = defaultNotificationIndex, toBeClosed = true, type = '.sw-alert') {
    this.expect.element(`${notification} ${type}__message`).to.have.text.that.contains(message);

    if (toBeClosed) {
        this
            .waitForElementVisible(`${notification} ${type}__close`)
            .click(`${notification} ${type}__close`)
            .waitForElementNotPresent(notification);
    }
    return this;
};
