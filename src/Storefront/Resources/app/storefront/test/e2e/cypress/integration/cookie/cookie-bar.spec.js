describe('Test if the cookie bar works correctly', () => {
    beforeEach(() => {
        cy.setToInitialState().then(() => {
            cy.visit('/');
        });
    });

    it('Should accept all cookies when user clicks on accept all in cookie bar', () => {
        // should wait of cookieOffcanvas
        cy.server();
        cy.route({
            url: '/cookie/offcanvas',
            method: 'get'
        }).as('cookieOffcanvas');

        cy.get('.cms-element-product-listing').should('be.visible');
        cy.get('.cookie-permission-container').should('be.visible');

        cy.get('.js-cookie-accept-all-button').should('be.visible')
            .click();

        cy.wait('@cookieOffcanvas').then(() => {
            cy.get('.offcanvas-cookie').should('not.be.visible');

            cy.reload(true);

            cy.get('.cms-element-product-listing').should('be.visible');
            cy.get('.cookie-permission-container').should('not.be.visible');
        });
    });
});
