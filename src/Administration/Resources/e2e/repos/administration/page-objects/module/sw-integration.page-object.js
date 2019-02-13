const GeneralPageObject = require('../sw-general.page-object');

class IntegrationPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);

        this.elements = {
            ...this.elements, ...{
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

        this.browser.getValue(this.elements.apiAccessKeyField, function checkValuePresent(result) {
            me.accessKeyId = result.value;

            me.browser
                .waitForElementVisible('.sw-field__copy-button:nth-of-type(1)')
                .click('.sw-field__copy-button:nth-of-type(1)')
                .checkNotification('Text has been copied to clipboard.')
                .clearValue(me.elements.integrationName)
                .setValue(me.elements.integrationName, ['', me.browser.Keys.CONTROL, 'v'])
                .expect.element(me.elements.integrationName).value.to.equal(me.accessKeyId);
        });

        this.browser
            .waitForElementPresent(`${this.elements.modal}__close`)
            .click(`${this.elements.modal}__close`)
            .waitForElementNotPresent(this.elements.modalTitle);
    }

    changeApiCredentials() {
        const me = this;

        this.browser.getValue(this.elements.apiAccessKeyField, function checkValuePresent(result) {
            me.newAccessKeyId = result.value;

            me.browser
                .waitForElementPresent('.sw-button--danger')
                .click('.sw-button--danger')
                .waitForElementPresent(me.elements.apiAccessKeyField)
                .getValue(me.elements.apiAccessKeyField, function checkValueNotPresent(secondResult) {
                    this.assert.notEqual(secondResult, me.accessKeyId);
                })
                .waitForElementVisible('.sw-integration-detail-modal__save-action')
                .click('.sw-integration-detail-modal__save-action')
                .waitForElementNotPresent(me.elements.loader)
                .checkNotification('Integration has been saved successfully.');
        });
    }

    verifyChangedApiCredentials() {
        const me = this;

        this.browser.getValue(this.elements.apiAccessKeyField, function checkValuePresent(result) {
            me.newAccessKeyId = result.value;

            me.browser
                .waitForElementPresent(me.elements.apiAccessKeyField)
                .getValue(me.elements.apiAccessKeyField, function checkValueNotPresent(secondResult) {
                    this.assert.notEqual(secondResult, me.accessKeyId);
                })
                .expect.element(me.elements.apiAccessKeyField).value.to.equal(me.newAccessKeyId);
        });

        this.browser
            .waitForElementPresent(`${this.elements.modal}__close`)
            .click(`${this.elements.modal}__close`)
            .waitForElementNotPresent(`${this.elements.modal}__title`);
    }

    deleteSingleIntegration(integrationName) {
        this.browser
            .clickContextMenuItem(`${this.elements.contextMenu}-item--danger`, this.elements.contextMenuButton, `${this.elements.gridRow}--0`)
            .waitForElementVisible(this.elements.modal)
            .assert.containsText(`${this.elements.modal}__body`, `Are you sure you want to delete this integration? ${integrationName}`)
            .click(`${this.elements.modal} button.sw-button--primary`)
            .waitForElementNotPresent(this.elements.modal)
            .waitForElementNotPresent(this.elements.listColumnName)
            .waitForElementPresent('.sw-empty-state__title')
            .assert.containsText('.sw-empty-state__title', 'No integrations yet');
    }
}

module.exports = (browser) => {
    return new IntegrationPageObject(browser);
};
