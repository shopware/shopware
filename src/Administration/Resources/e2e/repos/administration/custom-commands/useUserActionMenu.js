/**
 * Opens or collapses the user-related menu section of the admin menu, containing language switch, profile and logout
 *
 * @param {String} username
 * @param {Boolean} [open=true]
 * @returns {exports}
 */
exports.command = function useUserActionMenu(username, open = true) {
    this
        .waitForElementVisible('.sw-admin-menu__user-actions-toggle')
        .waitForElementVisible('.sw-admin-menu__user-name')
        .assert.containsText('.sw-admin-menu__user-name', username)
        .click('.sw-admin-menu__user-actions-toggle');

    if (open) {
        this.waitForElementVisible('.sw-admin-menu__logout-action');
        return this;
    }
    this.waitForElementNotVisible('.sw-admin-menu__logout-action');

    return this;
};
