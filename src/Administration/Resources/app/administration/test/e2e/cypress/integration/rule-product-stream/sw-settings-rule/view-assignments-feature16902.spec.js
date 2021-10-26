// / <reference types="Cypress" />

import RulePageObject from '../../../support/pages/module/sw-rule.page-object';

const uuid = require('uuid/v4');

describe('Rule builder: Test viewing rule assignments in other entities', () => {
    const ruleId = uuid().replace(/-/g, '');
    let shippingMethodId = null;

    beforeEach(() => {
        cy.onlyOnFeature('FEATURE_NEXT_16902');

        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.searchViaAdminApi({
                    endpoint: 'shipping-method',
                    data: {
                        field: 'name',
                        value: 'Express'
                    }
                });
            })
            .then((response) => {
                shippingMethodId = response.id;
                return cy.createDefaultFixture('rule', { id: ruleId });
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/rule/detail/${ruleId}`);
            });
    });

    it('@rule: assign rule to shipping method and verify assignment', () => {
        const page = new RulePageObject();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/shipping-method/*`,
            method: 'PATCH'
        }).as('saveShippingMethod');

        // Switch to assignments tab
        cy.get('.sw-settings-rule-detail__tabs').should('be.visible');
        cy.get('.sw-settings-rule-detail__tab-item-assignments').click();

        // Expect empty-state to be visible because the rule it not yet assigned to any entity
        cy.get('.sw-settings-rule-detail-assignments__entity-empty-state-shipping_method_availability_rule').should('be.visible');

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
        cy.get('.sw-settings-rule-detail-assignments__card-shipping_method_availability_rule').should('be.visible');
        cy.get(`.sw-settings-rule-detail-assignments__card-shipping_method_availability_rule ${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('be.visible');
        cy.get(`.sw-settings-rule-detail-assignments__card-shipping_method_availability_rule ${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Express');
    });

    it.only('@rule: assign shipping method to rule via assignment tab, verify assignment and delete assignment', () => {
        const page = new RulePageObject();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/shipping-method`,
            method: 'POST'
        }).as('searchData');

        // Switch to assignments tab
        cy.get('.sw-settings-rule-detail__tabs').should('be.visible');
        cy.get('.sw-settings-rule-detail__tab-item-assignments').click();

        // Expect empty-state to be visible because the rule it not yet assigned to any entity
        cy.get('.sw-settings-rule-detail-assignments__entity-empty-state-payment_method').should('exist');

        // Assign all payment methods
        cy.get('.sw-settings-rule-detail-assignments__card-payment_method .sw-settings-rule-detail-assignments__add-button').click();
        cy.get('.sw-data-grid__select-all input').click();
        cy.get(`.sw-settings-rule-add-assignment-modal ${page.elements.primaryButton}`).click();

        // All payment methods are assigned, so the add button should disabled
        cy.get('.sw-settings-rule-detail-assignments__card-payment_method .sw-settings-rule-detail-assignments__add-button').should('be.disabled');

        // Remove first payment method
        cy.get('.sw-settings-rule-detail-assignments__card-payment_method').find(`${page.elements.dataGridRow}`).should('have.length', 5);
        cy.get(`.sw-settings-rule-detail-assignments__card-payment_method ${page.elements.dataGridRow}--0 .sw-data-grid__cell--selection input`).click();
        cy.get('.sw-data-grid__bulk-selected .link-danger').click();
        cy.get('.sw-button--danger').click();
        cy.get('.sw-settings-rule-detail-assignments__card-payment_method').find(`${page.elements.dataGridRow}`).should('have.length', 4);
    });
});
