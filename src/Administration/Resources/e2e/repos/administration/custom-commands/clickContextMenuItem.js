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
            .waitForElementVisible(`${scope} ${menuOpenSelector}`)
            .click(`${scope} ${menuOpenSelector}`)
            .waitForElementVisible(`${menuOpenSelector}${activeContextButtonCssSelector}`);
    } else {
        this
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
