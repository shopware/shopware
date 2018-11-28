

/**
 * Finds a form field in the Administration using the provided selector. The method uses that selector to find the element on the page and ticks it.
 *
 * @param {String} selector
 * @returns {exports}
 */
exports.command = function clickContextMenuItem(selector) {
    this
        .waitForElementPresent(selector)
        .waitForElementVisible('.sw-grid-row:first-child .sw-context-button__button')
        .click('.sw-grid-row:first-child .sw-context-button__button')
        .waitForElementPresent('body > .sw-context-menu')
        .click('body > .sw-context-menu .sw-context-menu-item--danger');

    return this;
};
