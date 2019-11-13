// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************
//
//
// -- This is a parent command --
// Cypress.Commands.add("login", (email, password) => { ... })
//
//
// -- This is a child command --
// Cypress.Commands.add("drag", { prevSubject: 'element'}, (subject, options) => { ... })
//
//
// -- This is a dual command --
// Cypress.Commands.add("dismiss", { prevSubject: 'optional'}, (subject, options) => { ... })
//
//
// -- This is will overwrite an existing command --
// Cypress.Commands.overwrite("visit", (originalFn, url, options) => { ... })

/**
 * Types in an input element and checks if the content was correctly typed
 * @memberOf Cypress.Chainable#
 * @name typeAndCheck
 * @function
 * @param {String} value - The value to type
 */
Cypress.Commands.add('typeAndCheck', {
    prevSubject: 'element'
}, (subject, value) => {
    cy.wrap(subject).type(value).invoke('val').should('eq', value);
});

/**
 * Ticks a checkbox element and checks if it is behaving accordingly
 * @memberOf Cypress.Chainable#
 * @name tickAndCheckCheckbox
 * @function
 * @param {Boolean} checked - The value to type
 */
Cypress.Commands.add('tickAndCheckCheckbox', {
    prevSubject: 'element'
}, (subject, checked) => {
    cy.wrap(subject).click(checked);
    checked ?
        cy.wrap(subject).should('have.attr', 'checked')
        : cy.wrap(subject).should('not.have.attr', 'checked');
});

/**
 * Types in the global search field and verify search terms in url
 * @memberOf Cypress.Chainable#
 * @name typeAndCheckSelectField
 * @function
 * @param {String} value - The value to type
 */
Cypress.Commands.add('typeAndCheckSelectField', {
    prevSubject: 'element'
}, (subject, value) => {
    cy.wrap(subject).select(value).contains(value);
});
