// / <reference types="Cypress" />

import RulePageObject from '../../../support/pages/module/sw-rule.page-object';

describe('Rule builder: Visual tests', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('rule');
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@visual: check appearance of basic rule workflow', () => {
        const page = new RulePageObject();
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/rule`,
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-rule').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-settings-rule-list__content').should('exist');

        // Change color of the element to ensure consistent snapshots
        cy.changeElementStyling(
            '.sw-data-grid__cell--updatedAt',
            'color: #fff'
        );
        cy.get('.sw-data-grid__cell--updatedAt')
            .should('have.css', 'color', 'rgb(255, 255, 255)');

        // Take snapshot for visual testing
        cy.get(page.elements.loader).should('not.exist');
        cy.get('.sw-loader__element').should('not.exist');
        cy.get('.sw-card__content').should('not.exist');
        cy.takeSnapshot('[Rule builder] Listing', '.sw-rule-list-grid');

        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Ruler');

        cy.get(page.elements.loader).should('not.exist');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Ruler');
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Take snapshot
        cy.get('.sw-settings-rule-detail-base').should('be.visible');
        cy.takeSnapshot('[Rule builder] Detail', '.sw-settings-rule-detail-base');

        cy.get('.sw-condition-tree .sw-condition-or-container .sw-condition-and-container')
            .first()
            .as('first-and-container');
        cy.get('@first-and-container').should('be.visible');

        cy.get('@first-and-container').within(() => {
            cy.get('.sw-condition').as('condition-general');

            page.createBasicSelectCondition({
                selector: '@condition-general',
                type: 'Free shipping',
                operator: null,
                value: 'No'
            });

            cy.get('button.sw-button').contains('And').click();
            cy.get('.sw-condition').should('have.length', 2);

            cy.get('.sw-condition').eq(1).as('second-condition');
            page.createBasicInputCondition({
                selector: '@second-condition',
                type: 'Cart amount',
                operator: 'Is greater than',
                inputName: 'amount',
                value: '100'
            });

            cy.get('@second-condition').within(() => {
                cy.get('.sw-condition__context-button').click();
            });
        });

        cy.get('.sw-context-menu__content').should('be.visible');
        cy.get('.sw-context-menu__content').contains('Create before').click();

        cy.get('@first-and-container').within(() => {
            cy.get('.sw-condition').should('have.length', 3);

            page.createBasicSelectCondition({
                selector: '@second-condition',
                type: 'Customer group',
                operator: 'Is none of',
                value: 'Standard customer group'
            });

            cy.get('@second-condition').within(() => {
                cy.get('.sw-condition__context-button').click();
            });
        });

        cy.get('.sw-context-menu__content').should('be.visible');
        cy.get('.sw-context-menu__content').contains('Create after').click();

        cy.get('@first-and-container').within(() => {
            cy.get('.sw-condition').should('have.length', 4);

            cy.get('.sw-condition').eq(2).as('third-condition');
            page.createBasicSelectConditionFromSearch({
                selector: '@third-condition',
                type: 'Billing country',
                operator: 'Is none of',
                value: 'Australia'
            });
        });

        cy.get('.sw-condition-tree .sw-condition-or-container button.sw-button')
            .contains('Or')
            .click();

        cy.get('.sw-condition-tree .sw-condition-or-container .sw-condition-and-container')
            .eq(1).as('second-and-container');
        cy.get('@second-and-container').should('be.visible');

        cy.get('@second-and-container').within(() => {
            page.createBasicSelectCondition({
                selector: '.sw-condition',
                type: 'New customer',
                operator: null,
                value: 'Yes'
            });

            cy.get('button.sw-button').contains('Subcondition').click();
            cy.get('.sw-condition').should('have.length', 2);
        });

        // Take snapshot for visual testing
        cy.get('.sw-condition-tree').scrollIntoView();
        cy.get(page.elements.loader).should('not.exist');
        cy.get('.sw-condition').should('be.visible');
        cy.takeSnapshot('[Rule builder] Detail, rule with conditions', '.sw-condition');
    });
});
