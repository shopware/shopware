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

// Import general dependencies
const _ = require('lodash');
const uuid = require('uuid/v4');

// Import commands.js
require('./commands/commands');

// Import api commands.js
require('./commands/api-commands');

// Import fixture commands.js
require('./commands/fixture-commands');

// Import fixture commands.js
require('./commands/storefront-api-commands');

// Import fixture commands.js
require('./commands/system-commands');

// Import themes:
if (Cypress.config('useDarkTheme')) {
    require('cypress-dark');
    require('cypress-dark/src/halloween');
}

Cypress.on('uncaught:exception', (err, runnable) => {
    // returning false here prevents Cypress from
    // failing the test
    return false;
});

// Alternatively you can use CommonJS syntax:
require('./pages/sw-general.page-object');
require('./pages/module/sw-product.page-object');
require('./pages/module/sw-manufacturer.page-object');
require('./pages/module/sw-category.page-object');

before(() => {
    cy.activateShopwareTheme();
});

Cypress.Screenshot.defaults({
    capture: 'viewport'
});
