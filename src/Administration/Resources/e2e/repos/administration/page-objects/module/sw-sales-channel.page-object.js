const GeneralPageObject = require('../sw-general.page-object');

class SalesChannelPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);

        this.elements = {
            ...this.elements,
            ...{
                salesChannelMenuName: '.sw-admin-menu__sales-channel-item',
                salesChannelModal: '.sw-sales-channel-modal',
                salesChannelNameInput: 'input[name=sw-field--salesChannel-name]',
                salesChannelMenuTitle: '.sw-admin-menu__sales-channel-item .collapsible-text',
                apiAccessKeyField: 'input[name=sw-field--salesChannel-accessKey]',
                salesChannelSaveAction: '.sw-sales-channel-detail__save-action'
            }
        };

        this.accessKeyId = '';
        this.newAccessKeyId = '';
    }

    createBasicSalesChannel(salesChannelName) {
        this.browser
            .fillField(this.elements.salesChannelNameInput, salesChannelName)
            .fillSwSelectComponent(
                '.sw-sales-channel-detail__select-payment-method',
                {
                    value: 'Invoice',
                    isMulti: true,
                    searchTerm: 'Invoice'
                }
            )
            .fillSwSelectComponent(
                '.sw-sales-channel-detail__select-shipping-method',
                {
                    value: 'Standard',
                    isMulti: true,
                    searchTerm: 'Standard'
                }
            )
            .fillSwSelectComponent(
                '.sw-sales-channel-detail__select-countries',
                {
                    value: 'Germany',
                    isMulti: true,
                    searchTerm: 'Germany'
                }
            )
            .fillSwSelectComponent(
                '.sw-sales-channel-detail__select-currencies',
                {
                    value: 'Euro',
                    isMulti: true,
                    searchTerm: 'Euro'
                }
            )
            .fillSwSelectComponent(
                '.sw-sales-channel-detail__select-languages',
                {
                    value: 'Deutsch',
                    isMulti: true,
                    searchTerm: 'Deutsch'
                }
            )
            .fillSwSelectComponent(
                '.sw-sales-channel-detail__select-customer-group',
                {
                    value: 'Standard customer group',
                    isMulti: false,
                    searchTerm: 'Standard customer group'
                }
            )
            .click(this.elements.salesChannelSaveAction)
            .checkNotification(`Sales channel "${salesChannelName}" has been saved successfully.`);
    }

    openSalesChannel(salesChannelName, position = 0) {
        this.browser
            .expect.element(`${this.elements.salesChannelMenuName}--${position} > a`).to.have.text.that.equals(salesChannelName);

        this.browser
            .click(`${this.elements.salesChannelMenuName}--${position}`)
            .expect.element(this.elements.smartBarHeader).to.have.text.that.contains(salesChannelName);
    }

    deleteSingleSalesChannel(salesChannelName, position = 0) {
        this.browser
            .getLocationInView(this.elements.dangerButton)
            .click(this.elements.dangerButton)
            .expect.element(`${this.elements.modal}__body`).to.have.text.that.equals(`Are you sure you want to delete this sales channel? ${salesChannelName}`);

        this.browser
            .click(`${this.elements.modal}__footer button${this.elements.primaryButton}`)
            .waitForElementNotPresent(this.elements.modal)
            .expect.element(`${this.elements.salesChannelMenuName}--${position} > a`).to.have.text.that.not.contains(salesChannelName);
    }

    checkClipboard() {
        const me = this;

        this.browser.getValue(me.elements.apiAccessKeyField, (result) => {
            me.accessKeyId = result.value;

            me.browser
                .waitForElementPresent(me.elements.apiAccessKeyField)
                .getLocationInView(me.elements.apiAccessKeyField)
                .waitForElementVisible('.sw-field__copy-button:nth-of-type(1)')
                .click('.sw-field__copy-button:nth-of-type(1)')
                .checkNotification('Text has been copied to clipboard.')
                .getLocationInView(me.elements.salesChannelNameInput)
                .clearValue(me.elements.salesChannelNameInput)
                .setValue(me.elements.salesChannelNameInput, ['', me.browser.Keys.CONTROL, 'v'])
                .expect.element(me.elements.salesChannelNameInput).value.that.equals(me.accessKeyId);
        });
    }

    changeApiCredentials(salesChannelName) {
        const me = this;

        this.browser.getValue(this.elements.apiAccessKeyField, (result) => {
            me.newAccessKeyId = result.value;

            me.browser
                .getLocationInView(me.elements.apiAccessKeyField)
                .click('.sw-sales-channel-detail-base__button-generate-keys')
                .expect.element(me.elements.apiAccessKeyField).value.that.equals(me.newAccessKeyId);

            me.browser
                .fillField(me.elements.salesChannelNameInput, salesChannelName, true, 'input')
                .click('.sw-sales-channel-detail__save-action')
                .checkNotification(`Sales channel "${salesChannelName}" has been saved successfully.`);
        });
    }

    verifyChangedApiCredentials() {
        const me = this;

        this.browser.getValue(this.elements.apiAccessKeyField, (result) => {
            me.newAccessKeyId = result.value;

            me.browser
                .waitForElementPresent(me.elements.apiAccessKeyField)
                .expect.element(me.elements.apiAccessKeyField).value.that.equals(me.newAccessKeyId);
        });
    }
}

module.exports = (browser) => {
    return new SalesChannelPageObject(browser);
};
