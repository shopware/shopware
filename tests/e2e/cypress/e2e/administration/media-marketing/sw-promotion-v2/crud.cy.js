/**
 * @package checkout
 */
// / <reference types="Cypress" />

import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

const multiSelectFirstSelector = '.sw-select-selection-list__item-holder--0';

describe('Promotion v2: Test crud operations', () => {
    beforeEach(() => {
        cy.createDefaultFixture('promotion').then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/promotion/v2/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@base @marketing: create, update and read promotion', { tags: ['pa-checkout'] }, () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/promotion`,
            method: 'POST',
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

        // Test the sw-entity-advanced-selection-modal
        cy.get('.sw-promotion-v2-conditions__rule-select-customer .sw-select-selection-list li').should('have.length', 1);
        cy.get('.sw-promotion-v2-conditions__rule-select-customer').click();
        cy.get('.sw-many-to-many-select-filtering__advanced-selection').click();
        cy.get('.sw-data-grid__row:contains("All customers") .sw-field--checkbox:not(.is--disabled):not(.sw-data-grid__select-all)').click();
        cy.get('.sw-entity-advanced-selection-modal__button-apply').click();
        cy.get('.sw-promotion-v2-conditions__rule-select-customer .sw-select-selection-list li').should('have.length', 2);
        cy.get('.sw-promotion-v2-conditions__rule-select-customer .sw-select-selection-list li').contains('All customers');

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
        cy.contains(`.sw-promotion-v2-conditions__sales-channel-selection ${multiSelectFirstSelector}`,
            'Storefront');
        cy.contains(`.sw-promotion-v2-conditions__rules-exclusion-selection ${multiSelectFirstSelector}`,
            'Thunder Tuesday');

        cy.get('.sw-promotion-v2-conditions__rule-select-customer .sw-select-selection-list li').should('have.length', 2);
        cy.contains(`.sw-promotion-v2-conditions__rule-select-customer ${multiSelectFirstSelector}`,
            'All customers');
        cy.contains(`.sw-promotion-v2-cart-condition-form__rule-select-cart ${multiSelectFirstSelector}`,
            'Always valid (Default)');
        cy.contains(`.sw-promotion-v2-conditions__rule-select-order-conditions ${multiSelectFirstSelector}`,
            'All customers');

        // Verify Set-Group
        cy.contains(`${groupSelector}#sw-field--group-packagerKey`, 'Amount (net)');
        cy.get(`${groupSelector}.sw-promotion-v2-cart-condition-form__setgroup-value input`)
            .should('contain.value', '5.5');
        cy.contains(`${groupSelector}#sw-field--group-sorterKey`,
            'Price, descending');
        cy.contains(`${groupSelector}.sw-promotion-v2-cart-condition-form__setgroup-rules ${multiSelectFirstSelector}`,
            'Always valid (Default)');

        // Configure Discounts
        cy.get('.sw-tabs-item[title="Discounts"]')
            .should('not.have.class', 'sw-tabs-item--active')
            .click()
            .should('have.class', 'sw-tabs-item--active');

        cy.get('.sw-promotion-discount-component')
            .should('not.exist');
        cy.get('.promotion-detail-discounts__action_add button')
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
        cy.contains('#sw-field--discount-scope', 'Set group-1');
        cy.contains('#sw-field--discount-type', 'Absolute');
        cy.get('.sw-promotion-discount-component__discount-value input')
            .should('have.value', 10.5);

        // Verify promotion in listing
        cy.get('.sw-promotion-v2-detail__cancel-action').click();

        cy.contains('.sw-data-grid__cell--name > .sw-data-grid__cell-content', 'Funicular prices');
        cy.get('.sw-data-grid__cell--active > .sw-data-grid__cell-content > span').should('have.class', 'is--active');
        cy.contains('.sw-data-grid__cell--validFrom > .sw-data-grid__cell-content', '1 January 2222 at 00:00');
        cy.contains('.sw-data-grid__cell--validUntil > .sw-data-grid__cell-content', '2 February 2222 at 00:00');
    });

    it('@base @marketing: create promotion in non system language', { tags: ['pa-checkout'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/promotion`,
            method: 'POST',
        }).as('getData');

        cy.waitFor('.sw-language-switch');

        cy.get('.sw-language-switch__select').typeSingleSelectAndCheck('Deutsch', '.sw-language-switch__select');
        cy.wait('@getData').its('response.statusCode').should('equal', 200);

        cy.waitFor('.sw-promotion-v2-list__smart-bar-button-add');
        cy.get('.sw-promotion-v2-list__smart-bar-button-add').click();

        cy.get('.sw_language-info__info').should('be.visible');
        cy.get('.sw_language-info__info').contains('"New promotion" is displayed in the system default language. Always maintain new data in your chosen system default language.');

        cy.get('.sw-language-switch__select').should('have.class', 'is--disabled');
    });

    it('@base @marketing: delete promotion', { tags: ['pa-checkout'] }, () => {
        const page = new ProductPageObject();
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/promotion/*`,
            method: 'delete',
        }).as('deleteData');

        // Delete product
        cy.clickContextMenuItem(
            '.sw-context-menu-item--danger',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );
        cy.contains(`${page.elements.modal} .sw-listing__confirm-delete-text`,
            'Are you sure you want to delete this item?');
        cy.get(`${page.elements.modal}__footer ${page.elements.dangerButton}`).click();

        // Verify updated product
        cy.wait('@deleteData').its('response.statusCode').should('equal', 204);
        cy.get('.sw-sidebar-navigation-item[title="Refresh"]').click();
        cy.get('.sw-skeleton__listing').should('not.exist');
        cy.get('.sw-promotion-v2-empty-state-hero').should('be.visible');
    });
});
