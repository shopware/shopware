// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

const multiSelectFirstSelector = '.sw-select-selection-list__item-holder--0';

describe('Promotion v2: Test crud operations', () => {
    beforeEach(() => {
        cy.setToInitialState().then(() => {
            cy.loginViaApi();
        }).then(() => {
            return cy.createDefaultFixture('promotion');
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/promotion/v2/index`);
        });
    });

    it('@base @marketing: create, update and read promotion', () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/promotion`,
            method: 'POST'
        }).as('saveData');

        cy.waitFor('.sw-promotion-v2-list__smart-bar-button-add');
        cy.get('.sw-promotion-v2-list__smart-bar-button-add').click();

        // Create promotion
        cy.get('.sw-promotion-v2-detail').should('be.visible');
        cy.get('#sw-field--promotion-name').typeAndCheck('Funicular prices');
        cy.get('input[name="sw-field--promotion-active"]').click();
        cy.get('.sw-promotion-v2-detail__save-action').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.location('hash').should((hash) => {
            expect(hash).to.contain('#/sw/promotion/v2/detail/');
        });

        cy.get('.sw-loader').should('not.exist');

        cy.get('#sw-field--promotion-validFrom + input')
            .click()
            .type('2222-01-01{enter}');

        cy.get('#sw-field--promotion-validUntil + input')
            .click()
            .type('2222-02-02{enter}');

        cy.get('#sw-field--promotion-maxRedemptionsGlobal').type('1');

        cy.get('.sw-promotion-v2-detail__save-action').click();
        cy.get('.sw-loader').should('not.exist');

        // Verify promotion on detail page
        cy.get('#sw-field--promotion-name').should('have.value', 'Funicular prices');
        cy.get('input[name="sw-field--promotion-active"]').should('be.checked');
        cy.get('#sw-field--promotion-validFrom + input').should('contain.value', '2222-01-01');
        cy.get('#sw-field--promotion-validUntil + input').should('contain.value', '2222-02-02');
        cy.get('#sw-field--promotion-maxRedemptionsGlobal').should('have.value', '1');
        cy.get('#sw-field--promotion-maxRedemptionsPerCustomer')
            .should('be.empty')
            .should('have.attr', 'placeholder', 'Unlimited');

        // Configure Conditions
        cy.get('.sw-tabs-item[title="Conditions"]')
            .should('not.have.class', 'sw-tabs-item--active')
            .click()
            .should('have.class', 'sw-tabs-item--active');

        cy.get('.sw-promotion-v2-conditions__sales-channel-selection')
            .typeMultiSelectAndCheck('Storefront');
        cy.get('.sw-promotion-v2-conditions__rules-exclusion-selection')
            .typeMultiSelectAndCheck('Thunder Tuesday');

        cy.get('.sw-promotion-v2-conditions__rule-select-customer')
            .typeMultiSelectAndCheck('All customers');
        cy.get('.sw-promotion-v2-conditions__rule-select-customer')
            .type('{esc}');
        cy.get('.sw-promotion-v2-cart-condition-form__rule-select-cart')
            .typeMultiSelectAndCheck('Always valid (Default)');
        cy.get('.sw-promotion-v2-conditions__rule-select-order-conditions')
            .typeMultiSelectAndCheck('All customers');

        // Configure Set-Group
        cy.get('.sw-promotion-v2-cart-condition-form__add-group-button')
            .should('not.exist');
        cy.get('.sw-promotion-v2-cart-condition-form__use-setgroups input')
            .click();
        cy.get('.sw-promotion-v2-cart-condition-form__add-group-button')
            .should('be.visible')
            .click();

        const groupSelector = '#sw-promotion-v2-cart-condition-form__setgroup-card-1 ';
        cy.get(`${groupSelector}#sw-field--group-packagerKey`)
            .select('Amount (net)');
        cy.get(`${groupSelector}.sw-promotion-v2-cart-condition-form__setgroup-value`)
            .type('{selectall}5.5');
        cy.get(`${groupSelector}#sw-field--group-sorterKey`)
            .select('Price, descending');
        cy.get(`${groupSelector}.sw-promotion-v2-cart-condition-form__setgroup-rules`)
            .typeMultiSelectAndCheck('Always valid (Default)');

        cy.get('.sw-promotion-v2-detail__save-action').click();
        cy.get('.sw-loader').should('not.exist');

        // Verify conditions
        cy.get(`.sw-promotion-v2-conditions__sales-channel-selection ${multiSelectFirstSelector}`)
            .contains('Storefront');
        cy.get(`.sw-promotion-v2-conditions__rules-exclusion-selection ${multiSelectFirstSelector}`)
            .contains('Thunder Tuesday');

        cy.get(`.sw-promotion-v2-conditions__rule-select-customer ${multiSelectFirstSelector}`)
            .contains('All customers');
        cy.get('.sw-promotion-v2-conditions__rule-select-customer');
        cy.get(`.sw-promotion-v2-cart-condition-form__rule-select-cart ${multiSelectFirstSelector}`)
            .contains('Always valid (Default)');
        cy.get(`.sw-promotion-v2-conditions__rule-select-order-conditions ${multiSelectFirstSelector}`)
            .contains('All customers');

        // Verify Set-Group
        cy.get(`${groupSelector}#sw-field--group-packagerKey`)
            .contains('Amount (net)');
        cy.get(`${groupSelector}.sw-promotion-v2-cart-condition-form__setgroup-value input`)
            .should('contain.value', '5.5');
        cy.get(`${groupSelector}#sw-field--group-sorterKey`)
            .contains('Price, descending');
        cy.get(`${groupSelector}.sw-promotion-v2-cart-condition-form__setgroup-rules ${multiSelectFirstSelector}`)
            .contains('Always valid (Default)');

        // Configure Discounts
        cy.get('.sw-tabs-item[title="Discounts"]')
            .should('not.have.class', 'sw-tabs-item--active')
            .click()
            .should('have.class', 'sw-tabs-item--active');

        cy.get('.sw-promotion-discount-component')
            .should('not.exist');
        cy.get('.sw-card--hero button')
            .click();
        cy.get('.sw-promotion-discount-component')
            .should('be.visible');

        cy.get('#sw-field--discount-scope')
            .select('Set group-1');
        cy.get('#sw-field--discount-type')
            .select('Absolute');
        cy.get('.sw-promotion-discount-component__discount-value input')
            .type('{selectall}10.5');

        cy.get('.sw-promotion-v2-detail__save-action').click();
        cy.get('.sw-loader').should('not.exist');

        // Verify discounts
        cy.get('.sw-promotion-discount-component')
            .should('be.visible');
        cy.get('#sw-field--discount-scope')
            .contains('Set group-1');
        cy.get('#sw-field--discount-type')
            .contains('Absolute');
        cy.get('.sw-promotion-discount-component__discount-value input')
            .should('have.value', 10.5);

        // Verify promotion in listing
        cy.get('.sw-promotion-v2-detail__cancel-action').click();

        cy.get('.sw-data-grid__cell--name > .sw-data-grid__cell-content').contains('Funicular prices');
        cy.get('.sw-data-grid__cell--active > .sw-data-grid__cell-content > span').should('have.class', 'is--active');
        cy.get('.sw-data-grid__cell--validFrom > .sw-data-grid__cell-content').contains('1 January 2222, 00:00');
        cy.get('.sw-data-grid__cell--validUntil > .sw-data-grid__cell-content').contains('2 February 2222, 00:00');
    });

    it('@base @marketing: delete promotion', () => {
        const page = new ProductPageObject();
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/promotion/*`,
            method: 'delete'
        }).as('deleteData');

        // Delete product
        cy.clickContextMenuItem(
            '.sw-context-menu-item--danger',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get(`${page.elements.modal} .sw-listing__confirm-delete-text`).contains(
            'Are you sure you want to delete this item?'
        );
        cy.get(`${page.elements.modal}__footer ${page.elements.dangerButton}`).click();

        // Verify updated product
        cy.wait('@deleteData').its('response.statusCode').should('equal', 204);
        cy.get('.sw-sidebar-navigation-item[title="Refresh"]').click();
        cy.get('.sw-data-grid__skeleton').should('not.exist');
        cy.get('.sw-promotion-v2-empty-state-hero').should('be.visible');
    });
});
