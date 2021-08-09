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

require('@shopware-ag/e2e-testsuite-platform/cypress/support');

// Custom administration commands
require('./commands/commands');

Cypress.Cookies.defaults({
    preserve: ['_test-api-dbName', '_apiAuth']
})
