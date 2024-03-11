// / <reference types="Cypress" />
/**
 * @package inventory
 */
import ManufacturerPageObject from '../../../../support/pages/module/sw-manufacturer.page-object';

describe('Manufacturer: Visual tests', () => {
    // eslint-disable-next-line no-undef
    beforeEach(() => {
        cy.createDefaultFixture('product-manufacturer').then(() => {
            cy.openInitialPage(Cypress.env('admin'));
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@visual: check appearance of basic manufacturer workflow', { tags: ['pa-inventory', 'VUE3'] }, () => {
        const page = new ManufacturerPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/product-manufacturer/**`,
            method: 'PATCH',
        }).as('saveData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/manufacturer/index',
            mainMenuId: 'sw-catalogue',
            subMenuId: 'sw-manufacturer',
        });

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-manufacturer-list__content').should('exist');

        // Take snapshot for visual testing
        cy.get('.sw-skeleton__listing').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Manufacturer] Listing', '.sw-data-grid--full-page', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        // Edit base data
        cy.get(`${page.elements.dataGridRow}--0 a`).click();
        cy.get('.sw-page__main-content').should('be.visible');
        cy.get('.sw-skeleton__detail-bold').should('not.exist');
        cy.get('.sw-skeleton__detail').should('not.exist');

        // Take snapshot for visual testing
        cy.get('.sw-media-upload-v2__header .sw-context-button__button').should('be.visible');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Manufacturer] Detail', '.sw-manufacturer-detail', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
