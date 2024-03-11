/* global cy */
import elements from '../sw-general.page-object';

export default class SalesChannelPageObject {
    constructor() {
        this.elements = {
            ...elements,
            ...{
                salesChannelMenuName: '.sw-admin-menu__sales-channel-item',
                salesChannelModal: '.sw-sales-channel-modal',
                salesChannelNameInput: '.sw-field--salesChannel-name input',
                salesChannelMenuTitle: '.sw-admin-menu__sales-channel-item .collapsible-text',
                apiAccessKeyField: 'input[name=sw-field--salesChannel-accessKey]',
                salesChannelSaveAction: '.sw-sales-channel-detail__save-action'
            }
        };
    }

    fillInBasicSalesChannelData(salesChannelName) {
        cy.get(this.elements.salesChannelNameInput).typeAndCheck(salesChannelName);

        cy.get('.sw-sales-channel-detail__select-navigation-category-id .sw-block-field__block .sw-category-tree__input-field').click();
        cy.get('.sw-category-tree-field__results_popover .sw-tree__content')
            .contains('.sw-tree-item__element', 'Home')
            .find('.sw-field__checkbox input')
            .click({force: true});

        // Closes the category overlay again
        cy.get(this.elements.salesChannelNameInput).click();

        cy.get('.sw-sales-channel-detail__select-customer-group')
            .typeSingleSelectAndCheck('Standard customer group', '.sw-sales-channel-detail__select-customer-group');

        cy.get('.sw-sales-channel-detail__select-countries').scrollIntoView();
        cy.get('.sw-sales-channel-detail__select-countries').typeMultiSelectAndCheck('Germany', {
            searchTerm: 'Germany'
        });
        cy.contains('.sw-sales-channel-detail__assign-countries', 'Germany');

        cy.get('.sw-sales-channel-detail__select-languages').scrollIntoView();
        cy.get('.sw-sales-channel-detail__select-languages').typeMultiSelectAndCheck('English', {
            searchTerm: 'English'
        });
        cy.contains('.sw-sales-channel-detail__assign-languages', 'English');

        cy.get('.sw-sales-channel-detail__select-payment-methods').scrollIntoView();
        cy.get('.sw-sales-channel-detail__select-payment-methods').typeMultiSelectAndCheck('Invoice', {
            searchTerm: 'Invoice'
        });
        cy.get('.sw-sales-channel-detail__assign-payment-methods').should('contain', 'Invoice');

        cy.get('.sw-sales-channel-detail__select-shipping-methods').scrollIntoView();
        cy.get('.sw-sales-channel-detail__select-shipping-methods').typeMultiSelectAndCheck('Standard', {
            searchTerm: 'Standard'
        });
        cy.contains('.sw-sales-channel-detail__assign-shipping-methods', 'Standard');

        cy.get('.sw-sales-channel-detail__select-currencies').scrollIntoView();
        cy.get('.sw-sales-channel-detail__select-currencies').typeMultiSelectAndCheck('Euro', {
            searchTerm: 'Euro'
        });
        cy.contains('.sw-sales-channel-detail__assign-currencies', 'Euro');
    }

    openSalesChannel(salesChannelName, position = 0) {
        cy.contains(`${this.elements.salesChannelMenuName}--${position} > a`, salesChannelName);
        cy.get(`${this.elements.salesChannelMenuName}--${position}`).click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains(this.elements.smartBarHeader, salesChannelName);
    }

    deleteSingleSalesChannel(salesChannelName) {
        cy.get(this.elements.dangerButton).scrollIntoView();
        cy.get(this.elements.dangerButton).click();
        cy.get(this.elements.modal).should('be.visible');
        cy.contains(`${this.elements.modal}__body .sw-sales-channel-detail-base__delete-modal-confirm-text`,
            'Are you sure you want to delete this Sales Channel?');
        cy.contains(`${this.elements.modal}__body .sw-sales-channel-detail-base__delete-modal-name`,
            salesChannelName);

        cy.get(`${this.elements.modal}__footer button${this.elements.dangerButton}`).click();
        cy.get(this.elements.modal).should('not.exist');
    }

    addExampleDomain(clickAddButton) {
        clickAddButton = clickAddButton === undefined ? true : clickAddButton;

        cy.contains('button', 'Add domain').click();
        cy.contains('.sw-modal__title', 'Create domain');

        cy.get('.sw-url-input-field__input').type('example.org');

        cy.get('.sw-sales-channel-detail-domains__domain-language-select').find('.sw-single-select__selection').click();
        cy.contains('.sw-select-result', 'Deutsch').click();

        cy.get('.sw-sales-channel-detail-domains__domain-currency-select').find('.sw-single-select__selection').click();
        cy.contains('.sw-select-result', 'Euro').click();

        cy.contains('.sw-entity-single-select', 'Snippet').find('.sw-entity-single-select__selection').click();
        cy.contains('.sw-select-result', 'BASE de-DE').click();

        if (clickAddButton) {
            cy.contains('.sw-button--primary', 'Add domain').click();
        }
    }
}
