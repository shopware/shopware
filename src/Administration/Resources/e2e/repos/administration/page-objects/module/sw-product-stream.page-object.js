const GeneralPageObject = require('../sw-general.page-object');


class RuleBuilderPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);

        this.elements = {
            ...this.elements, ...{
                columnName: '.sw-product-stream-list__column-name',
                streamSaveAction: '.sw-product-stream-detail__save-action'
            }
        };
    }

    createBasicProductStream(name, description) {
        this.browser
            .fillField('input[name=sw-field--productStream-name]', name)
            .fillField('textarea[name=sw-field--productStream-description]', description)
            .waitForElementVisible(this.elements.streamSaveAction)
            .click(this.elements.streamSaveAction)
            .checkNotification(`The product stream "${name}" has been saved successfully.`);
    }

    deleteProductStream(productStreamName) {
        this.browser
            .waitForElementPresent('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .click('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .waitForElementNotPresent(this.elements.loader)
            .clickContextMenuItem('.sw-context-menu-item--danger', this.elements.contextMenuButton, `${this.elements.gridRow}--0`)
            .waitForElementVisible(this.elements.modal)
            .assert.containsText(`${this.elements.modal}__body`, `Are you sure you want to delete the product stream "${productStreamName}"?`)
            .click(`.sw-modal__footer button${this.elements.primaryButton}`)
            .waitForElementNotPresent(this.elements.modal);
    }
}

module.exports = (browser) => {
    return new RuleBuilderPageObject(browser);
};
