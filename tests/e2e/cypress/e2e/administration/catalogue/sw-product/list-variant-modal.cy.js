// / <reference types="Cypress" />

describe('Product: Test variants', () => {
    beforeEach(() => {
        cy.createPropertyFixture({
            name: 'Size',
            options: [{ name: 'S' }, { name: 'M' }, { name: 'L' }],
        }).then(() => {
            return cy.createProductFixture({
                name: 'Parent Product',
            });
        })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');

                cy.get('.sw-data-grid__cell--name')
                    .click();

                cy.get('.sw-product-detail__tab-variants').click();

                cy.get('.sw-product-detail-variants__generated-variants-empty-state .sw-button')
                    .click();

                cy.get('.sw-grid__row--0 .group_grid__column-name')
                    .click();

                cy.get('.sw-property-search__tree-selection__option_grid .sw-grid__row--0 > :nth-child(2)').click();

                cy.get('.sw-product-variant-generation__next-action').click();

                cy.get('.sw-product-modal-variant-generation__upload_files .sw-button--primary').click();

                cy.get('.sw-modal').should('not.exist');

                cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');

                // open variant modal
                cy.get('.sw-data-grid :nth-child(2) > .sw-button')
                    .should('be.visible')
                    .click();

                cy.get('.sw-modal')
                    .should('be.visible');
            });
    });

    it('@catalogue: should edit variants in modal', { tags: ['pa-inventory'] }, () => {
        cy.intercept({
            method: 'PATCH',
            url: `${Cypress.env('apiPath')}/product/*`,
        }).as('saveChanges');

        cy.get('.sw-modal .sw-data-grid__row--0')
            .dblclick();

        cy.get('#sw-field--item-name')
            .typeAndCheck('Random variant');

        cy.get('#sw-field--item-stock')
            .clearTypeAndCheck('12');

        cy.get('.is--inline-edit input[type="checkbox"]')
            .uncheck();

        cy.get('.sw-data-grid__inline-edit-save')
            .click();

        cy.awaitAndCheckNotification('Succesfully saved "Parent Product (Size: L)".');

        cy.wait('@saveChanges').its('response.statusCode').should('equal', 204);

        cy.get('.sw-modal__footer > .sw-button')
            .should('be.visible')
            .click();
    });

    it('@catalogue @base: delete variants in modal', { tags: ['quarantined', 'pa-inventory'] }, () => {
        cy.intercept({
            method: 'POST',
            url: 'api/_action/sync',
        }).as('deleteData');

        cy.get('.sw-modal .sw-data-grid__actions-menu')
            .click();

        cy.get('.sw-tooltip--wrapper > .sw-context-menu-item')
            .click();

        // check if delete modal is visible
        cy.get('.sw-product-variant-modal__delete-modal')
            .should('be.visible');

        // check modal description
        cy.contains('.sw-product-variant-modal__delete-modal  .sw-modal__body p',
            'Are your sure you want to delete the variant "Parent Product (Size: L)"?');

        cy.get('.sw-button-process')
            .should('be.visible')
            .click();

        cy.wait('@deleteData').its('response.statusCode').should('equal', 200);

        // check delete modal has been closed
        cy.get('.sw-product-variant-modal__delete-modal')
            .should('not.be.visible');

        cy.awaitAndCheckNotification('Successfully deleted "Parent Product".');

        // check if empty state exists
        cy.get('.sw-empty-state')
            .should('be.visible');

        cy.contains('.sw-empty-state__description-content', 'No variants were found.');
    });
});
