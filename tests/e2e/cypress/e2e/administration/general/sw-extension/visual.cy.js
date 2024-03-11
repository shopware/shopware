// / <reference types="Cypress" />

describe('Extension:  Visual tests', { tags: ['VUE3'] }, () => {
    // eslint-disable-next-line no-undef
    beforeEach(() => {
        const now = new Date(2018, 1, 1);
        cy.clock(now, ['Date'])
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@visual: check appearance of my extension overview', { tags: ['quarantined', 'pa-services-settings'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/extension/installed`,
            method: 'GET',
        }).as('getInstalled');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/**`,
            method: 'POST',
        }).as('searchResultCall');

        // Check my extensions listing
        cy.clickMainMenuItem({
            targetPath: '#/sw/extension/my-extensions',
            mainMenuId: 'sw-extension',
            subMenuId: 'sw-extension-my-extensions',
        });

        cy.wait('@getInstalled')
            .its('response.statusCode').should('equal', 200);

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-extension-card-base__info')
            .filter(':contains("SDK Testplugin")')
            .closest('.sw-extension-card-base')
            .should('be.visible');

        cy.get('.sw-extension-card-base__info')
            .filter(':contains("SDK Testplugin")')
            .closest('.sw-extension-card-base')
            .find('.sw-field--switch__input input[type=checkbox]')
            .should('be.checked');

        // Change color of the element to ensure consistent snapshots
        cy.changeElementStyling(
            '.sw-extension-card-base__meta-info',
            'color: #fff',
        );
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[My extensions] List', '.sw-extension-my-extensions-listing', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
