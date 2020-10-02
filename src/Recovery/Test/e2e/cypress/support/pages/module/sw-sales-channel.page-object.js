/* global cy */
import elements from '../sw-general.page-object';

export default class SalesChannelPageObject {
    constructor() {
        this.elements = {
            ...elements,
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
        cy.get(this.elements.salesChannelNameInput).typeAndCheck(salesChannelName);

        cy.get('.sw-sales-channel-detail__select-navigation-category-id')
            .typeSingleSelectAndCheck('Home', '.sw-sales-channel-detail__select-navigation-category-id');

        cy.get('.sw-sales-channel-detail__select-customer-group')
            .typeSingleSelectAndCheck('Standard customer group', '.sw-sales-channel-detail__select-customer-group');

        cy.get('.sw-sales-channel-detail__select-countries').scrollIntoView();
        cy.get('.sw-sales-channel-detail__select-countries').typeMultiSelectAndCheck('Germany', {
            searchTerm: 'Germany'
        });
        cy.get('.sw-sales-channel-detail__assign-countries')
            .typeSingleSelectAndCheck('Germany', '.sw-sales-channel-detail__assign-countries');

        cy.get('.sw-sales-channel-detail__select-languages').scrollIntoView();
        cy.get('.sw-sales-channel-detail__select-languages').typeMultiSelectAndCheck('English', {
            searchTerm: 'English'
        });
        cy.get('.sw-sales-channel-detail__assign-languages')
            .typeSingleSelectAndCheck('English', '.sw-sales-channel-detail__assign-languages');

        cy.get('.sw-sales-channel-detail__select-payment-methods').scrollIntoView();
        cy.get('.sw-sales-channel-detail__select-payment-methods').typeMultiSelectAndCheck('Invoice', {
            searchTerm: 'Invoice'
        });
        cy.get('.sw-sales-channel-detail__assign-payment-methods')
            .typeSingleSelectAndCheck('Invoice', '.sw-sales-channel-detail__assign-payment-methods');

        cy.get('.sw-sales-channel-detail__select-shipping-methods').scrollIntoView();
        cy.get('.sw-sales-channel-detail__select-shipping-methods').typeMultiSelectAndCheck('Standard', {
            searchTerm: 'Standard'
        });
        cy.get('.sw-sales-channel-detail__assign-shipping-methods')
            .typeSingleSelectAndCheck('Standard', '.sw-sales-channel-detail__assign-shipping-methods');

        cy.get('.sw-sales-channel-detail__select-currencies').scrollIntoView();
        cy.get('.sw-sales-channel-detail__select-currencies').typeMultiSelectAndCheck('Euro', {
            searchTerm: 'Euro'
        });
        cy.get('.sw-sales-channel-detail__assign-currencies')
            .typeSingleSelectAndCheck('Euro', '.sw-sales-channel-detail__assign-currencies');
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
            .contains('Are you sure you want to delete this Sales Channel?');
        cy.get(`${this.elements.modal}__body .sw-sales-channel-detail-base__delete-modal-name`)
            .contains(salesChannelName);

        cy.get(`${this.elements.modal}__footer button${this.elements.dangerButton}`).click();
        cy.get(this.elements.modal).should('not.exist');
    }
}
