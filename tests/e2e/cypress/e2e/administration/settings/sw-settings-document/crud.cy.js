// / <reference types="Cypress" />

describe('Documents: Test crud operations', () => {
    beforeEach(() => {
        cy.createProductFixture()
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/document/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@settings: Create invoice document', { tags: ['pa-customers-orders'] }, () => {
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
            cy.contains('.sw-select-result-list__item-list li .sw-highlight-text', 'Invoice')
                .click();

            // scroll the checkbox for the option "Display header" into view
            cy.get('.sw-field--checkbox__content:nth(8)').scrollIntoView();

            // check that the correct element is selected and check the checkbox
            cy.contains('.sw-field--checkbox__content:nth(8)', 'Display "intra-Community delivery" label');
            cy.get('.sw-field--checkbox__content:nth(8) input[type=checkbox]').check();

            // scroll the country select into view
            cy.get('.sw-settings-document-detail__field_delivery_countries').scrollIntoView();

            // select country
            cy.get('.sw-settings-document-detail__field_delivery_countries').typeMultiSelectAndCheck('Germany', {
                searchTerm: 'Germany',
            });

            // do saving action.
            cy.get('.sw-settings-document-detail__save-action').click();

            // back to documents list and check exists invoice which was created successfully.
            cy.get('.smart-bar__back-btn').click();
            cy.contains('.sw-settings-document-list-grid .sw-grid-row .sw-grid__cell-content a', 'Invoice Name')
                .should('be.visible');
            cy.contains('.sw-settings-document-list-grid .sw-grid-row .sw-document-list__column-type .sw-grid__cell-content', 'Invoice')
                .should('be.visible');
        });
    });
});
