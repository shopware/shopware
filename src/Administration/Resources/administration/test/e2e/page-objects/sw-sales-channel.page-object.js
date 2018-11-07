function SalesChannelPageObject(browser) {
    // constructor
    this.browser = browser;
    this.elements = {};
    this.elements.salesChannelNameInput = 'input[name=sw-field--salesChannel-name]';
    this.elements.salesChannelMenuTitle = '.sw-admin-menu__sales-channel-item .collapsible-text';
    this.elements.apiAccessKeyField = 'input[name=sw-field--salesChannel-accessKey]';

    this.accessKeyId = '';
    this.newAccessKeyId = '';
}

SalesChannelPageObject.prototype.createBasicSalesChannel = function createBasicSalesChannel(salesChannelName) {
    this.browser
        .fillField(this.elements.salesChannelNameInput, salesChannelName)
        .fillMultiSelect('.sw-sales-channel-detail__input-payment-method ', 'Direct Debit')
        .fillMultiSelect('.sw-sales-channel-detail__input-shipping-method ', 'Standard')
        .fillMultiSelect('.sw-sales-channel-detail__input-countries ', 'Deutschland')
        .fillMultiSelect('.sw-sales-channel-detail__input-currencies ', 'Euro')
        .fillMultiSelect('.sw-sales-channel-detail__input-languages ', 'Deutsch')
        .fillMultiSelect('.sw-sales-channel-detail__input-catalogues ', 'Default catalogue')
        .waitForElementVisible('.sw-sales-channel-detail__save-action')
        .click('.sw-sales-channel-detail__save-action')
        .waitForElementVisible('.sw-notifications .sw-alert')
        .assert.containsText(
            '.sw-alert .sw-alert__message',
            `Sales channel "${salesChannelName}" has been saved successfully.`
        )
        .click('.sw-alert button.sw-alert__close')
        .waitForElementNotPresent('.sw-notifications .sw-alert');
};

SalesChannelPageObject.prototype.openSalesChannel = function openSalesChannel(salesChannelName) {
    this.browser
        .waitForElementVisible('.sw-admin-menu__sales-channel-item .collapsible-text')
        .assert.containsText('.sw-admin-menu__sales-channel-item .collapsible-text', salesChannelName)
        .waitForElementVisible('.sw-admin-menu__sales-channel-item:first-child')
        .click('.sw-admin-menu__sales-channel-item:first-child')
        .waitForElementVisible('.smart-bar__header')
        .assert.containsText('.smart-bar__header h2', salesChannelName);
};

SalesChannelPageObject.prototype.deleteSingleSalesChannel = function deleteSingleSalesChannel(salesChannelName) {
    this.browser
        .waitForElementPresent('.sw-button--danger')
        .getLocationInView('.sw-button--danger')
        .waitForElementVisible('.sw-button--danger')
        .click('.sw-button--danger')
        .waitForElementVisible('.sw-modal')
        .assert.containsText(
            '.sw-modal__body',
            `Are you sure you want to delete this sales channel? ${salesChannelName}`
        )
        .click('.sw-modal__footer button.sw-button--primary')
        .waitForElementNotPresent('.sw-modal')
        .getValue(this.elements.salesChannelMenuTitle, function checkValueNotPresent(result) {
            this.assert.notEqual(result, salesChannelName);
        });
};

SalesChannelPageObject.prototype.checkClipboard = function f() {
    const me = this;

    this.browser.getValue(me.elements.apiAccessKeyField, function checkValuePresent(result) {
        me.accessKeyId = result.value;

        me.browser
            .waitForElementPresent(me.elements.apiAccessKeyField)
            .getLocationInView(me.elements.apiAccessKeyField)
            .waitForElementVisible('.sw-field__copy-button')
            .click('.sw-field__copy-button')
            .waitForElementVisible('.sw-notifications .sw-alert')
            .assert.containsText('.sw-alert .sw-alert__message', 'Text has been copied to clipboard')
            .click('.sw-alert .sw-alert__close')
            .waitForElementNotPresent('.sw-notifications .sw-alert')
            .getLocationInView(me.elements.salesChannelNameInput)
            .clearValue(me.elements.salesChannelNameInput)
            .setValue(me.elements.salesChannelNameInput, ['', me.browser.Keys.CONTROL, 'v'])
            .expect.element(me.elements.salesChannelNameInput).value.to.equal(me.accessKeyId);
    });
};

SalesChannelPageObject.prototype.changeApiCredentials = function changeApiCredentials(salesChannelName) {
    const me = this;

    this.browser.getValue(this.elements.apiAccessKeyField, function checkValuePresent(result) {
        me.newAccessKeyId = result.value;

        me.browser
            .waitForElementPresent(me.elements.apiAccessKeyField)
            .getLocationInView(me.elements.apiAccessKeyField)
            .waitForElementPresent('.sw-sales-channel-detail-base__button-generate-keys')
            .click('.sw-sales-channel-detail-base__button-generate-keys')
            .getValue(me.elements.apiAccessKeyField, function checkValueNotPresent(secondResult) {
                this.assert.notEqual(secondResult, me.accessKeyId);
            })
            .fillField(me.elements.salesChannelNameInput, salesChannelName)
            .waitForElementVisible('.sw-sales-channel-detail__save-action')
            .click('.sw-sales-channel-detail__save-action')
            .waitForElementVisible('.sw-notification__alert')
            .assert.containsText(
                '.sw-alert .sw-alert__message',
                `Sales channel "${salesChannelName}" has been saved successfully.`
            );
    });
};

SalesChannelPageObject.prototype.verifyChangedApiCredentials = function verifyChangedApiCredentials() {
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
};

module.exports = (browser) => {
    return new SalesChannelPageObject(browser);
};
