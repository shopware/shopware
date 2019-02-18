const contextMenuCssSelector = '.sw-context-menu';
const activeContextButtonCssSelector = '.is--active';

/**
 * Opens and clicks a context menu item, even if it's in a specific scope
 *
 * @param {String} menuButtonSelector
 * @param {String} menuOpenSelector
 * @param {String|null} [scope=null]
 * @returns {exports}
 */
exports.command = function clickContextMenuItem(menuButtonSelector, menuOpenSelector, scope = null) {
    if (scope != null) {
        this
            .waitForElementVisible(scope)
            .moveToElement(`${scope} ${menuOpenSelector}`, 2, 2)
            .waitForElementVisible(`${scope} ${menuOpenSelector}`)
            .click(`${scope} ${menuOpenSelector}`);

        if(scope.includes('sw-grid__row')) {
            this.waitForElementVisible(`${menuOpenSelector}${activeContextButtonCssSelector}`);
        }
    } else {
        this
            .moveToElement(menuOpenSelector, 2, 2)
            .waitForElementVisible(menuOpenSelector)
            .click(menuOpenSelector);
    }

    this
        .waitForElementVisible(contextMenuCssSelector)
        .waitForElementVisible(menuButtonSelector)
        .click(menuButtonSelector)
        .waitForElementNotPresent(contextMenuCssSelector);

    return this;
};
