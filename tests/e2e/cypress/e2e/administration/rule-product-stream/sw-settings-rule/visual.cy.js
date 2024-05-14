// / <reference types="Cypress" />

import RulePageObject from '../../../../support/pages/module/sw-rule.page-object';

describe('Rule builder: Visual tests', () => {
    beforeEach(() => {
        cy.createDefaultFixture('rule').then(() => {
            cy.openInitialPage(Cypress.env('admin'));
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@visual: check appearance of basic rule workflow', { tags: ['pa-services-settings'] }, () => {
        const page = new RulePageObject();
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/rule`,
            method: 'POST',
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings',
        });
        cy.get('#sw-settings-rule').click();
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-settings-rule-list__content').should('exist');

        // Change text of the element to ensure consistent snapshots
        cy.changeElementText('.sw-data-grid__cell--updatedAt .sw-data-grid__cell-content', '01 Jan 2018, 00:01');

        // Change text of the element to ensure consistent snapshots
        cy.changeElementText('.sw-data-grid__cell--createdAt .sw-data-grid__cell-content', '01 Jan 2018, 00:00');

        // Take snapshot for visual testing
        cy.get(page.elements.loader).should('not.exist');
        cy.get('.sw-loader__element').should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-card__content').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Rule builder] Listing', '.sw-rule-list-grid', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Ruler');

        cy.get(page.elements.loader).should('not.exist');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Ruler');
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        // Take snapshot
        cy.get('.sw-settings-rule-detail-base').should('be.visible');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Rule builder] Detail', '.sw-settings-rule-detail-base', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.get('.sw-condition-tree .sw-condition-or-container .sw-condition-and-container')
            .first()
            .as('first-and-container');
        cy.get('@first-and-container').should('be.visible');

        cy.get('@first-and-container').within(() => {
            cy.get('.sw-condition').as('condition-general');

            page.createBasicSelectCondition({
                selector: '@condition-general',
                type: 'Item with free shipping',
                operator: null,
                value: 'No',
            });

            cy.get('button.sw-button').contains('Add AND condition').click();
            cy.get('.sw-condition').should('have.length', 2);

            cy.get('.sw-condition').eq(1).as('second-condition');
            page.createBasicInputCondition({
                selector: '@second-condition',
                type: 'Grand total',
                operator: 'Is greater than',
                inputName: 'amount',
                value: '100',
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
                value: 'Standard customer group',
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
                type: 'Billing address: Country',
                operator: 'Is none of',
                value: 'Australia',
            });
        });

        cy.get('.sw-condition-tree .sw-condition-or-container__actions button.sw-button')
            .contains('Add OR condition')
            .click();

        cy.get('.sw-condition-tree .sw-condition-or-container .sw-condition-and-container')
            .eq(1).as('second-and-container');
        cy.get('@second-and-container').should('be.visible');

        cy.get('@second-and-container').within(() => {
            page.createBasicSelectCondition({
                selector: '.sw-condition',
                type: 'Commercial customer',
                operator: null,
                value: 'Yes',
            });

            cy.get('button.sw-button').contains('Add subconditions').click();
            cy.get('.sw-condition').should('have.length', 2);

            cy.get('.sw-condition').eq(1).as('subcontainer');
            cy.get('@subcontainer').should('be.visible');

            page.createBasicInputCondition({
                selector: '@subcontainer',
                type: 'Total quantity of all products',
                operator: 'Is equal to',
                inputName: 'count',
                value: 100,
            });
        });

        // Take snapshot for visual testing
        cy.get('.sw-condition-tree').scrollIntoView();
        cy.get(page.elements.loader).should('not.exist');
        cy.get('.sw-condition').should('be.visible');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Rule builder] Detail, rule with conditions', '.sw-condition', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        // open filter modal
        cy.get('.sw-condition-line-item-goods-total__filter')
            .should('be.visible')
            .click();

        // Take snapshot for visual testing
        cy.get('.sw-modal').should('be.visible');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Rule builder] Detail, condition modal', '.sw-modal', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
