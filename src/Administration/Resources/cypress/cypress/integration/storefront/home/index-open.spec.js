describe('Home: Open home page', function () {

    it('open index as home page', function () {
        cy.visit('/');
        cy.get('.logo-main-img').should('be.visible');
    });
});
