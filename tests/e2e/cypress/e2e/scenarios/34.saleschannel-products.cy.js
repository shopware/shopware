/// <reference types="Cypress" />
/**
 * @package buyers-experience
 */

describe('Add and remove products from saleschannel', () => {
    beforeEach(() => {
        cy.createProductFixture();
    });

    afterEach(() => {
        // Remove product from the sales channel
        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);
        cy.get('.sw-dashboard-index__welcome-title').should('be.visible');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains(Cypress.env('storefrontName')).click();

        cy.contains('.sw-card__title', 'Algemene instellingen').should('be.visible');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-tabs-item[title="Producten"]').click();

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-data-grid__row--0')
            .should('be.visible');
        cy.get('.sw-data-grid__actions-menu').click();
        cy.contains('.sw-context-menu__content', 'Verwijderen').click();
        cy.contains('Geen producten meer gevonden').should('be.visible');

        // Verify product not exist in the storefront
        cy.visit('/');
        cy.get('.header-search-input').should('be.visible').type('Product name');
        cy.contains('.search-suggest-container', 'No results found').should('be.visible');
    });

    it('@package: should add via product selection', { tags: ['pa-sales-channels'] }, () => {
        // Add product from the sales channel
        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains(Cypress.env('storefrontName')).click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-tabs-item[title="Producten"]').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-button.sw-button--ghost').click();
        cy.contains('.sw-modal__header', 'Producten toevoegen').should('be.visible');
        cy.get('.sw-data-grid__select-all [type]').check();
        cy.contains('.sw-data-grid__bulk', '1').should('be.visible');
        cy.get('.sw-button.sw-button--primary.sw-button--small').click();
        cy.contains('.sw-data-grid__cell--name', 'Product name');

        // Verify product in the storefront
        cy.visit('/');
        cy.get('.header-search-input').should('be.visible').type('Product name');
        cy.contains('.search-suggest-product-name', 'Product name').should('be.visible');
    });

    it('@package: should add via category selection', { tags: ['pa-sales-channels'] }, () => {
        // Add product to the home
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-tree-item__label', 'Home').click();
        cy.get('.sw-tabs-item[title="Producten"]').click();
        cy.get('input[placeholder="Producten zoeken en toewijzen …"]').click();
        cy.contains('.sw-select-result-list__content', 'Product name').click();

        // Add product to sales channel
        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);
        cy.get('.sw-dashboard-index__welcome-title').should('be.visible');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains(Cypress.env('storefrontName')).click();

        cy.contains('.sw-card__title', 'Algemene instellingen').should('be.visible');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-tabs-item[title="Producten"]').click();

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-button', 'producten toevoegen').click();

        cy.contains('.sw-modal__header', 'Producten toevoegen').should('be.visible');
        cy.get('[title="Categorie selectie"]').click();
        cy.get('.sw-tree-item__selection [type]').click();
        cy.get('.sw-button.sw-button--primary.sw-button--small').click();

        // Verify product in the storefront
        cy.visit('/');
        cy.get('.header-search-input').should('be.visible').type('Product name');
        cy.contains('.search-suggest-product-name', 'Product name').should('be.visible');
    });

    it('@package: should add and remove via product group selection', { tags: ['pa-sales-channels'] }, () => {
        // Add product to a dynamic product group
        cy.visit(`${Cypress.env('admin')}#/sw/product/stream/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-product-stream-list__create-action').click();

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('input[name=sw-field--productStream-name]').typeAndCheck('Dynamic Products');
        cy.get('[class="sw-product-stream-value sw-product-stream-value--grow-2"] .sw-entity-single-select__selection').click();

        cy.contains('.sw-select-result-list__content', 'Product name').click();
        cy.get('.sw-button.sw-button--primary.sw-product-stream-detail__save-action').click();

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-button-process__content', 'Opslaan').should('be.visible');

        // Add product to sales channel
        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains(Cypress.env('storefrontName')).click();

        cy.contains('.sw-card__title', 'Algemene instellingen').should('be.visible');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-tabs-item[title="Producten"]').click();

        cy.contains('.sw-empty-state__title', 'Geen producten meer gevonden').should('be.visible');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-button.sw-button--ghost').click();

        cy.contains('.sw-modal__header', 'Producten toevoegen').should('be.visible');
        cy.contains('.sw-button', '0 producten toevoegen').should('be.visible');
        cy.contains('.sw-data-grid__row--0', 'Product name').should('be.visible');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.contains('.sw-button', 'Annuleren').click();
        cy.get('.sw-modal').should('not.exist');
        cy.get('.sw-button.sw-button--ghost').click();

        cy.contains('.sw-modal__header', 'Producten toevoegen').should('be.visible');
        cy.contains('.sw-button', '0 producten toevoegen').should('be.visible');
        cy.contains('.sw-data-grid__row--0', 'Product name').should('be.visible');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('[title="Selectie van productgroepen"]').click();
        cy.get('.sw-alert__message').should('be.visible');

        cy.get('.sw-data-grid--actions .sw-data-grid__header [type]').check();
        cy.contains('.sw-data-grid__bulk', '1').should('be.visible');
        cy.get('.sw-button.sw-button--primary.sw-button--small').click();
        cy.contains('.sw-data-grid__cell--name', 'Product name');

        // Verify product in the storefront
        cy.visit('/');
        cy.get('.header-search-input').should('be.visible').type('Product name');
        cy.contains('.search-suggest-product-name', 'Product name').should('be.visible');
    });
});
