// / <reference types="Cypress" />

describe('Profile: Visual tests', () => {
    beforeEach(() => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/profile/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
    });

    it('@visual: check appearance of basic profile workflow',  { tags: ['pa-services-settings'] }, () => {
        // Take snapshot for visual testing
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-tabs-item').contains(/General|Search preferences/g);
        cy.get('.sw-tabs-item[title="General"]').should('have.class', 'sw-tabs-item--active');
        cy.get('.sw-card__title').contains(/Profile information|Profile image|Password/g);
        cy.get('.sw-media-upload-v2__header .sw-context-button__button').should('be.visible');
        cy.get('.sw-loader-element').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Profile] Detail', '.sw-profile-index-general', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
