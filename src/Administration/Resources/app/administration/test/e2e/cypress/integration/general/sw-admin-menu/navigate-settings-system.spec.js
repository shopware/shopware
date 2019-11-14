// / <reference types="Cypress" />

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

    it('@general: navigate to user module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/user?page=1&limit=25',
            method: 'get'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('.sw-settings__tab-system').click();
        cy.get('a[href="#/sw/settings/user/list"]').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-settings-user-list').should('be.visible');
    });

    it('@general: navigate to shopware account module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/_action/system-config/schema?domain=core.store',
            method: 'get'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('.sw-settings__tab-system').click();
        cy.get('a[href="#/sw/settings/store/index"]').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-system-config').should('be.visible');
    });

    it('@general: navigate to shopware update module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/_action/system-config/schema?domain=core.update',
            method: 'get'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('.sw-settings__tab-system').click();
        cy.get('a[href="#/sw/settings/shopware/updates/index"]').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-card__title').contains('Shopware Updates');
    });

    it('@general: navigate to custom field module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/custom-field-set',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('.sw-settings__tab-system').click();
        cy.get('a[href="#/sw/settings/custom/field/index"]').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-settings-custom-field-set-list__card').should('be.visible');
    });

    it('@general: navigate to plugin module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/_action/plugin/refresh',
            method: 'post'
        }).as('refresh');
        cy.route({
            url: '/api/v1/search/plugin',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('.sw-tabs-item[title="System"]').click();
        cy.get('a[href="#/sw/plugin/index"]').click();
        cy.wait('@refresh').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-plugin-list').should('be.visible');
    });

    it('@general: navigate to integrations module', () => {
        cy.server();
        cy.route({
            url: '/api/v1//integration?page=1&limit=25',
            method: 'get'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('.sw-settings__tab-system').click();
        cy.get('a[href="#/sw/integration/index"]').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-integration-list__overview').should('be.visible');
    });
});
