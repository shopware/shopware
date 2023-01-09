import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

describe('Product: Test crud operations', () => {
    beforeEach(() => {
        cy.createProductFixture().then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@general: should show a growl error message when saving an entity with invalid required fields', { tags: ['ct-admin'] }, () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveData');
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/calculate-price`,
            method: 'POST',
        }).as('calculatePrice');

        // Add basic data to product
        cy.get('a[href="#/sw/product/create"]').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Save product
        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 400);

        cy.awaitAndCheckNotification('This value should not be blank.');
    });
});
