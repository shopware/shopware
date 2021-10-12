// Imports
import '@percy/cypress';

// Require test suite commons
require('@shopware-ag/e2e-testsuite-platform/cypress/support');

// Custom administration commands
require('./commands/commands');

// this sets the default browser locale to the environment variable
Cypress.on('window:before:load', (window) => {
    Object.defineProperty(window.navigator, 'language', {
        value: Cypress.env('locale')
    })
})
