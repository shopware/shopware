import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Product: Test crud operations', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it('@general: should show a growl error message when saving an entity with invalid required fields', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product`,
            method: 'post'
        }).as('saveData');
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/calculate-price`,
            method: 'post'
        }).as('calculatePrice');

        // Add basic data to product
        cy.get('a[href="#/sw/product/create"]').click();

        // Save product
        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 400);
        });

        cy.awaitAndCheckNotification('This value should not be blank.');
    });
});
