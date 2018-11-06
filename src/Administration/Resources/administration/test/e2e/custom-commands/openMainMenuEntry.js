const menuCssSelector = '.sw-admin-menu__navigation-link';
const flyoutMenuCssSelector = '.sw-admin-menu__flyout';

/**
 * Finds and opens a main menu entry in the Shopware Administration menu. It is possible to provide a sub menu item name
 * to open sub menu entries.
 *
 * @param {String} mainMenuPath
 * @param {String} menuTitle
 * @param {String|null} [subMenuItemPath=null]
 * @param {String|null} [subMenuTitle=null]
 * @returns {exports}
 */
exports.command = function openMainMenuEntry(mainMenuPath, menuTitle, subMenuItemPath = null, subMenuTitle = null) {
    const mainMenuItem = `${menuCssSelector}[href="${mainMenuPath}"]`;

    this.waitForElementVisible(mainMenuItem);

    // We're dealing with a sub menu entry, so we have to find and click it
    if (subMenuItemPath) {
        this.moveToElement(mainMenuItem, 5, 5);
        this.waitForElementVisible(flyoutMenuCssSelector);

        const subMenuItem = `${flyoutMenuCssSelector} ${menuCssSelector}[href="${subMenuItemPath}"]`;

        this.waitForElementVisible(subMenuItem)
            .assert.containsText(subMenuItem, subMenuTitle)
            .click(subMenuItem)
            .assert.urlContains(subMenuItemPath);

        return this;
    }

    this.assert.containsText(mainMenuItem, menuTitle);
    // Just click the main menu item
    return this.click(mainMenuItem);
};