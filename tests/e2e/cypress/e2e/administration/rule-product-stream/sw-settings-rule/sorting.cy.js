// / <reference types="Cypress" />

import RulePageObject from '../../../../support/pages/module/sw-rule.page-object';

describe('Rule builder: Sorting rules', () => {
    beforeEach(() => {
        cy.createDefaultFixture('rule', {
            paymentMethods: [
                { name: 'foo' },
                { name: 'bar' },
            ],
        }).then(() => {
            return cy.createDefaultFixture('rule', {
                name: 'Foobar',
                paymentMethods: [
                    { name: 'baz' },
                ],
            });
        })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/rule/index`);
            });
    });

    it('@base @rule: sort rules by assignment counts', { tags: ['pa-business-ops'] }, () => {
        const page = new RulePageObject();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/rule`,
            method: 'POST',
        }).as('loadData');

        cy.get('.sw-data-grid-skeleton').should('exist');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        // filter by payment method assigned
        cy.get('.sw-sidebar-navigation-item[title="Filters"]').click();
        cy.get('#assignments .sw-multi-select').typeMultiSelectAndCheck('Payment methods');

        // wait for Data to be loaded
        cy.wait('@loadData').its('response.statusCode').should('equal', 200);
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('be.visible');
        cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--name`).should('be.visible');
        cy.get(`${page.elements.dataGridRow}--2 .sw-data-grid__cell--name`).should('not.exist');

        cy.get('.sw-sidebar-navigation-item[title="Filters"]').click();

        // open grid settings context menu
        cy.get('.sw-data-grid-settings__trigger').click();

        // check items for columns of assignments exist
        cy.contains('.sw-data-grid__settings-column-item', 'Product price assignments');
        cy.contains('.sw-data-grid__settings-column-item', 'Promotion cart rule assignments');
        cy.contains('.sw-data-grid__settings-column-item', 'Promotion order rule assignments');
        cy.contains('.sw-data-grid__settings-column-item', 'Promotion customer rule assignments');
        cy.contains('.sw-data-grid__settings-column-item', 'Promotion set group rule assignments');
        cy.contains('.sw-data-grid__settings-column-item', 'Promotion discount product rule assignments');
        cy.contains('.sw-data-grid__settings-column-item', 'Flow assignments');
        cy.contains('.sw-data-grid__settings-column-item', 'Product price assignments');
        cy.contains('.sw-data-grid__settings-column-item', 'Shipping method price matrix assignments');
        cy.contains('.sw-data-grid__settings-column-item', 'Shipping method price assignments');
        cy.contains('.sw-data-grid__settings-column-item', 'Shipping method assignments');
        // enable column for payment method assignments
        cy.contains('.sw-data-grid__settings-column-item', 'Payment method assignments').click();

        // sort ascending
        cy.contains('.sw-data-grid__cell--sortable', 'Payment method assignments').click();

        // wait for Data to be loaded
        cy.wait('@loadData').its('response.statusCode').should('equal', 200);
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Foobar');
        cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--name`).should('be.visible');
        cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--name`).contains('Ruler');

        // sort descending
        cy.contains('.sw-data-grid__cell--sortable', 'Payment method assignments').click();

        // wait for Data to be loaded
        cy.wait('@loadData').its('response.statusCode').should('equal', 200);
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Ruler');
        cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--name`).should('be.visible');
        cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--name`).contains('Foobar');
    });
});
