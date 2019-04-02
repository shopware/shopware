const GeneralPageObject = require('../sw-general.page-object');

class IntegrationPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);

        this.elements = {
            ...this.elements,
            ...{
                integrationName: 'input[name=sw-field--currentIntegration-label]',
                integrationSaveAction: '.sw-integration-detail-modal__save-action',
                apiAccessKeyField: 'input[name=sw-field--currentIntegration-accessKey]',
                listColumnName: '.sw-integration-list__column-integration-name',
                listHeadline: '.sw-integration-list__welcome-headline'
            }
        };

        this.accessKeyId = '';
        this.newAccessKeyId = '';
    }

    checkClipboard() {
        const me = this;

        this.browser.getValue(this.elements.apiAccessKeyField, (result) => {
            me.accessKeyId = result.value;

            me.browser
                .waitForElementVisible('.sw-field__copy-tooltip:nth-of-type(1) .sw-icon')
                .click('.sw-field__copy-tooltip:nth-of-type(1) .sw-icon')
                .clearValue(me.elements.integrationName)
                .setValue(me.elements.integrationName, [me.browser.Keys.CONTROL, 'v'])
                .expect.element(me.elements.integrationName).value.that.equals(me.accessKeyId);
        });

        this.browser
            .waitForElementPresent(`${this.elements.modal}__close`)
            .click(`${this.elements.modal}__close`)
            .waitForElementNotPresent(this.elements.modalTitle);
    }

    changeApiCredentials() {
        const me = this;

        this.browser
            .waitForElementPresent('.sw-button--danger')
            .click('.sw-button--danger');

        this.browser.getValue(this.elements.apiAccessKeyField, (result) => {
            me.newAccessKeyId = result.value;
        });
        this.newAccessKeyId = me.newAccessKeyId;

        this.browser
            .waitForElementNotPresent(me.elements.loader)
            .expect.element(this.elements.integrationSaveAction).to.be.enabled;

        this.browser
            .click(this.elements.integrationSaveAction)
            .checkNotification('Integration has been saved successfully');
    }

    verifyChangedApiCredentials() {
        const me = this;

        this.browser.getValue(this.elements.apiAccessKeyField, (result) => {
            me.newAccessKeyId = result.value;

            me.browser.expect.element(me.elements.apiAccessKeyField).value.that.equals(me.newAccessKeyId);
        });

        this.browser
            .waitForElementPresent(`${this.elements.modal}__close`)
            .click(`${this.elements.modal}__close`)
            .waitForElementNotPresent(`${this.elements.modal}__title`);
    }

    deleteSingleIntegration(integrationName) {
        this.browser
            .clickContextMenuItem(this.elements.contextMenuButton, {
                menuActionSelector: `${this.elements.contextMenu}-item--danger`,
                scope: `${this.elements.gridRow}--0`
            })
            .expect.element(`${this.elements.modal}__body`).text.that.equals(`Are you sure you want to delete this integration? "${integrationName}"`);

        this.browser
            .click(`${this.elements.modal} button.sw-button--primary`)
            .waitForElementNotPresent(this.elements.modal)
            .waitForElementNotPresent(this.elements.listColumnName)
            .expect.element('.sw-empty-state__title').text.that.equals('No integrations yet');
    }
}

module.exports = (browser) => {
    return new IntegrationPageObject(browser);
};
