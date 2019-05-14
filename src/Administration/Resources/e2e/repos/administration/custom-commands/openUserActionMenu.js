/**
 * Opens the user-related menu section of the admin menu if necessary
 *
 * @returns {exports}
 */
exports.command = function openUserActionMenu() {
    this.waitForElementVisible('.sw-admin-menu__user-actions-toggle');

    // check admin menu is extended already
    return this.element('css selector', '.sw-admin-menu__user-actions-indicator.icon--small-arrow-medium-up', (element) => {
        // menus is already expanded
        if (element.status === -1) {
            return this;
        }

        this.click('.sw-admin-menu__user-actions-toggle')
            .waitForElementVisible('.sw-admin-menu__logout-action');
    });
};
