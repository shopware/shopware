let mainMenuCssSelector = '.sw-admin-menu__item--';
const flyoutMenuCssSelector = '.sw-admin-menu__flyout-item--';


/**
 * Finds and opens a main menu entry in the Shopware Administration menu. It is possible to provide a sub menu item name
 * to open sub menu entries.
 *
 * @param {Object} obj
 * @param {String} obj.targetPath
 * @param {String} obj.mainMenuId
 * @param {String} obj.subMenuId

 * @returns {exports}
 */
exports.command = function openMainMenuEntry(
    {targetPath, mainMenuId, subMenuId = null}
) {
    let finalMenuItem = `${mainMenuCssSelector}${mainMenuId}`;
    this.waitForElementVisible(finalMenuItem);

    // We're dealing with a sub menu entry, so we have to find and click it
    if (subMenuId) {
        this.moveToElement(`${mainMenuCssSelector}${mainMenuId}`, 5, 5);
        finalMenuItem = `${flyoutMenuCssSelector}${subMenuId}`;
    }
    this.click(finalMenuItem).assert.urlContains(targetPath);

    return this;
};