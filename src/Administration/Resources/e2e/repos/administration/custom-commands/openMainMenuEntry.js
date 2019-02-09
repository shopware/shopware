const menuLinkCssSelector = '.sw-admin-menu__navigation-link';
let menuCssSelector = '.sw-admin-menu__item--';
const flyoutMenuCssSelector = '.sw-admin-menu__flyout';

/**
 * Finds and opens a main menu entry in the Shopware Administration menu. It is possible to provide a sub menu item name
 * to open sub menu entries.
 *
 * @param {Object} obj
 * @param {String} obj.mainMenuPath
 * @param {String} obj.menuTitle
 * @param {String} obj.subMenuItemPath
 * @param {String} obj.subMenuTitle
 * @param {Int} obj.index
 * @param {String} menuTitle

 * @returns {exports}
 */
//exports.command = function openMainMenuEntry(mainMenuPath, menuTitle, subMenuItemPath = null, subMenuTitle = null) {
exports.command = function openMainMenuEntry({mainMenuPath, menuTitle, index = null, subMenuItemPath = null, subMenuTitle = null }) {
    let mainMenuItem = `${menuLinkCssSelector}[href="${mainMenuPath}"]`;

    if (index !== null) {
        mainMenuItem = `${menuCssSelector}${index} > ${menuLinkCssSelector}[href="${mainMenuPath}"]`;
    }

    this.waitForElementVisible(mainMenuItem);

    // We're dealing with a sub menu entry, so we have to find and click it
    if (subMenuItemPath) {
        this.moveToElement(mainMenuItem, 5, 5);
        this.waitForElementVisible(flyoutMenuCssSelector);

        const subMenuItem = `${flyoutMenuCssSelector} ${menuLinkCssSelector}[href="${subMenuItemPath}"]`;

        this.waitForElementVisible(subMenuItem)
            .assert.containsText(subMenuItem, subMenuTitle)
            .click(subMenuItem)
            .assert.urlContains(subMenuItemPath);

        return this;
    }

    this.assert.containsText(mainMenuItem, menuTitle)
        .click(mainMenuItem)
        .assert.urlContains(mainMenuPath);

    return this;
};