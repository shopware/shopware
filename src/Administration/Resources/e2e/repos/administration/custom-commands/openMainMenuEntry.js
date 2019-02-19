let mainMenuCssSelector = '.sw-admin-menu__item--';
const flyoutMenuCssSelector = '.sw-admin-menu__flyout-item--';
const flyoutCssSelector = '.sw-admin-menu__flyout';
const subMenuCssSelector = '.sw-admin-menu__navigation-list-item';


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

    this.waitForElementVisible('.sw-admin-menu', function(){
        // We're dealing with a sub menu entry, so we have to find and click it
        if (subMenuId) {
            this.moveToElement(`${mainMenuCssSelector}${mainMenuId}`, 5, 5);
            this.element('css selector', flyoutCssSelector, (res) => {
                if (res.value.ELEMENT) {
                    finalMenuItem = `${flyoutMenuCssSelector}${subMenuId}`;
                } else {
                    finalMenuItem = `${subMenuCssSelector}.${subMenuId}`;
                }
                this.click(finalMenuItem).assert.urlContains(targetPath);
            });
        } else {
            this.waitForElementVisible(finalMenuItem, () => {
                this.click(`${finalMenuItem} a:first-child`).assert.urlContains(targetPath);
            });
        }
    });

    return this;
};