const GeneralPageObject = require('../sw-general.page-object');

class SalesChannelPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);

        this.elements = Object.assign(this.elements, {
            salesChannelMenuName: '.sw-admin-menu__sales-channel-item',
            salesChannelModal: '.sw-sales-channel-modal',
            salesChannelNameInput: 'input[name=sw-field--salesChannel-name]',
            salesChannelMenuTitle: '.sw-admin-menu__sales-channel-item .collapsible-text',
            apiAccessKeyField: 'input[name=sw-field--salesChannel-accessKey]',
            salesChannelSaveAction: '.sw-sales-channel-detail__save-action'
        });

        this.accessKeyId = '';
        this.newAccessKeyId = '';
    }

    createBasicSalesChannel(salesChannelName) {
        this.browser
            .fillField(this.elements.salesChannelNameInput, salesChannelName)
            .fillSwSelectComponent(
                '.sw-sales-channel-detail__select-payment-method ',
                {
                    value: 'Direct Debit',
                    isMulti: true,
                    searchTerm: 'Direct Debit'
                }
            )
            .fillSwSelectComponent(
                '.sw-sales-channel-detail__select-shipping-method ',
                {
                    value: 'Standard',
                    isMulti: true,
                    searchTerm: 'Standard'
                }
            )
            .fillSwSelectComponent(
                '.sw-sales-channel-detail__select-countries ',
                {
                    value: 'Germany',
                    isMulti: true,
                    searchTerm: 'Germany'
                }
            )
            .fillSwSelectComponent(
                '.sw-sales-channel-detail__select-currencies ',
                {
                    value: 'Euro',
                    isMulti: true,
                    searchTerm: 'Euro'
                }
            )
            .fillSwSelectComponent(
                '.sw-sales-channel-detail__select-languages ',
                {
                    value: 'Deutsch',
                    isMulti: true,
                    searchTerm: 'Deutsch'
                }
            )
            .fillSwSelectComponent(
                '.sw-sales-channel-detail__select-catalogues ',
                {
                    value: 'Default catalogue',
                    isMulti: true,
                    searchTerm: 'Default catalogue'
                }
            )
            .waitForElementVisible(this.elements.salesChannelSaveAction)
            .click(this.elements.salesChannelSaveAction)
            .checkNotification(`Sales channel "${salesChannelName}" has been saved successfully.`);
    }

    openSalesChannel(salesChannelName, position = 0) {
        this.browser
            .waitForElementVisible(`${this.elements.salesChannelMenuName}--${position} .collapsible-text`)
            .assert.containsText(`${this.elements.salesChannelMenuName}--${position} .collapsible-text`, salesChannelName)
            .waitForElementVisible(`${this.elements.salesChannelMenuName}--${position}`)
            .click(`${this.elements.salesChannelMenuName}--${position}`)
            .waitForElementVisible(this.elements.smartBarHeader)
            .assert.containsText(`${this.elements.smartBarHeader} h2`, salesChannelName);
    }

    deleteSingleSalesChannel(salesChannelName) {
        this.browser
            .waitForElementPresent(this.elements.dangerButton)
            .getLocationInView(this.elements.dangerButton)
            .waitForElementVisible(this.elements.dangerButton)
            .click(this.elements.dangerButton)
            .waitForElementVisible(this.elements.modal)
            .assert.containsText(
                `${this.elements.modal}__body`,
                `Are you sure you want to delete this sales channel? ${salesChannelName}`
            )
            .click(`${this.elements.modal}__footer button${this.elements.primaryButton}`)
            .waitForElementNotPresent(this.elements.modal)
            .getValue(this.elements.salesChannelMenuTitle, function checkValueNotPresent(result) {
                this.assert.notEqual(result, salesChannelName);
            });
    }

    checkClipboard() {
        const me = this;

        this.browser.getValue(me.elements.apiAccessKeyField, function checkValuePresent(result) {
            me.accessKeyId = result.value;

            me.browser
                .waitForElementPresent(me.elements.apiAccessKeyField)
                .getLocationInView(me.elements.apiAccessKeyField)
                .waitForElementVisible('.sw-field__copy-button')
                .click('.sw-field__copy-button')
                .checkNotification('Text has been copied to clipboard')
                .getLocationInView(me.elements.salesChannelNameInput)
                .clearValue(me.elements.salesChannelNameInput)
                .setValue(me.elements.salesChannelNameInput, ['', me.browser.Keys.CONTROL, 'v'])
                .expect.element(me.elements.salesChannelNameInput).value.to.equal(me.accessKeyId);
        });
    }

    changeApiCredentials(salesChannelName) {
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
                .fillField(me.elements.salesChannelNameInput, salesChannelName, 'input', true)
                .waitForElementVisible('.sw-sales-channel-detail__save-action')
                .click('.sw-sales-channel-detail__save-action')
                .checkNotification(`Sales channel "${salesChannelName}" has been saved successfully.`);
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
    }
}

module.exports = (browser) => {
    return new SalesChannelPageObject(browser);
};
