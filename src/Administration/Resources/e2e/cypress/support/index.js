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

// Import commands.js using ES2015 syntax:
import './commands/commands'

// Import api commands.js using ES2015 syntax:
import './commands/api-commands'

//Import storefront api commands using ES2015 syntax:
import './commands/storefront-api-commands'

// Import fixture commands.js using ES2015 syntax:
import './commands/fixture-commands'

// Import fixture commands.js using ES2015 syntax:
import './commands/system-commands'

// Import themes:
Cypress.config('useDarkTheme') ? require('cypress-dark') : null;
Cypress.config('useDarkTheme') ? require('cypress-dark/src/halloween') : null;

// Alternatively you can use CommonJS syntax:
require('./pages/sw-general.page-object');
require('./pages/module/sw-product.page-object');
require('./pages/module/sw-manufacturer.page-object');

before(() => {
    cy.activateShopwareTheme();
});

