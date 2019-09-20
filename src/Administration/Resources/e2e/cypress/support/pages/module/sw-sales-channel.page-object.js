const GeneralPageObject = require('../sw-general.page-object');

export default class SalesChannelPageObject extends GeneralPageObject {
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
    }

    fillInBasicSalesChannelData(salesChannelName) {
        cy.get(this.elements.salesChannelNameInput).type(salesChannelName);

        cy.get('.sw-sales-channel-detail__select-payment-method').typeMultiSelectAndCheck('Invoice');
        cy.get('.sw-sales-channel-detail__assign-payment-method').typeSingleSelectAndCheck('Invoice');

        cy.get('.sw-sales-channel-detail__select-shipping-method').typeMultiSelectAndCheck('Standard');
        cy.get('.sw-sales-channel-detail__assign-shipping-method').typeSingleSelectAndCheck('Standard');

        cy.get('.sw-sales-channel-detail__select-countries').typeMultiSelectAndCheck('Germany');
        cy.get('.sw-sales-channel-detail__assign-countries').typeSingleSelectAndCheck('Germany');

        cy.get('.sw-sales-channel-detail__select-currencies').typeMultiSelectAndCheck('Euro');
        cy.get('.sw-sales-channel-detail__assign-currencies').typeSingleSelectAndCheck('Euro');

        cy.get('.sw-sales-channel-detail__select-languages').typeMultiSelectAndCheck('English');
        cy.get('.sw-sales-channel-detail__assign-languages').typeSingleSelectAndCheck('English');

        cy.get('.sw-sales-channel-detail__select-customer-group').typeSingleSelectAndCheck('Standard customer group');

        cy.get('.sw-sales-channel-detail__select-navigation-category-id').typeSingleSelectAndCheck('Catalogue #1');
    }

    openSalesChannel(salesChannelName, position = 0) {
        cy.get(`${this.elements.salesChannelMenuName}--${position} > a`).contains(salesChannelName);
        cy.get(`${this.elements.salesChannelMenuName}--${position}`).click();
        cy.get(this.elements.smartBarHeader).contains(salesChannelName);
    }

    deleteSingleSalesChannel(salesChannelName) {
        cy.get(this.elements.dangerButton).scrollIntoView();
        cy.get(this.elements.dangerButton).click();
        cy.get(this.elements.modal).should('be.visible');
        cy.get(`${this.elements.modal}__body .sw-sales-channel-detail-base__delete-modal-confirm-text`)
            .contains('Are you sure you want to delete this sales channel?');
        cy.get(`${this.elements.modal}__body .sw-sales-channel-detail-base__delete-modal-name`)
            .contains(salesChannelName);

        cy.get(`${this.elements.modal}__footer button${this.elements.dangerButton}`).click();
        cy.get(this.elements.modal).should('not.exist');
    }
}
