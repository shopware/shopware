class IntegrationPageObject {
    constructor(browser) {
        this.browser = browser;
        this.elements = {};
        this.elements.integrationName = 'input[name=sw-field--currentIntegration-label]';
        this.elements.apiAccessKeyField = 'input[name=sw-field--currentIntegration-accessKey]';

        this.accessKeyId = '';
        this.newAccessKeyId = '';
    }

    checkClipboard() {
        const me = this;

        this.browser.getValue('input[name=sw-field--currentIntegration-accessKey]', function checkValuePresent(result) {
            me.accessKeyId = result.value;

            me.browser
                .waitForElementVisible('.sw-field__copy-button')
                .click('.sw-field__copy-button')
                .checkNotification('Text has been copied to clipboard')
                .clearValue(me.elements.integrationName)
                .setValue(me.elements.integrationName, ['', me.browser.Keys.CONTROL, 'v'])
                .expect.element(me.elements.integrationName).value.to.equal(me.accessKeyId);
        });

        this.browser
            .waitForElementPresent('.sw-modal__close')
            .click('.sw-modal__close')
            .waitForElementNotPresent('.sw-modal__title');
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
                .checkNotification('Integration has been saved successfully');
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
            .waitForElementPresent('.sw-modal__close')
            .click('.sw-modal__close')
            .waitForElementNotPresent('.sw-modal__title');
    }

    deleteSingleIntegration(integrationName) {
        this.browser
            .clickContextMenuItem('.sw-context-menu-item--danger', '.sw-context-button__button','.sw-grid-row:first-child')
            .waitForElementVisible('.sw-modal')
            .assert.containsText('.sw-modal__body', `Are you sure you want to delete this integration? ${integrationName}`)
            .click('.sw-modal__footer button.sw-button--primary')
            .waitForElementNotPresent('.sw-integration-list__column-integration-name')
            .waitForElementNotPresent('.sw-modal')
            .waitForElementPresent('.sw-empty-state__title')
            .assert.containsText('.sw-empty-state__title', 'No integrations yet');
    }
}

module.exports = (browser) => {
    return new IntegrationPageObject(browser);
};
