import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

describe('Product: Test crud operations', () => {
    beforeEach(() => {
        cy.setLocaleToEnGb()
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@general: should show a growl error message when saving an entity with invalid required fields', { tags: ['ct-admin'] }, () => {
        const page = new ProductPageObject();

        // Add basic data to product
        cy.get('a[href="#/sw/product/create?creationStates=is-physical"]').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Save product
        cy.get(page.elements.productSaveAction).click();

        cy.awaitAndCheckNotification('Please fill in all required fields.');
    });
});
