
/**
 * Types in the global search field and verify search terms in url
 * @memberOf Cypress.Chainable#
 * @name typeAndCheckSearchField
 * @function
 * @param {String} value - The value to type
 */
Cypress.Commands.add('typeAndCheckSearchField', {
    prevSubject: 'element'
}, (subject, value) => {
    // Request we want to wait for later
    cy.server();
    cy.route({
        url: `${Cypress.env('apiPath')}/search/**`,
        method: 'post'
    }).as('searchResultCall');

    cy.wrap(subject).type(value).should('have.value', value);

    cy.wait('@searchResultCall').then((xhr) => {
        expect(xhr).to.have.property('status', 200);

        cy.url().should('include', encodeURI(value));
    });
});

/**
 * Add role with Permissions
 * @memberOf Cypress.Chainable#
 * @name loginAsUserWithPermissions
 * @function
 * @param {Array} permissions - The permissions for the role
 */
Cypress.Commands.add('loginAsUserWithPermissions', {
    prevSubject: false
}, (permissions) => {
    cy.window().then(($w) => {
        const roleID = 'ef68f039468d4788a9ee87db9b3b94de';
        const localeId = $w.Shopware.State.get('session').currentUser.localeId;
        const headers = {
            Accept: 'application/vnd.api+json',
            Authorization: `Bearer ${$w.Shopware.Context.api.authToken.access}`,
            'Content-Type': 'application/json'
        };

        // save role
        cy.request({
            url: `/api/${Cypress.env('apiVersion')}/acl-role`,
            method: 'POST',
            headers: headers,
            body: {
                id: roleID,
                name: 'e2eRole',
                privileges: (() => {
                    const privilegesService = $w.Shopware.Service('privileges');

                    const requiredPermissions = privilegesService.getRequiredPrivileges();
                    const selectedPrivileges = permissions.reduce((selectedPrivileges, { key, role }) => {
                        const identifier = `${key}.${role}`;

                        selectedPrivileges.push(
                            identifier,
                            ...privilegesService.getPrivilegeRole(identifier).privileges
                        );

                        return selectedPrivileges;
                    }, []);

                    return [
                        ...selectedPrivileges,
                        ...requiredPermissions
                    ];
                })()
            }
        }).then(response => {
            cy.log(response);
        });

        // save user
        cy.request({
            url: `/api/${Cypress.env('apiVersion')}/user`,
            method: 'POST',
            headers: headers,
            body: {
                aclRoles: [{ id: roleID }],
                admin: false,
                email: 'max@muster.com',
                firstName: 'Max',
                id: 'b7fb49e9d86d4e5b9b03c9d6f929e36b',
                lastName: 'Muster',
                localeId: localeId,
                password: 'Passw0rd!',
                username: 'maxmuster'
            }
        });

        // logout
        cy.get('.sw-admin-menu__user-actions-toggle').click();
        cy.clearCookies();
        cy.get('.sw-admin-menu__logout-action').click();
        cy.get('.sw-login__container').should('be.visible');

        // login
        cy.get('#sw-field--username').type('maxmuster');
        cy.get('#sw-field--password').type('Passw0rd!');
        cy.get('.sw-login__login-action').click();
    });
});

/**
 * Cleans up any previous state by restoring database and clearing caches
 * @memberOf Cypress.Chainable#
 * @name openInitialPage
 * @function
 */
Cypress.Commands.add('openInitialPage', (url) => {
    // Request we want to wait for later
    cy.server();
    cy.route(`${Cypress.env('apiPath')}/_info/me`).as('meCall');


    cy.visit(url);
    cy.wait('@meCall').then((xhr) => {
        expect(xhr).to.have.property('status', 200);
    });
    cy.get('.sw-desktop').should('be.visible');
});
