class RuleBuilderPageObject {
    constructor(browser) {
        this.browser = browser;
        this.elements = {
            columnName: '.sw-product-stream-list__column-name'
        };
    }

    createBasicProductStream(name, description) {
        this.browser
            .fillField('input[name=sw-field--productStream-name]', name)
            .fillField('textarea[name=sw-field--productStream-description]', description);

        this.browser
            .waitForElementVisible('.sw-product-stream-detail__save-action')
            .click('.sw-product-stream-detail__save-action')
            .checkNotification(`The product stream "${name}" was saved.`);
    }

    deleteProductStream(productStreamName) {
        this.browser
            .waitForElementPresent('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .click('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .waitForElementNotPresent('.sw-loader')
            .clickContextMenuItem('.sw-context-menu-item--danger', '.sw-context-button__button', '.sw-grid-row:first-child')
            .waitForElementVisible('.sw-modal')
            .assert.containsText('.sw-modal__body',`Are you sure you want to delete the product stream "${productStreamName}"?`)
            .click('.sw-modal__footer button.sw-button--primary')
            .waitForElementNotPresent('.sw-modal');
    }
}

module.exports = (browser) => {
    return new RuleBuilderPageObject(browser);
};
