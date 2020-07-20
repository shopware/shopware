
/**
 * Types in the global search field and verify search terms in url
 * @memberOf Cypress.Chainable#
 * @name runMinimalAutoUpdate
 * @function
 * @param {String} tag - Tag of the update
 */
Cypress.Commands.add('runMinimalAutoUpdate', (tag) => {
    let version = tag[0] === 'v' ? tag.slice(1) : tag;

    cy.wait('@downloadLatestUpdate', { responseTimeout: 300000, timeout: 310000 })
        .then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
    cy.wait('@deactivatePlugins', { responseTimeout: 300000, timeout: 310000 })
        .then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
    cy.wait('@unpack', { responseTimeout: 300000, timeout: 310000 })
        .then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

    cy.get('section.content--main', { timeout: 120000 }).should('be.visible');
    cy.get('.navigation--list .is--active .navigation--link').contains('Datenbank-Migration');
    cy.get('.content--main h2').contains('Datenbank-Update durchführen');

    cy.wait('@applyMigrations', { responseTimeout: 300000, timeout: 310000 })
        .then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

    cy.get('[name="cleanupForm"]', { timeout: 120000 }).should('be.visible');
    cy.get('.is--active > .navigation--link', { timeout: 1000 }).contains('Aufräumen');
    cy.get('.content--main h2').contains('Aufräumen');
    cy.get('.btn.btn-primary').contains('Weiter').click();

    cy.get('.alert-hero-title').should('be.visible');
    cy.get('.navigation--list .is--active .navigation--link').contains('Fertig');
    cy.get('.alert-hero-title').contains('Das Update war erfolgreich!');
    cy.get('.btn.btn-primary').contains('Update abschließen').click();

    cy.getCookie('bearerAuth')
        .then((val) => {
            // we need to login, if the new auth cookie does not exist - e.g. update from 6.1.x -> 6.2.x
            if (!val) {
                cy.get('.sw-login__content').should('be.visible');
                cy.get('#sw-field--username').clear().type(Cypress.env('user'));
                cy.get('#sw-field--password').clear().type(Cypress.env('pass'));
                cy.get('.sw-button__content').click();
            }
        });

    cy.get('.sw-version__info').contains(tag).should('be.visible');
});

/**
 * Types in the global search field and verify search terms in url
 * @memberOf Cypress.Chainable#
 * @name prepareMinimalUpdate
 * @function
 * @param {String} tag - Tag of the update
 */
Cypress.Commands.add('prepareMinimalUpdate', (tag) => {
    let version = tag[0] === 'v' ? tag.slice(1) : tag;
    cy.get('.sw-alert__actions > :nth-child(1) > .sw-button__content').should('be.visible').click();

    cy.get('.smart-bar__header > h2').contains('(' + version + ')').should('be.visible');

    // TODO: plugin step

    cy.get('.sw-button__content')
        .contains('Update starten')
        .should('be.visible')
        .click();

    cy.get('.sw-field--checkbox label')
        .contains('Ja, ich habe ein Backup erstellt.')
        .should('be.visible')
        .click();

    cy.server();
    cy.route({ url: '/api/v1/_action/update/download-latest-update*', method: 'get' }).as('downloadLatestUpdate');
    cy.route({ url: '/api/v1/_action/update/deactivate-plugins*', method: 'get' }).as('deactivatePlugins');
    cy.route({ url: '/api/v1/_action/update/unpack*', method: 'get' }).as('unpack');
    cy.route({url: '*applyMigrations*', method: 'get'}).as('applyMigrations');

    cy.get('.sw-settings-shopware-updates-check__start-update-actions > .sw-button--primary')
        .should('be.enabled')
        .click();
});
/**
 * Logs in to the Administration manually
 * @memberOf Cypress.Chainable#
 * @name login
 * @function
 * @param {Object} userType - The type of the user logging in
 */
Cypress.Commands.add('login', (userType) => {
    const admin =  {
        name: 'admin',
        pass: 'shopware'
    };

    const user = userType ? types[userType] : admin;

    cy.contains('Username');
    cy.contains('Password');

    cy.get('#sw-field--username')
        .type(user.name)
        .should('have.value', user.name);
    cy.get('#sw-field--password')
        .type(user.pass)
        .should('have.value', user.pass);
    cy.get('.sw-login-login').submit();
    cy.contains('Dashboard');
});
