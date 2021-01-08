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

            cy.server();
            cy.get('.sw-data-grid__cell--name > .sw-data-grid__cell-content > a').click();
        });
    });

    it('@base @marketing: generate and save a fixed promotion code', () => {
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
        cy.get('.sw-promotion-v2-individual-codes-behavior__empty-state').should('not.be.visible');
        cy.get('#sw-field--selectedCodeType').select('Individual promotion codes');
        cy.get('.sw-promotion-v2-individual-codes-behavior__empty-state').should('be.visible');
        cy.get('.sw-promotion-v2-individual-codes-behavior__empty-state-generate-action')
            .scrollIntoView()
            .should('be.visible')
            .click();

        cy.get('.sw-promotion-v2-generate-codes-modal').should('be.visible');
    });

    it("@base @marketing: generate and save individual promotion codes", () => {
        cy.get('#sw-field--selectedCodeType').select('Individual promotion codes');
        cy.get('.sw-promotion-v2-individual-codes-behavior__empty-state').should('be.visible');
        cy.get('.sw-promotion-v2-individual-codes-behavior__empty-state-generate-action')
            .scrollIntoView()
            .should('be.visible')
            .click();

        cy.get('.sw-promotion-v2-generate-codes-modal').should('be.visible');
        cy.get('#sw-field--pattern-prefix')
            .click()
            .clear()
            .type('pre_{enter}');
        cy.get('#sw-field--pattern-codeLength')
            .click()
            .clear()
            .type('5{enter}');
        cy.get('#sw-field--pattern-suffix')
            .click()
            .clear()
            .type('_post{enter}');
        cy.get('#sw-field--preview').should('have.value', 'pre_%s%s%s%s%s_post');

        cy.get('#sw-field--pattern-codeLength')
            .click()
            .clear()
            .type('2{enter}');
        cy.get('#sw-field--preview').should('have.value', 'pre_%s%s_post');
        cy.get('.sw-promotion-v2-generate-codes-modal__button-cancel').click();

        cy.get('.sw-promotion-v2-detail__save-action').click();
        cy.get('.sw-loader').should('not.be.visible');

        cy.get('.sw-promotion-v2-individual-codes-behavior__empty-state-generate-action')
            .should('be.visible')
            .click();

        cy.get('.sw-promotion-v2-generate-codes-modal').should('be.visible');
        cy.get('#sw-field--preview').should('have.value', 'pre_%s%s_post');

        // ToDo NEXT-12517 - Implement generation check
    });

    xit("@base @marketing: generate and save individual promotion codes with custom pattern", () => {
        // ToDo NEXT-12515 - Implement generation check
    });

    xit("@base @marketing: show table, when codes are generated", () => {
        // ToDo NEXT-12517 - Implement generation check
    });
});
