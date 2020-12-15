/// <reference types="Cypress" />

const promotionCodeFixedSelector = '#sw-field--promotion-code';

describe('Promotion v2: Test crud operations', () => {
    before(() => {
        cy.onlyOnFeature('FEATURE_NEXT_12016');
    });

    beforeEach(() => {
        cy.setToInitialState().then(() => {
            cy.loginViaApi();
        }).then(() => {
            return cy.createDefaultFixture('promotion');
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/promotion/v2/index`);
        });
    });

    it('@base @marketing: generate and save a fixed promotion code', () => {
        cy.server();
        cy.get('.sw-data-grid__cell--name > .sw-data-grid__cell-content > a').click();

        const testPromoCode = 'WelcomeIAmAPromotionCode';

        // Select fixed code type and edit manually
        cy.get(promotionCodeFixedSelector).should('not.be.visible');
        cy.get('#sw-field--selectedCodeType').select('Fixed promotion code');
        cy.get(promotionCodeFixedSelector).should('be.visible');
        cy.get(promotionCodeFixedSelector).type(testPromoCode);

        // Save
        cy.get('.sw-promotion-v2-detail__save-action').click();
        cy.get('.sw-loader').should('not.be.visible');

        // Generate and check code
        cy.get(promotionCodeFixedSelector).should('contain.value', testPromoCode);
        cy.get('.sw-promotion-v2-detail-base__fixed-generate-button').click();
        cy.get(promotionCodeFixedSelector).should('not.contain.value', testPromoCode);
        cy.get(promotionCodeFixedSelector).should((code) => {
            expect(code[0].value).to.have.length(8);
        });
    });

    it("@base @marketing: show empty state, if there're no individual codes", () => {
        cy.server();
        cy.get('.sw-data-grid__cell--name > .sw-data-grid__cell-content > a').click();

        cy.get('.sw-promotion-v2-detail-base__individual-empty-state').should('not.be.visible');
        cy.get('#sw-field--selectedCodeType').select('Individual promotion codes');
        cy.get('.sw-promotion-v2-detail-base__individual-empty-state').should('be.visible');
    });
});
