// / <reference types="Cypress" />

const promotionCodeFixedSelector = '#sw-field--promotion-code';
const debounceTimeout = 800;

describe('Promotion v2: Test crud operations', () => {
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

    it('@base @marketing: generate and save individual promotion codes and replace afterwards with a custom pattern', () => {
        cy.get('#sw-field--selectedCodeType').select('Individual promotion codes');
        cy.get('.sw-promotion-v2-individual-codes-behavior__empty-state').should('be.visible');
        cy.get('.sw-promotion-v2-individual-codes-behavior__empty-state-generate-action')
            .scrollIntoView()
            .should('be.visible')
            .click();

        // Configure codes
        cy.get('.sw-promotion-v2-generate-codes-modal').should('be.visible');
        cy.get('#sw-field--pattern-prefix')
            .clear()
            .type('pre_{enter}');
        cy.get('#sw-field--pattern-codeLength')
            .clear()
            .type('5{enter}');
        cy.get('#sw-field--pattern-suffix')
            .clear()
            .type('_post{enter}');

        // Generate first preview
        cy.wait(debounceTimeout);
        cy.get('.sw-promotion-v2-generate-codes-modal__button-generate').should('not.be.disabled');
        cy.get('#sw-field--preview').then((content) => {
            expect(content[0].value).to.match(/pre_([A-Z]){5}_post/);
        });

        // Generate new preview after change
        cy.get('#sw-field--pattern-codeLength')
            .clear()
            .type('2{enter}');

        cy.wait(debounceTimeout);
        cy.get('.sw-promotion-v2-generate-codes-modal__button-generate').should('not.be.disabled');
        cy.get('#sw-field--preview').then((content) => {
            expect(content[0].value).to.match(/pre_([A-Z]){2}_post/);
        });

        // Save pattern and reopen
        cy.get('.sw-promotion-v2-generate-codes-modal__button-cancel').click();
        cy.get('.sw-promotion-v2-detail__save-action').click();
        cy.get('.sw-loader').should('not.be.visible');

        cy.get('.sw-promotion-v2-individual-codes-behavior__empty-state-generate-action')
            .should('be.visible')
            .click();
        cy.get('.sw-promotion-v2-generate-codes-modal').should('be.visible');

        // Check new preview
        cy.wait(debounceTimeout);
        cy.get('.sw-promotion-v2-generate-codes-modal__button-generate').should('not.be.disabled');
        cy.get('#sw-field--preview').then((content) => {
            expect(content[0].value).to.match(/pre_([A-Z]){2}_post/);
        });

        // Generate 15 codes
        cy.get('#sw-field--codeAmount')
            .clear()
            .type('15{enter}');
        cy.get('.sw-promotion-v2-generate-codes-modal__button-generate').click();
        cy.get('.sw-promotion-v2-generate-codes-modal').should('not.be.visible');
        cy.get('.sw-promotion-v2-individual-codes-behavior__empty-state').should('not.be.visible');

        cy.get('.sw-data-grid__cell--code > .sw-data-grid__cell-content > span').then((content) => {
            expect(content).to.have.length(15);
            expect(content[0].innerText).to.match(/pre_([A-Z]){2}_post/);
        });
        cy.get('.sw-data-grid__cell--payload > .sw-data-grid__cell-content > span').should('have.class', 'is--inactive');

        // Reopen Modal and enter custom pattern
        cy.get('.sw-promotion-v2-individual-codes-behavior__generate-codes-action')
            .should('be.visible')
            .click();
        cy.get('.sw-promotion-v2-generate-codes-modal').should('be.visible');

        cy.get('.sw-promotion-v2-generate-codes-modal__content > .sw-field--switch > .sw-field--switch__content > .sw-field > .sw-field__label > label').click();
        cy.get('#sw-field--pattern-prefix').should('not.be.visible');
        cy.get('#sw-field--promotion-individualCodePattern')
            .should('have.value', 'pre_%s%s_post')
            .clear()
            .type('new_%d%d%d_new!');
        cy.get('#sw-field--codeAmount')
            .clear()
            .type('20{enter}');

        // Check new preview
        cy.wait(debounceTimeout);
        cy.get('.sw-promotion-v2-generate-codes-modal__button-generate').should('not.be.disabled');
        cy.get('#sw-field--preview').then((content) => {
            expect(content[0].value).to.match(/new_([0-9]){3}_new!/);
        });

        cy.get('.sw-promotion-v2-generate-codes-modal__button-generate').click();
        cy.get('.sw-promotion-v2-generate-codes-modal').should('not.be.visible');

        // Check generated and overridden codes
        cy.get('.sw-data-grid__cell--code > .sw-data-grid__cell-content > span').then((content) => {
            expect(content).to.have.length(20);
            expect(content[0].innerText).to.match(/new_([0-9]){3}_new!/);
        });
    });
});
