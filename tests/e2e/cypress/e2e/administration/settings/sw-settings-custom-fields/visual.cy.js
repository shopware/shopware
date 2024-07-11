// / <reference types="Cypress" />

describe('Custom fields: Visual testing', () => {
    // eslint-disable-next-line no-undef
    beforeEach(() => {
        cy.createDefaultFixture('custom-field-set')
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@base @visual: check appearance of custom field module', { tags: ['pa-services-settings', 'VUE3'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/custom-field-set`,
            method: 'POST',
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings',
        });
        cy.get('.sw-settings__tab-system').click();
        cy.get('.sw-settings__tab-system.sw-tabs-item--active').should('exist');
        cy.get('#sw-settings__content-grid-system').should('be.visible');

        cy.get('a[href="#/sw/settings/custom/field/index"]').should('be.visible');
        cy.get('a[href="#/sw/settings/custom/field/index"]').click();
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Custom fields] Listing', '.sw-settings-custom-field-set-list__card', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.contains('.sw-custom-field-set-list__column-name', 'My custom field').click();
        cy.get('.sw-loader').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Custom fields] Detail', '.sw-custom-field-list__grid', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.contains('.sw-custom-field-list__custom-field-label', 'custom_field_set_property').click();
        cy.wait('@getData').its('response.statusCode').should('equals', 200);

        cy.handleModalSnapshot('Edit custom field');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Custom fields] Detail, Field modal', '#sw-field--currentCustomField-config-customFieldType', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
