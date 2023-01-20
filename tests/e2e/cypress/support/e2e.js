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
//
import 'cypress-file-upload';
import 'cypress-real-events/support';
import 'cypress-network-idle';
import registerCypressGrep from '@cypress/grep/src/support';

registerCypressGrep();

require('@shopware-ag/e2e-testsuite-platform/cypress/support');

// Custom administration commands
require('./commands/commands');

// this sets the default browser locale to the environment variable
Cypress.on('window:before:load', (window) => {
    Object.defineProperty(window.navigator, 'language', {
        value: Cypress.env('locale')
    })
})

beforeEach(() => {
    return cy.authenticate().then(() => {
        if (!Cypress.env('SKIP_INIT')) {
            return cy.setToInitialState().then(() => {
                return cy.authenticate();
            });
        }
    });
});

// we need to use the classic function syntax to bind `this` correctly
afterEach(function () {
    const { state, _currentRetry, _retries } = this.currentTest;
    if (Cypress.env('INTERRUPT_ON_ERROR') && state === 'failed' && _currentRetry >= _retries) {
        throw new Error('Interrupt');
    }
})
