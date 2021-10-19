// / <reference types="Cypress" />

import RulePageObject from '../../../support/pages/module/sw-rule.page-object';

describe('Rule builder: Test viewing rule assignments in other entities', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('rule');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/rule/index`);
            });
    });

    it('@rule: assign rule to shipping costs and verify assignment', () => {
        const page = new RulePageObject();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/shipping-method/*`,
            method: 'PATCH'
        }).as('saveShippingMethod');

        cy.onlyOnFeature('FEATURE_NEXT_6040', () => {
            cy.setEntitySearchable('shipping_method', 'name');
        });

        // Open rule
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible');
        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Ruler');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Ruler');
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Switch to assignments tab
        cy.get('.sw-settings-rule-detail__tabs').should('be.visible');
        cy.get('.sw-settings-rule-detail__tab-item-assignments').click();

        // Expect empty-state to be visible because the rule it not yet assigned to any entity
        cy.get('.sw-settings-rule-detail-assignments__empty-state').should('be.visible');

        // Go to shipping methods
        cy.visit(`${Cypress.env('admin')}#/sw/settings/shipping/index`);

        // Open shipping method
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible');
        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Express');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Express');
        cy.clickContextMenuItem(
            '.sw-settings-shipping-list__edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Assign rule to shipping method
        cy.get('.sw-settings-shipping-detail__condition_container').scrollIntoView();
        cy.get('.sw-settings-shipping-detail__top-rule').typeSingleSelectAndCheck(
            'Ruler',
            '.sw-settings-shipping-detail__top-rule'
        );

        // Save rule
        cy.get('.sw-settings-shipping-method-detail__save-action').click();
        cy.wait('@saveShippingMethod').its('response.statusCode').should('equal', 204);

        // Go back to rule
        cy.visit(`${Cypress.env('admin')}#/sw/settings/rule/index`);
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible');
        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Ruler');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Ruler');
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Switch to assignments tab
        cy.get('.sw-settings-rule-detail__tabs').should('be.visible');
        cy.get('.sw-settings-rule-detail__tab-item-assignments').click();

        // Shipping method assignment should be present
        cy.get('.sw-settings-rule-detail-assignments__card-shipping_method').should('be.visible');
        cy.get(`.sw-settings-rule-detail-assignments__card-shipping_method ${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('be.visible');
        cy.get(`.sw-settings-rule-detail-assignments__card-shipping_method ${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Express');
    });
});
