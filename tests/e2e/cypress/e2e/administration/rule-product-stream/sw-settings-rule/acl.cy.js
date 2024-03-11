// / <reference types="Cypress" />

import RulePageObject from '../../../../support/pages/module/sw-rule.page-object';

describe('Rule builder: Test crud operations', () => {
    beforeEach(() => {
        cy.createDefaultFixture('rule').then(() => {
            return cy.createDefaultFixture('promotion');
        })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@base @rule: read rule', { tags: ['pa-services-settings'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'rule',
                role: 'viewer',
            },
            {
                key: 'flow',
                role: 'viewer',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/rule/index`);
            cy.get('.sw-rule-list-grid').should('exist');
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        const page = new RulePageObject();

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`)
            .contains('All customers')
            .click();

        cy.get('#sw-field--rule-name').should('have.value', 'All customers');
        cy.get('.sw-condition-type-select .sw-single-select__selection-text').contains('Customer group');

        cy.get('.smart-bar__actions .sw-button--primary')
            .should('to.have.prop', 'disabled', true);

        cy.get('.smart-bar__actions .sw-settings-rule-detail__button-context-menu')
            .should('to.have.prop', 'disabled', true);

        // Switch to assignments tab
        cy.get('.sw-settings-rule-detail__tab-item-assignments').click();

        cy.get('.sw-settings-rule-detail-assignments__add-button').each(($element) => {
            cy.wrap($element).should('have.class', 'sw-button--disabled');
        });
    });

    it('@base @rule: edit rule', { tags: ['pa-services-settings'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'rule',
                role: 'viewer',
            },
            {
                key: 'flow',
                role: 'viewer',
            },
            {
                key: 'rule',
                role: 'editor',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/rule/index`);
            cy.get('.sw-rule-list-grid').should('exist');
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        const page = new RulePageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/rule/*`,
            method: 'PATCH',
        }).as('saveData');

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`)
            .contains('All customers')
            .click();

        cy.get('#sw-field--rule-name').should('have.value', 'All customers');
        cy.get('#sw-field--rule-name').clearTypeAndCheck('Some customers');

        // fill rule data
        cy.get('.sw-condition-type-select').click();

        cy.get('.sw-select-result-list-popover-wrapper').should('be.visible');
        cy.get('.sw-select-result-list-popover-wrapper').contains('Time range')
            .click();

        cy.get('.sw-condition-time-range').should('exist');

        cy.get('.smart-bar__actions .sw-button--primary')
            .should('to.have.prop', 'disabled', false);

        cy.get('.smart-bar__actions .sw-settings-rule-detail__button-context-menu')
            .should('to.have.prop', 'disabled', false);

        cy.get('.smart-bar__actions .sw-settings-rule-detail__button-context-menu').click();
        cy.get('.sw-settings-rule-detail__save-duplicate-action').should('to.have.class', 'is--disabled', true);

        // Verify rule
        cy.get('button.sw-button').contains('Save').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        // wait for ending loading state in tree view
        cy.get('.sw-condition-tree .sw-loader').should('exist');
        cy.get('.sw-condition-tree .sw-loader').should('not.exist');

        // Switch to assignments tab
        cy.get('.sw-settings-rule-detail__tab-item-assignments').click();

        cy.get('.sw-settings-rule-detail-assignments__card-shipping_method_availability_rule .sw-settings-rule-detail-assignments__add-button')
            .should('have.not.class', 'sw-button--disabled');
        cy.get('.sw-settings-rule-detail-assignments__card-payment_method .sw-settings-rule-detail-assignments__add-button')
            .should('have.not.class', 'sw-button--disabled');
        cy.get('.sw-settings-rule-detail-assignments__card-promotion_cart_rule .sw-settings-rule-detail-assignments__add-button')
            .should('have.not.class', 'sw-button--disabled');
    });

    it('@base @rule: create and read rule', { tags: ['pa-services-settings'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'rule',
                role: 'viewer',
            },
            {
                key: 'flow',
                role: 'viewer',
            },
            {
                key: 'rule',
                role: 'editor',
            },
            {
                key: 'rule',
                role: 'creator',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/rule/index`);
            cy.get('.sw-rule-list-grid').should('exist');
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        const page = new RulePageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/rule`,
            method: 'POST',
        }).as('saveData');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-rule-list-grid').should('be.visible');

        cy.get('a[href="#/sw/settings/rule/create"]').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-settings-rule-detail-base').should('exist');
        // save with empty data
        cy.contains('button.sw-button--primary', 'Save').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 400);

        cy.get('.sw-alert--error .sw-alert__message')
            .should('be.visible')
            .contains('An error occurred while saving rule');

        // fill basic data
        cy.get('.sw-field').contains('.sw-field', 'Name').then((field) => {
            cy.wrap(field).should('have.class', 'has--error');
            cy.get('input', { withinSubject: field }).type('Rule 1st');
            cy.wrap(field).should('not.have.class', 'has--error');
        });

        cy.get('.sw-field').contains('.sw-field', 'Priority').then((field) => {
            cy.wrap(field).should('have.class', 'has--error');
            cy.get('input', { withinSubject: field }).type('1').blur();
            cy.wrap(field).should('not.have.class', 'has--error');
        });

        cy.get('.sw-field').contains('.sw-field', 'Description').then((field) => {
            cy.get('textarea', { withinSubject: field }).type('desc');
        });

        // fill rule data
        cy.get('.sw-condition').then((conditionElement) => {
            cy.get('.sw-condition-type-select', { withinSubject: conditionElement })
                .then((conditionTypeSelect) => {
                    cy.wrap(conditionTypeSelect).click();
                    cy.get('.sw-select-result-list-popover-wrapper').should('be.visible');

                    cy.get('.sw-select-result-list-popover-wrapper').contains('Time range')
                        .click();
                });
        });

        // Verify rule
        cy.get('button.sw-button').contains('Save').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get('.sw-skeleton').should('exist');
        cy.get('.sw-skeleton').should('not.exist');

        cy.get(page.elements.smartBarBack).click();

        cy.get('.sw-skeleton').should('exist');
        cy.get('.sw-skeleton').should('not.exist');

        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Rule 1st');
        cy.get(page.elements.loader).should('not.exist');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Rule 1st');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name .sw-data-grid__cell-value`).click();

        cy.get('.smart-bar__actions .sw-button--primary')
            .should('to.have.prop', 'disabled', false);

        cy.get('.smart-bar__actions .sw-settings-rule-detail__button-context-menu')
            .should('to.have.prop', 'disabled', false);

        cy.get('.smart-bar__actions .sw-settings-rule-detail__button-context-menu').click();
        cy.get('.sw-settings-rule-detail__save-duplicate-action').should('not.to.have.class', 'is--disabled');
    });

    it('@base @rule: delete rule', { tags: ['pa-services-settings'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'rule',
                role: 'viewer',
            },
            {
                key: 'flow',
                role: 'viewer',
            },
            {
                key: 'rule',
                role: 'deleter',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/rule/index`);
            cy.get('.sw-rule-list-grid').should('exist');
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        const page = new RulePageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/rule/*`,
            method: 'delete',
        }).as('deleteData');

        // Delete rule
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible');
        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Ruler');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Ruler');
        cy.clickContextMenuItem(
            '.sw-context-menu-item--danger',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );
        cy.get('.sw-listing__confirm-delete-text')
            .contains('Are you sure you want to delete this item?');
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();
        cy.wait('@deleteData').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.modal).should('not.exist');
    });
});
