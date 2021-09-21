// / <reference types="Cypress" />

import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Customer groups: Test acl privileges', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('customer-group');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@settings: can view customer groups', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'customer_groups',
                role: 'viewer'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/customer/group/index`);
        });

        cy.get('.sw-settings-customer-group-list').should('be.visible');

        // click on first element in grid
        cy.get(`${page.elements.dataGridRow}--0`)
            .contains('Chuck-Testers')
            .click();

        // check if values are visible
        cy.get('#sw-field--customerGroup-name').should('have.value', 'Chuck-Testers');
        cy.get('input#sw-field--castedValue-0').should('be.checked');
        cy.get('input[name="sw-field--customerGroup-registrationActive"]').should('not.be.checked');
    });

    it('@settings: can edit customer group', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'customer_groups',
                role: 'viewer'
            },
            {
                key: 'customer_groups',
                role: 'editor'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/customer/group/index`);
        });

        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/customer-group/*`,
            method: 'PATCH'
        }).as('updateCustomerGroup');

        // click on first element in grid
        cy.get(`${page.elements.dataGridRow}--0`)
            .contains('Chuck-Testers')
            .click();

        // edit name
        cy.get('#sw-field--customerGroup-name').clear().type('Net price');

        // edit gross
        cy.get('input#sw-field--castedValue-1').check();

        cy.get('input[name="sw-field--customerGroup-registrationActive"]').check();

        cy.get('#sw-field--customerGroup-registrationTitle').type('Registration');

        // Set sales channel
        cy.get('.sw-settings-customer-group-detail__sales-channel')
            .scrollIntoView();
        cy.get('.sw-settings-customer-group-detail__sales-channel').typeMultiSelectAndCheck('Storefront');
        cy.get('.sw-settings-customer-group-detail__sales-channel .sw-select-selection-list__input')
            .type('{esc}');

        // save customer group
        cy.get(page.elements.customerGroupSaveAction).click();

        // Verify creation
        cy.wait('@updateCustomerGroup').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.smartBarBack).click();
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Net price');
        cy.get('.sw-settings-customer-group-list').should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible')
            .contains('Net price');
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.customerGroupColumnTaxDisplay}`).should('be.visible')
            .contains('Net');
    });

    it('@settings: can create customer group', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'customer_groups',
                role: 'viewer'
            },
            {
                key: 'customer_groups',
                role: 'editor'
            },
            {
                key: 'customer_groups',
                role: 'creator'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/customer/group/index`);
        });

        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/customer-group`,
            method: 'POST'
        }).as('createCustomerGroup');

        // Create customer group
        cy.get('a[href="#/sw/settings/customer/group/create"]').click();

        // Fill all fields
        cy.get('#sw-field--customerGroup-name').type('VIP');
        cy.get('input#sw-field--castedValue-1').check();
        cy.get('input[name="sw-field--customerGroup-registrationActive"]').check();
        cy.get('#sw-field--customerGroup-registrationTitle').type('VIP Registration');

        // Set sales channel
        cy.get('.sw-settings-customer-group-detail__sales-channel')
            .scrollIntoView();
        cy.get('.sw-settings-customer-group-detail__sales-channel').typeMultiSelectAndCheck('Headless');
        cy.get('.sw-settings-customer-group-detail__sales-channel .sw-select-selection-list__input')
            .type('{esc}');

        cy.get(page.elements.customerGroupSaveAction).click();

        // Verify creation
        cy.wait('@createCustomerGroup').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.smartBarBack).click();
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('VIP');
        cy.get('.sw-settings-customer-group-list').should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.customerGroupColumnName}`).contains('VIP');
    });

    it('@settings: can delete customer group', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'customer_groups',
                role: 'viewer'
            },
            {
                key: 'customer_groups',
                role: 'deleter'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/customer/group/index`);
        });

        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/customer-group/*`,
            method: 'delete'
        }).as('deleteCustomerGroup');

        // filter customer group via search bar
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Chuck-Testers');

        // Delete customer group
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get('.sw-modal__body').should('be.visible');
        cy.get('.sw-modal__body')
            .contains('Are you sure you want to delete this item?');
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();

        // Verify deletion
        cy.wait('@deleteCustomerGroup').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.modal).should('not.exist');
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.customerGroupColumnName}`).should('not.exist');
    });
});
