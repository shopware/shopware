// / <reference types="Cypress" />

import ShippingPageObject from '../../../../support/pages/module/sw-shipping.page-object';

describe('Shipping: Test acl privileges', () => {
    beforeEach(() => {
        cy.createShippingFixture()
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@settings: read shipping method', { tags: ['pa-checkout', 'VUE3'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'shipping',
                role: 'viewer',
            },
        ]);

        cy.visit(`${Cypress.env('admin')}#/sw/settings/shipping/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // open shipping
        cy.contains('.sw-data-grid__cell-value', 'Luftpost').click();

        // verify fields
        cy.get('#sw-field--shippingMethod-name').should('have.value', 'Luftpost');
        cy.contains('.sw-settings-shipping-detail__top-rule', 'Cart >= 0 (Payment)');
    });

    it('@settings: edit shipping method', { tags: ['pa-checkout', 'VUE3'] }, () => {
        const page = new ShippingPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'shipping',
                role: 'viewer',
            },
            {
                key: 'shipping',
                role: 'editor',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/shipping/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/shipping-method/*`,
            method: 'PATCH',
        }).as('saveData');

        // open shipping
        cy.contains('.sw-data-grid__cell-value', 'Luftpost').click();

        // edit fields
        cy.get('#sw-field--shippingMethod-name').clearTypeAndCheck('Schiffspost');
        cy.get('.sw-settings-shipping-detail__top-rule').typeSingleSelect(
            'All customers',
            '.sw-settings-shipping-detail__top-rule',
        );

        // save shipping method
        cy.get(page.elements.shippingSaveAction).click();

        // Verify shipping method
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);
    });

    it('@settings: create shipping method', { tags: ['pa-checkout', 'VUE3'] }, () => {
        const page = new ShippingPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'shipping',
                role: 'viewer',
            },
            {
                key: 'shipping',
                role: 'editor',
            },
            {
                key: 'shipping',
                role: 'creator',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/shipping/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/shipping-method`,
            method: 'POST',
        }).as('saveData');

        // Create shipping method
        cy.get('a[href="#/sw/settings/shipping/create"]').click();
        page.createShippingMethod('Automated test shipping');
        cy.get(page.elements.shippingSaveAction).click();

        // Verify shipping method
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.smartBarBack).click({ force: true });
        cy.contains(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`, 'Automated test shipping')
            .should('be.visible');
    });

    it('@settings: delete shipping method', { tags: ['pa-checkout', 'VUE3'] }, () => {
        const page = new ShippingPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'shipping',
                role: 'viewer',
            },
            {
                key: 'shipping',
                role: 'deleter',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/shipping/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/shipping-method/*`,
            method: 'delete',
        }).as('deleteData');

        cy.setEntitySearchable('shipping_method', 'name');

        cy.get('.sw-settings-shipping-list').should('be.visible');
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Luftpost');
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.get('.sw-modal__body').should('be.visible');
        cy.contains('.sw-modal__body', 'Are you sure you really want to delete the shipping method "Luftpost"?');
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();
        cy.get(page.elements.modal).should('not.exist');

        cy.wait('@deleteData').its('response.statusCode').should('equal', 204);

        cy.awaitAndCheckNotification('Shipping method "Luftpost" has been deleted.');
    });
});
