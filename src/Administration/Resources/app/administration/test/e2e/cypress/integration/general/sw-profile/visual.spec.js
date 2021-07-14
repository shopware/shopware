/// <reference types="Cypress" />

describe('Profile: Visual tests', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi()
            }).then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/profile/index`);
            });
    });

    it('@visual: check appearance of basic profile workflow', () => {
        // Take snapshot for visual testing
        cy.get('.sw-loader').should('not.exist');
        cy.onlyOnFeature('FEATURE_NEXT_6040', () => {
            cy.get('.sw-tabs-item').contains(/General|Search preferences/g);
            cy.get('.sw-tabs-item[title="General"]').should('have.class', 'sw-tabs-item--active');
            cy.get('.sw-card__title').contains(/Profile information|Profile image|Password/g);
            cy.takeSnapshot('[Profile] Detail', '.sw-profile-index-general');
        });
        cy.skipOnFeature('FEATURE_NEXT_6040', () => {
            cy.takeSnapshot('[Profile] Detail', '.sw-profile__card');
        });
    });
});
