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
        cy.window().then((win) => {
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

            // check exists additional note delivery field.
            cy.get('.sw-settings-document-detail__field_additional_note_delivery input[type="checkbox"]')
                .scrollIntoView()
                .should('be.visible');

            // select additional note delivery checkbox.
            cy.get('.sw-settings-document-detail__field_additional_note_delivery input[type="checkbox"]')
                .scrollIntoView()
                .click();

            // check exists select multi countries field.
            cy.get('.sw-settings-document-detail__field_delivery_countries').should('be.visible');

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
