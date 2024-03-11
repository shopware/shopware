/// <reference types="Cypress" />
/**
 * @package inventory
 */
describe('Dynamic Product Groups in categories', () => {
    beforeEach(() => {
        cy.createProductFixture().then(() => {
            cy.createProductFixture({
                name: 'Custom Product-1',
                productNumber: 'CP-1111',
            });
        }).then(() => {
            cy.createProductFixture({
                name: 'Custom Product-2',
                productNumber: 'CP-1112',
            });
        });
    });

    it('@package: should create a dynamic product groups and assign it to a category and check at the storefront', { tags: ['pa-services-settings', 'quarantined'] }, () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/category`,
            method: 'POST',
        }).as('getCategory');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/product-stream`,
            method: 'POST',
        }).as('saveProductStream');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/user-config`,
            method: 'POST',
        }).as('getUserConfig');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveData');

        // Go to dynamic product pages
        cy.visit(`${Cypress.env('admin')}#/sw/product/stream/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('h2', 'Dynamische productgroepen').should('be.visible');
        cy.get('.sw-product-stream-list__create-action').click();
        cy.get('#sw-field--productStream-name').clearTypeAndCheck('Dynamic Products');
        cy.get('.sw-product-stream-filter__container').then((conditionElement) => {
            cy.get('.sw-product-stream-field-select .sw-single-select__selection', {withinSubject: conditionElement})
                .then((conditionTypeSelect) => {
                    cy.wrap(conditionTypeSelect).click();
                    cy.get('.sw-select-result-list-popover-wrapper').should('be.visible');
                    cy.get('.sw-select-result-list-popover-wrapper').contains('Productnummer')
                        .click();
                });
            cy.get('.sw-product-stream-value__operator-select .sw-single-select__selection')
                .then((conditionOperatorSelect) => {
                    cy.wrap(conditionOperatorSelect).click();
                    cy.get('.sw-select-result-list-popover-wrapper').should('be.visible');
                    cy.get('.sw-select-result-list-popover-wrapper').contains('Is niet gelijk aan').click();
                });
            cy.get('#sw-field--stringValue').type('RS-333');
            cy.get('.sw-loader').should('not.exist');
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-button-process__content').contains('Opslaan').click();
            cy.wait('@saveProductStream').its('response.statusCode').should('equal', 204);
        });

        // Define the product under the home category
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.tree-link > .sw-tree-item__label').click();
        cy.get('.sw-tabs-item[title="Producten"]').click();
        cy.get('.sw-category-detail-products__product-assignment-type-select .sw-single-select__selection')
            .type('Dynamische productgroep');
        cy.get('.sw-highlight-text__highlight').click( {force: true} );
        cy.get('[label] .sw-entity-single-select__selection').type('Dynamic Products');
        cy.get('.sw-highlight-text__highlight').click();
        cy.contains('Custom Product-1').should('exist');
        cy.contains('Custom Product-2').should('exist');
        cy.contains('Product name').should('not.exist');
        cy.get('.sw-button-process__content').contains('Opslaan').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');
        cy.wait('@getCategory').its('response.statusCode').should('equal', 200);

        // Add both products to the sales channel
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-data-grid__select-all .sw-field__checkbox input').click();
        cy.get('.sw-data-grid__bulk-selected.bulk-link').should('exist');
        cy.get('.link.link-primary').click();
        cy.wait('@getUserConfig').its('response.statusCode').should('equal', 200);
        cy.get('.sw-product-bulk-edit-modal').should('exist');
        cy.get('.sw-modal__footer .sw-button--primary').click();
        cy.contains('.smart-bar__header', 'Bulk bewerking: 3 producten');
        cy.get('.sw-bulk-edit-change-field-visibilities [type="checkbox"]').click();
        cy.get('div[name="visibilities"]').typeMultiSelectAndCheck(Cypress.env('storefrontName'));

        // Save and apply changes
        cy.get('.sw-bulk-edit-product__save-action').click();
        cy.get('.sw-bulk-edit-save-modal').should('exist');
        cy.contains('.footer-right .sw-button--primary', 'Wijzigingen toepassen');
        cy.get('.footer-right .sw-button--primary').click();
        cy.get('.sw-bulk-edit-save-modal').should('exist');
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);
        cy.get('.sw-bulk-edit-save-modal').should('exist');
        cy.contains('.sw-bulk-edit-save-modal', 'Bulk edit - Succes');
        cy.contains('.footer-right .sw-button--primary', 'Sluiten');
        cy.get('.footer-right .sw-button--primary').click();
        cy.get('.sw-bulk-edit-save-modal').should('not.exist');

        // Verify dynamic products at the storefront
        cy.visit('/');
        cy.contains('Custom Product-1').should('exist');
        cy.contains('Custom Product-2').should('exist');
        cy.contains('Product name').should('not.exist');
    });
});
