/**
 * Actuvates Shopware theme for Cypress test runner
 * @memberOf Cypress.Chainable#
 * @name activateShopwareTheme
 * @function
 */
Cypress.Commands.add('activateShopwareTheme', () => {
    const isStyleLoaded = $head => $head.find('#cypress-dark').length > 0;
    const themeFilename = 'theme/shopware.css';

    // Cypress includes jQuery
    const $head = Cypress.$(parent.window.document.head);
    if (isStyleLoaded($head) || !Cypress.config('useShopwareTheme')) {
        return;
    }

    // TODO: Substring workaround for Cypress issue #4352, please remove asap
    cy.readFile(themeFilename, { log: false }).then(() => {
        $head.append(
            `<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.2/css/brands.css" integrity="sha384-i2PyM6FMpVnxjRPi0KW/xIS7hkeSznkllv+Hx/MtYDaHA5VcF0yL3KVlvzp8bWjQ" crossorigin="anonymous">
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.2/css/fontawesome.css" integrity="sha384-sri+NftO+0hcisDKgr287Y/1LVnInHJ1l+XC7+FOabmTTIK0HnE2ID+xxvJ21c5J" crossorigin="anonymous">`
        );
    }).then((css) => {
        $head.append(
            `<style type="text/css" id="cypress-shopware">\n${css}</style>`
        );
    });
});

/**
 * Cleans up any previous state by restoring database and clearing caches
 * @memberOf Cypress.Chainable#
 * @name cleanUpPreviousState
 * @function
 */
Cypress.Commands.add('cleanUpPreviousState', () => {
    if (Cypress.env('localUsage')) {
        return cy.exec(`${Cypress.env('projectRoot')}/psh.phar e2e:restore-db`);
    }
    return cy.request('http://docker.vm:8005/cleanup');
});
