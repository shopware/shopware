// / <reference types="Cypress" />

import RulePageObject from '../../../../support/pages/module/sw-rule.page-object';

const uuid = require('uuid/v4');

describe('Rule builder: Test viewing rule assignments in other entities', () => {
    const ruleId = uuid().replace(/-/g, '');
    const defaultRuleId = uuid().replace(/-/g, '');
    const shippingMethodId = uuid().replace(/-/g, '');
    const deliveryTimeId = uuid().replace(/-/g, '');

    beforeEach(() => {
        cy.createDefaultFixture('rule', { id: defaultRuleId, name: 'Default Rule' }, 'rule-simple-condition')
            .then(() => {
                return cy.createDefaultFixture('rule', {
                    id: ruleId,
                    name: 'Ruler' ,
                    conditions: [
                        {
                            "type": "orContainer",
                            "parentId": null,
                            "id": "bb63eedc25144ae095252ceb824b17ca",
                        },
                        {
                            "type": "andContainer",
                            "parentId": "bb63eedc25144ae095252ceb824b17ca",
                            "id": "5182ff99234e4b238033a3d16ade88eb",
                        },
                        {
                            "type": "customerBillingStreet",
                            "parentId": "5182ff99234e4b238033a3d16ade88eb",
                            "value": {
                                "operator":"=",
                                "streetName":"test",
                            },
                            "id": "acf32b2197fe40819b2e635193b81c61",
                        },
                    ],
                }, 'rule');
            })
            .then(() => {
                return cy.createDefaultFixture('delivery-time', { id: deliveryTimeId });
            })
            .then(() => {
                return cy.createDefaultFixture('shipping-method', { name: 'Testing Method', id: shippingMethodId, availabilityRuleId: defaultRuleId, deliveryTimeId: deliveryTimeId });
            })
            .then(() => {
                return cy.createDefaultFixture('promotion', {
                    personaRules: [
                        {
                            id: ruleId,
                        },
                    ],
                    cartRules: [
                        {
                            id: ruleId,
                        },
                    ],
                    orderRules: [
                        {
                            id: ruleId,
                        },
                    ],
                });
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/rule/detail/${ruleId}`);
                // wait for ending loading state
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@rule: add, verify and delete payment method rule assignment', { tags: ['pa-business-ops'] }, () => {
        const page = new RulePageObject();

        // Switch to assignments tab
        cy.get('.sw-settings-rule-detail__tab-item-assignments').click();

        // Payment method assignment should be empty
        cy.get('.sw-settings-rule-detail-assignments__entity-empty-state-payment_method').should('exist');

        // Assign payment method again
        cy.get('.sw-settings-rule-detail-assignments__card-payment_method .sw-settings-rule-detail-assignments__add-button').click();
        cy.get('.sw-settings-rule-add-assignment-modal .sw-data-grid__select-all input').click();
        cy.get(`.sw-settings-rule-add-assignment-modal ${page.elements.primaryButton}`).click();
        cy.get('.sw-settings-rule-detail-assignments__card-payment_method').find(`${page.elements.dataGridRow}`).should('have.length', 5);
        cy.get('.sw-settings-rule-detail-assignments__card-payment_method .sw-settings-rule-detail-assignments__add-button').should('be.disabled');

        // Delete payment method assignment
        cy.get('.sw-settings-rule-detail-assignments__card-payment_method .sw-data-grid__select-all input').click();
        cy.get('.sw-data-grid__bulk-selected .link-danger').click();
        cy.get('.sw-button--danger').click();
        cy.get('.sw-settings-rule-detail-assignments__entity-empty-state-payment_method').should('exist');
        cy.get('.sw-settings-rule-detail-assignments__card-payment_method .sw-settings-rule-detail-assignments__add-button').should('be.enabled');
    });

    it('@rule: verify, delete and add promotion rule assignments', { tags: ['pa-business-ops'] }, () => {
        const page = new RulePageObject();

        // Switch to assignments tab
        cy.get('.sw-settings-rule-detail__tab-item-assignments').click();

        // Promotion order assignment should be present
        cy.get('.sw-settings-rule-detail-assignments__card-promotion_order_rule').scrollIntoView().should('be.visible');
        cy.get(`.sw-settings-rule-detail-assignments__card-promotion_order_rule ${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('be.visible');
        cy.get(`.sw-settings-rule-detail-assignments__card-promotion_order_rule ${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Thunder Tuesday');
        cy.get('.sw-settings-rule-detail-assignments__card-promotion_order_rule .sw-settings-rule-detail-assignments__add-button').should('be.disabled');

        // Delete promotion order assignment
        cy.get('.sw-settings-rule-detail-assignments__card-promotion_order_rule .sw-data-grid__select-all input').click();
        cy.get('.sw-data-grid__bulk-selected .link-danger').click();
        cy.get('.sw-button--danger').click();
        cy.get('.sw-settings-rule-detail-assignments__entity-empty-state-promotion_order_rule').should('exist');
        cy.get('.sw-settings-rule-detail-assignments__card-promotion_order_rule .sw-settings-rule-detail-assignments__add-button').should('be.enabled');

        // Assign promotion again
        cy.get('.sw-settings-rule-detail-assignments__card-promotion_order_rule .sw-settings-rule-detail-assignments__add-button').click();
        cy.get('.sw-settings-rule-add-assignment-modal .sw-data-grid__select-all input').click();
        cy.get(`.sw-settings-rule-add-assignment-modal ${page.elements.primaryButton}`).click();
        cy.get('.sw-settings-rule-detail-assignments__card-promotion_order_rule').find(`${page.elements.dataGridRow}`).should('have.length', 2);
        cy.get('.sw-settings-rule-detail-assignments__card-promotion_order_rule .sw-settings-rule-detail-assignments__add-button').should('be.disabled');

        // Promotion cart assignment should be present
        cy.get('.sw-settings-rule-detail-assignments__card-promotion_cart_rule').scrollIntoView().should('be.visible');
        cy.get(`.sw-settings-rule-detail-assignments__card-promotion_cart_rule ${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('be.visible');
        cy.get(`.sw-settings-rule-detail-assignments__card-promotion_cart_rule ${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Thunder Tuesday');
        cy.get('.sw-settings-rule-detail-assignments__card-promotion_cart_rule .sw-settings-rule-detail-assignments__add-button').should('be.disabled');

        // Delete promotion cart assignment
        cy.get('.sw-settings-rule-detail-assignments__card-promotion_cart_rule .sw-data-grid__select-all input').click();
        cy.get('.sw-data-grid__bulk-selected .link-danger').click();
        cy.get('.sw-button--danger').click();
        cy.get('.sw-settings-rule-detail-assignments__entity-empty-state-promotion_cart_rule').should('exist');
        cy.get('.sw-settings-rule-detail-assignments__card-promotion_cart_rule .sw-settings-rule-detail-assignments__add-button').should('be.enabled');

        // Assign promotion again
        cy.get('.sw-settings-rule-detail-assignments__card-promotion_cart_rule .sw-settings-rule-detail-assignments__add-button').click();
        cy.get('.sw-settings-rule-add-assignment-modal .sw-data-grid__select-all input').click();
        cy.get(`.sw-settings-rule-add-assignment-modal ${page.elements.primaryButton}`).click();
        cy.get('.sw-settings-rule-detail-assignments__card-promotion_cart_rule').find(`${page.elements.dataGridRow}`).should('have.length', 2);
        cy.get('.sw-settings-rule-detail-assignments__card-promotion_cart_rule .sw-settings-rule-detail-assignments__add-button').should('be.disabled');

        // Promotion customer assignment should be present
        cy.get('.sw-settings-rule-detail-assignments__card-promotion_customer_rule').scrollIntoView().should('be.visible');
        cy.get(`.sw-settings-rule-detail-assignments__card-promotion_customer_rule ${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('be.visible');
        cy.get(`.sw-settings-rule-detail-assignments__card-promotion_customer_rule ${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Thunder Tuesday');
        cy.get('.sw-settings-rule-detail-assignments__card-promotion_customer_rule .sw-settings-rule-detail-assignments__add-button').should('be.disabled');

        // Delete promotion customer assignment
        cy.get('.sw-settings-rule-detail-assignments__card-promotion_customer_rule .sw-data-grid__select-all input').click();
        cy.get('.sw-data-grid__bulk-selected .link-danger').click();
        cy.get('.sw-button--danger').click();
        cy.get('.sw-settings-rule-detail-assignments__entity-empty-state-promotion_customer_rule').should('exist');
        cy.get('.sw-settings-rule-detail-assignments__card-promotion_customer_rule .sw-settings-rule-detail-assignments__add-button').should('be.enabled');

        // Assign promotion again
        cy.get('.sw-settings-rule-detail-assignments__card-promotion_customer_rule .sw-settings-rule-detail-assignments__add-button').click();
        cy.get('.sw-settings-rule-add-assignment-modal .sw-data-grid__select-all input').click();
        cy.get(`.sw-settings-rule-add-assignment-modal ${page.elements.primaryButton}`).click();
        cy.get('.sw-settings-rule-detail-assignments__card-promotion_customer_rule').find(`${page.elements.dataGridRow}`).should('have.length', 2);
        cy.get('.sw-settings-rule-detail-assignments__card-promotion_customer_rule .sw-settings-rule-detail-assignments__add-button').should('be.disabled');
    });

    it('@rule @package: assign rule to shipping method and verify assignment', { tags: ['pa-business-ops'] }, () => {
        const page = new RulePageObject();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/shipping-method/*`,
            method: 'PATCH',
        }).as('saveShippingMethod');

        // Switch to assignments tab
        cy.get('.sw-settings-rule-detail__tabs').should('be.visible');
        cy.get('.sw-settings-rule-detail__tab-item-assignments').click();

        // Expect empty-state to be visible because the rule it not yet assigned to any entity
        cy.get('.sw-settings-rule-detail-assignments__entity-empty-state-shipping_method_availability_rule').should('be.visible');

        // Go to shipping methods
        cy.visit(`${Cypress.env('admin')}#/sw/settings/shipping/detail/${shippingMethodId}`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Assign rule to shipping method
        cy.get('.sw-settings-shipping-detail__condition_container').scrollIntoView();
        cy.get('.sw-settings-shipping-detail__top-rule').typeSingleSelectAndCheck(
            'Ruler',
            '.sw-settings-shipping-detail__top-rule',
        );

        // Save rule
        cy.get('.sw-settings-shipping-method-detail__save-action').click();
        cy.wait('@saveShippingMethod').its('response.statusCode').should('equal', 204);

        // Go back to rule
        cy.visit(`${Cypress.env('admin')}#/sw/settings/rule/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible');
        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Ruler');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Ruler');
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        // Switch to assignments tab
        cy.get('.sw-settings-rule-detail__tabs').should('be.visible');
        cy.get('.sw-settings-rule-detail__tab-item-assignments').click();

        // Shipping method assignment should be present
        cy.get('.sw-settings-rule-detail-assignments__card-shipping_method_availability_rule').should('be.visible');
        cy.get(`.sw-settings-rule-detail-assignments__card-shipping_method_availability_rule ${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('be.visible');
        cy.get(`.sw-settings-rule-detail-assignments__card-shipping_method_availability_rule ${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Testing Method');
    });

    it('@rule @package: assign shipping method to rule via assignment tab, verify assignment and delete assignment', { tags: ['pa-business-ops'] }, () => {
        const page = new RulePageObject();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/shipping-method`,
            method: 'POST',
        }).as('searchData');

        // Switch to assignments tab
        cy.get('.sw-settings-rule-detail__tabs').should('be.visible');
        cy.get('.sw-settings-rule-detail__tab-item-assignments').click();

        // Expect empty-state to be visible because the rule it not yet assigned to any entity
        cy.get('.sw-settings-rule-detail-assignments__entity-empty-state-payment_method').should('exist');

        // Assign all payment methods
        cy.get('.sw-settings-rule-detail-assignments__card-payment_method .sw-settings-rule-detail-assignments__add-button').click();
        cy.get('.sw-settings-rule-add-assignment-modal .sw-data-grid__select-all input').click();
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
