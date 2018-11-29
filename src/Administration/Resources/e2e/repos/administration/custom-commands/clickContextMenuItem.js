const contextMenuCssSelector = '.sw-context-menu';
const activeContextButtonCssSelector = '.is--active';

/**
 * Opens and clicks a context menu item in a specific parent
 *
 * @param {String} menuButtonSelector
 * @param {String} menuOpenSelector
 * @param {String|null} [parent=null]
 * @returns {exports}
 */
exports.command = function clickContextMenuItem(menuButtonSelector, menuOpenSelector, parent = null) {
    if (parent != null) {
        this
            .waitForElementVisible(parent)
            .waitForElementVisible(`${parent} ${menuOpenSelector}`)
            .click(`${parent} ${menuOpenSelector}`)
            .waitForElementVisible(`${menuOpenSelector}${activeContextButtonCssSelector}`);
    } else {
        this
            .waitForElementVisible(menuOpenSelector)
            .click(menuOpenSelector);

    }

    this
        .waitForElementVisible(contextMenuCssSelector)
        .waitForElementVisible(`${menuButtonSelector}`)
        .click(`${menuButtonSelector}`)
        .waitForElementNotPresent(contextMenuCssSelector);

    return this;
};
