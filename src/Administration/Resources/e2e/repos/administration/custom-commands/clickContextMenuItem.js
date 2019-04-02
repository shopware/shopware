const contextMenuCssSelector = '.sw-context-menu';
const activeContextButtonCssSelector = '.is--active';

/**
 * Opens and clicks a context menu item, even if it's in a specific scope
 *
 * @param {String} menuOpenSelector
 * @param {Object|null} [options=null]
 * @returns {exports}
 */
exports.command = function clickContextMenuItem(menuOpenSelector, {
    menuActionSelector = null, scope = null
}) {
    if (scope != null) {
        this
            .waitForElementVisible(scope)
            .moveToElement(`${scope} ${menuOpenSelector}`, 2, 2)
            .waitForElementVisible(`${scope} ${menuOpenSelector}`)
            .click(`${scope} ${menuOpenSelector}`);

        if (scope.includes('sw-grid__row')) {
            this.waitForElementVisible(`${menuOpenSelector}${activeContextButtonCssSelector}`);
        }
    } else {
        this
            .moveToElement(menuOpenSelector, 2, 2)
            .waitForElementVisible(menuOpenSelector)
            .click(menuOpenSelector);
    }

    this.waitForElementVisible(contextMenuCssSelector);

    if (menuActionSelector != null) {
        this
            .waitForElementVisible(menuActionSelector)
            .click(menuActionSelector)
            .waitForElementNotPresent(contextMenuCssSelector);
    }

    return this;
};
