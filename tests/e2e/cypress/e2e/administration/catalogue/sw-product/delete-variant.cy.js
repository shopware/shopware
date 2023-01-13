import variantProduct from '../../../../fixtures/variant-product';

// / <reference types="Cypress" />

describe('Product: Test variants', () => {
    beforeEach(() => {
        cy.createProductFixture(variantProduct)
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');

                cy.get('.sw-data-grid__cell--name')
                    .click();

                cy.get('.sw-product-detail__tab-variants').click();

            });
    });

    it('@catalogue: delete variants in modal', { tags: ['pa-inventory'] }, () => {
        cy.intercept({
            method: 'POST',
            url: 'api/_action/sync',
        }).as('deleteData');

        cy.get('.sw-data-grid__select-all .sw-field__checkbox input').click();

        cy.get('.sw-product-variants-overview__bulk-delete-action').should('exist');
        cy.get('.sw-product-variants-overview__bulk-delete-action').click();

        // check if delete modal is visible
        cy.get('.sw-product-variants-overview__delete-modal')
            .should('be.visible');

        // check modal description
        cy.contains('.sw-product-variants-overview__delete-modal  .sw-product-variants-overview__modal--confirm-delete-text',
            'Do you really want to delete these variants?');

        cy.get('.sw-product-variants-overview__delete-modal .sw-modal__footer .sw-button--danger')
            .should('be.visible')
            .click();

        cy.wait('@deleteData').its('response.statusCode').should('equal', 200);

        // check delete modal has been closed
        cy.get('.sw-product-variants-overview__delete-modal').should('not.exist');

        cy.awaitAndCheckNotification('Variant has been deleted.');
    });
});
