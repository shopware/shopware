
/**
 * Types in the global search field and verify search terms in url
 * @memberOf Cypress.Chainable#
 * @name typeAndCheckSearchField
 * @function
 * @param {String} value - The value to type
 */
Cypress.Commands.add('typeAndCheckSearchField', {
    prevSubject: 'element'
}, (subject, value) => {

    // Request we want to wait for later
    cy.server();
    cy.route({
        url: `${Cypress.env('apiPath')}/search/**`,
        method: 'post'
    }).as('searchResultCall');

    cy.wrap(subject).type(value).should('have.value', value);

    cy.wait('@searchResultCall').then((xhr) => {
        expect(xhr).to.have.property('status', 200);

        cy.url().should('include', encodeURI(value));
    });
});

/**
 * Cleans up any previous state by restoring database and clearing caches
 * @memberOf Cypress.Chainable#
 * @name openInitialPage
 * @function
 */
Cypress.Commands.add('openInitialPage', (url) => {
    // Request we want to wait for later
    cy.server();
    cy.route(`${Cypress.env('apiPath')}/_info/me`).as('meCall');


    cy.visit(url);
    cy.wait('@meCall').then((xhr) => {
        expect(xhr).to.have.property('status', 200);
    });
    cy.get('.sw-desktop').should('be.visible');
});
