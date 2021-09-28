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

require('@shopware-ag/e2e-testsuite-platform/cypress/support');

// load and register the grep feature
// https://github.com/bahmutov/cypress-grep
require('cypress-grep')()

// Custom administration commands
require('./commands/commands');

Cypress.Cookies.defaults({
    preserve: ['_test-api-dbName', '_apiAuth']
})

// this sets the default browser locale to the environment variable
Cypress.on('window:before:load', (window) => {
    Object.defineProperty(window.navigator, 'language', {
        value: Cypress.env('locale')
    })
})
