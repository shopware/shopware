import PageObject from "../../support/pages/sw-general.page-object";

describe('Administration: Check module navigation', function () {
    it('check product module', function () {
        cy.visit(Cypress.env('admin'));
        cy.get('.sw-login').should('be.visible')
    });
});
