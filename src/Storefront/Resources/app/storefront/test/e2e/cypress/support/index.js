// ***********************************************************
// This example support/index.js is processed and
// loaded automatically before your test files.
//
// This is a great place to put global configuration and
// behavior that modifies Cypress.
//
// You can change the location of this file or turn off
// automatically serving support files with the
// 'supportFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/configuration
// ***********************************************************
require('@shopware-ag/e2e-testsuite-platform/cypress/support');

// Alternatively you can use CommonJS syntax:
require('./pages/general.page-object');
require('./pages/checkout.page-object');
require('./pages/account.page-object');
require('./service/fixture/rule-builder.fixture');

// Custom storefront commands
require('./commands/commands');

beforeEach(() => {
    return cy.log('Cleaning, please wait a little bit.').then(() => {
        return cy.cleanUpPreviousState();
    });
});
