// / <reference types="Cypress" />

describe('Custom fields: Visual testing', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        cy.setToInitialState()
            .then(() => {
                return cy.createDefaultFixture('custom-field-set');
            });
    });

    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@base @visual: check appearance of custom field module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/custom-field-set`,
            method: 'post'
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
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

        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.takeSnapshot('[Custom fields] Listing', '.sw-settings-custom-field-set-list__card');

        cy.contains('.sw-custom-field-set-list__column-name', 'My custom field').click();
        cy.get('.sw-loader').should('not.exist');
        cy.takeSnapshot('[Custom fields] Detail', '.sw-custom-field-list__grid');

        cy.contains('.sw-custom-field-list__custom-field-label', 'custom_field_set_property').click();

        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.handleModalSnapshot('Edit custom field');
        cy.takeSnapshot('[Custom fields] Detail, Field modal', '#sw-field--currentCustomField-config-customFieldType');
    });
});
