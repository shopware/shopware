/// <reference types="Cypress" />
/**
 * @package buyers-experience
 */

describe('Sales Channel: create product, change currency and language', () => {
    beforeEach(() => {
        cy.createProductFixture().then(() => {
            cy.openInitialPage(Cypress.env('admin'));
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@package: create sales channel', { tags: ['pa-sales-channels'] }, () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/sales-channel-domain`,
            method: 'POST',
        }).as('verifyDomain');
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/product-visibility`,
            method: 'POST',
        }).as('editProduct');
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/sales-channel`,
            method: 'POST',
        }).as('editSalesChannel');

        // Configure sales channel
        cy.goToSalesChannelDetail(Cypress.env('storefrontName'))
            .selectCountryForSalesChannel('Germany')
            .selectLanguageForSalesChannel('Deutsch')
            .selectCurrencyForSalesChannel('US-Dollar');

        // Add NL domain
        cy.get('.sw-card.sw-card--grid.sw-sales-channel-detail-domains').scrollIntoView();
        cy.get('.sw-sales-channel-detail__button-domain-add').contains('Domein toevoegen').click();
        cy.contains('.sw-modal__title', 'Nieuw domain maken');
        cy.get('.sw-field__url-input__prefix').click();
        cy.get('.sw-url-input-field__input').type('localhost:8000/nl');
        cy.get('.sw-sales-channel-detail-domains__domain-language-select').find('.sw-single-select__selection').click();
        cy.get('.sw-select-result').contains('Dutch').click();
        cy.get('.sw-sales-channel-detail-domains__domain-currency-select').find('.sw-single-select__selection').click();
        cy.contains('.sw-select-result', 'Euro').click();
        cy.contains('.sw-entity-single-select', 'Tekstfragment').find('.sw-entity-single-select__selection').click();
        cy.contains('.sw-select-result', 'LanguagePack nl-NL').click();
        cy.contains('.sw-button--primary', 'Domein toevoegen').click();
        cy.wait('@verifyDomain').its('response.statusCode').should('equal', 200);
        cy.get('.sw-sales-channel-detail-domains .sw-data-grid__body')
            .find('.sw-data-grid__row').should('have.length', 3);

        // Add DE domain
        cy.get('.sw-card.sw-card--grid.sw-sales-channel-detail-domains').scrollIntoView();
        cy.get('.sw-sales-channel-detail__button-domain-add').contains('Domein toevoegen').click();
        cy.contains('.sw-modal__title', 'Nieuw domain maken');
        cy.get('.sw-field__url-input__prefix').click();
        cy.get('.sw-url-input-field__input').type('localhost:8000/de');
        cy.get('.sw-sales-channel-detail-domains__domain-language-select').find('.sw-single-select__selection').click();
        cy.contains('.sw-select-result', 'Deutsch').click();
        cy.get('.sw-sales-channel-detail-domains__domain-currency-select').find('.sw-single-select__selection').click();
        cy.contains('.sw-select-result', 'Euro').click();
        cy.contains('.sw-entity-single-select', 'Tekstfragment').find('.sw-entity-single-select__selection').click();
        cy.contains('.sw-select-result', 'BASE de-DE').click();
        cy.contains('.sw-button--primary', 'Domein toevoegen').click();
        cy.wait('@verifyDomain').its('response.statusCode').should('equal', 200);
        cy.get('.sw-sales-channel-detail-domains .sw-data-grid__body')
            .find('.sw-data-grid__row').should('have.length', 4);

        // Add product
        cy.get('.sw-tabs-item[title="Producten"]').scrollIntoView().click();
        cy.get('.sw-button.sw-button--ghost').click();
        cy.get('.sw-data-grid__body .sw-data-grid__cell--selection .sw-data-grid__cell-content').click();
        cy.get('.sw-data-grid__bulk-selected-label').should('include.text', 'Geselecteerd');
        cy.get('.sw-button.sw-button--primary.sw-button--small').click();
        cy.wait('@editProduct').its('response.statusCode').should('equal', 204);

        // Save sales channel
        cy.get('.sw-button-process.sw-sales-channel-detail__save-action').click();
        cy.wait('@editSalesChannel').its('response.statusCode').should('equal', 200);

        // Storefront
        cy.visit('/nl');
        cy.get('.header-search-input').type('Product name');
        cy.contains('.search-suggest-product-name', 'Product name').click();
        cy.contains('.product-detail-price', '€ 49,98*');
        cy.get('.product-detail-buy .btn-buy').contains('In het winkelmandje').click();

        // Off canvas
        cy.get('.offcanvas').should('be.visible');
        cy.contains('.line-item-total-price-value', '€ 49,98*').should('be.visible');
        cy.get('a[title="Ga naar de kassa"]').should('be.visible');
        cy.contains('Doorgaan met winkelen').click();

        // Change currency
        cy.get('#currenciesDropdown-top-bar').click();
        cy.get('.dropdown-menu-end.show.top-bar-list').contains('$ USD').click();
        cy.contains('#currenciesDropdown-top-bar', '$ US-Dollar');

        // Change language
        cy.get('#languagesDropdown-top-bar').click();
        cy.get('.dropdown-menu-end.show.top-bar-list').contains('Deutsch').click();
        cy.url().should('include', '/de');
        cy.contains('#languagesDropdown-top-bar', 'Deutsch');

        // Verify currency and language on detail page
        cy.contains('.product-detail-price', '58,52 $*');
        cy.contains('.product-detail-buy .btn-buy', 'In den Warenkorb').should('be.visible');

        // Verify currency and language on canvas
        cy.get('.header-cart-icon').click({ force: true });
        cy.get('.offcanvas').should('be.visible');
        cy.contains('.line-item-total-price-value', '58,52 $*').should('be.visible');
        cy.get('a[title="Zur Kasse"]').should('be.visible');
    });
});
