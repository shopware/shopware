/// <reference types="Cypress" />

describe('Administration: Check module navigation in settings', () => {
    beforeEach(() => {
        // Clean previous state and prepare Administration
        cy.loginViaApi()
            .then(() => {
                cy.setLocaleToEnGb();
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@navigation: navigate to user module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/user`,
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('.sw-settings__tab-system').click();
        cy.get('.sw-settings__tab-system.sw-tabs-item--active').should('exist');
        cy.get('#sw-settings__content-grid-system').should('be.visible');

        cy.contains('Users & permissions').should('be.visible');
        cy.contains('Users & permissions').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-settings-user-list').should('be.visible');
    });

    it('@navigation: navigate to shopware account module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/system-config/schema?domain=core.store`,
            method: 'get'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('.sw-settings__tab-system').click();
        cy.get('.sw-settings__tab-system.sw-tabs-item--active').should('exist');
        cy.get('#sw-settings__content-grid-system').should('be.visible');

        cy.get('a[href="#/sw/settings/store/index"]').should('be.visible');
        cy.get('a[href="#/sw/settings/store/index"]').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-system-config').should('be.visible');
    });

    it('@navigation: navigate to logging module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/log-entry`,
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });

        cy.get('.sw-settings__tab-system').click();
        cy.get('.sw-settings__tab-system.sw-tabs-item--active').should('exist');
        cy.get('.sw-settings__tab-system.sw-tabs-item--active').should('exist');
        cy.get('#sw-settings__content-grid-system').should('be.visible');

        cy.get('#sw-settings-logging').should('be.visible');
        cy.get('#sw-settings-logging').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-data-grid').should('be.visible');
    });

    it('@navigation: navigate to shopware update module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/system-config/schema?domain=core.update`,
            method: 'get'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('.sw-settings__tab-system').click();
        cy.get('.sw-settings__tab-system.sw-tabs-item--active').should('exist');
        cy.get('.sw-settings__tab-system.sw-tabs-item--active').should('exist');
        cy.get('#sw-settings__content-grid-system').should('be.visible');

        cy.get('a[href="#/sw/settings/shopware/updates/index"]').should('be.visible');
        cy.get('a[href="#/sw/settings/shopware/updates/index"]').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-card__title').contains('Shopware Updates');
    });

    it('@base @navigation: navigate to custom field module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/custom-field-set`,
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('.sw-settings__tab-system').click();
        cy.get('.sw-settings__tab-system.sw-tabs-item--active').should('exist');
        cy.get('#sw-settings__content-grid-system').should('be.visible');

        cy.get('a[href="#/sw/settings/custom/field/index"]').should('be.visible');
        cy.get('a[href="#/sw/settings/custom/field/index"]').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-settings-custom-field-set-list__card').should('be.visible');
    });

    it('@base @navigation: navigate to plugin module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/plugin/refresh`,
            method: 'post'
        }).as('refresh');
        cy.route({
            url: `${Cypress.env('apiPath')}/search/plugin`,
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('.sw-settings__tab-system').click();
        cy.get('.sw-settings__tab-system.sw-tabs-item--active').should('exist');
        cy.get('#sw-settings__content-grid-system').should('be.visible');

        cy.get('a[href="#/sw/plugin/index"]').should('be.visible');
        cy.get('a[href="#/sw/plugin/index"]').click();
        cy.wait('@refresh').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-plugin-list').should('be.visible');
    });

    it('@navigation: navigate to integrations module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/integration`,
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('.sw-settings__tab-system').click();
        cy.get('.sw-settings__tab-system.sw-tabs-item--active').should('exist');
        cy.get('#sw-settings__content-grid-system').should('be.visible');

        cy.get('a[href="#/sw/integration/index"]').should('be.visible');
        cy.get('a[href="#/sw/integration/index"]').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-integration-list__overview').should('be.visible');
    });
});
