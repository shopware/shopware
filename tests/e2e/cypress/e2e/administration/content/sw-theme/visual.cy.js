/// <reference types="Cypress" />
/**
 * @package buyers-experience
 */

describe('Theme: Visual tests', { tags: ['VUE3']}, () => {
    beforeEach(() => {
        cy.viewport(1920, 1080);
        cy.openInitialPage(Cypress.env('admin'));
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
    });

    it('@visual: check appearance of basic theme workflow', { tags: ['pa-sales-channels'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/theme/*`,
            method: 'PATCH',
        }).as('saveData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/theme/manager/index',
            mainMenuId: 'sw-content',
            subMenuId: 'sw-theme-manager',
        });

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-theme-list__list').should('be.visible');
        cy.get('.sw-skeleton__gallery').should('not.exist');
        cy.log('Before Screenshot');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Theme] Listing', '.sw-theme-list__content', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.get('.sw-theme-list-item')
            .contains('.sw-theme-list-item__title', 'Shopware default theme')
            .click();
        cy.get('.sw-colorpicker').should('be.visible');
        cy.get('.sw-skeleton__detail').should('not.exist');

        cy.changeElementText('.sw-select-selection-list__item-holder--0 > span > span > span', 'Sales Channel');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Theme] Shopware default theme', '.sw-theme-manager-detail__info', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
