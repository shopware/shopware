// / <reference types="Cypress" />

describe('Documents: Test crud operations', () => {
    beforeEach(() => {
        cy.setToInitialState().then(() => {
            cy.loginViaApi();
        }).then(() => {
            return cy.createProductFixture();
        })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/document/index`);
            });
    });

    it('@settings: Create invoice document', () => {
        cy.window().then(() => {
            cy.get('.sw-settings-document-list__add-document').click();

            // fill data into general field.
            cy.get('#sw-field--documentConfig-name').type('Invoice Name');
            cy.get('#sw-field--documentConfig-filenamePrefix').type('invoice_');
            cy.get('#sw-field--documentConfig-filenameSuffix').type('no');
            cy.get('#itemsPerPage').type('10');

            // select document type by Invoice type.
            cy.get('.sw-settings-document-detail__select-type')
                .scrollIntoView()
                .click();
            cy.get('.sw-select-result-list__item-list li .sw-highlight-text')
                .contains('Invoice')
                .click();

            // scroll the checkbox for the option "Display header" into view
            cy.get('.sw-field--checkbox__content:nth(8)').scrollIntoView()
            
                            
            // check that the correct element is selected and check the checkbox
            cy.get('.sw-field--checkbox__content:nth(8)').contains('Display "intra-Community delivery" label')
            cy.get('.sw-field--checkbox__content:nth(8) input[type=checkbox]').check()

            // scroll the country select into view
            cy.get('.sw-settings-document-detail__field_delivery_countries').scrollIntoView();

            // select country
            cy.get('.sw-settings-document-detail__field_delivery_countries').typeMultiSelectAndCheck('Germany', {
                searchTerm: 'Germany'
            });

            // do saving action.
            cy.get('.sw-settings-document-detail__save-action').click();

            // back to documents list and check exists invoice which was created successfully.
            cy.get('.smart-bar__back-btn').click();
            cy.get('.sw-settings-document-list-grid .sw-grid-row .sw-grid__cell-content a')
                .contains('Invoice Name')
                .should('be.visible');
            cy.get('.sw-settings-document-list-grid .sw-grid-row .sw-document-list__column-type .sw-grid__cell-content')
                .contains('Invoice')
                .should('be.visible');
        });
    });
});
