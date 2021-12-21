/// <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('activate wishlist and add product before update', ()=>{

    beforeEach(() => {
        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`
                },
                method: 'post',
                url: `api/_action/system-config/batch`,
                body: {
                    null: {
                        'core.cart.wishlistEnabled': true
                    }
                }
            };

            return cy.request(requestConfig);
        });

        cy.setLocaleToEnGb().then(() => {
            cy.loginViaApi();
        });
    });

    it('@pre-update should add to wishlist', ()=>{

        const page = new ProductPageObject();
        cy.createProductFixture({
            "id": "6dfd9dc216ab4ac99598b837ac600369",
            "name": "Wishlist Test",
            "stock": 1,
            "productNumber": "WS-01",
            "descriptionLong": "Product description",
            "price": [
                {
                    "currencyId": "b7d2554b0ce847cd82f3ac9bd1c0dfca",
                    "net": 8.40,
                    "linked": false,
                    "gross": 10
                }
            ],
            "url": "/product-name.html",
            "manufacturer": {
                "id": "b7d2554b0ce847cd82f3ac9bd1c0dfca",
                "name": "Wishlist maker"
            },
        });

        // add saleschannel and category
        cy.visit(`${Cypress.env('admin')}#/sw/product/detail/6dfd9dc216ab4ac99598b837ac600369/base`);
        cy.get('.sw-product-detail__select-visibility') .scrollIntoView();
        cy.get('.sw-product-detail__select-visibility').typeMultiSelectAndCheck('Footwear');
        cy.get('.sw-product-detail__select-visibility .sw-select-selection-list__input').type('{esc}');
        cy.get('.sw-category-tree__input-field').type('Angebote');
        cy.get('.sw-category-tree-field__search-results').contains('Angebote').click();
        cy.get('.sw-container.sw-product-feature-set-form__description .sw-inherit-wrapper__inheritance-label').click();
        cy.get(page.elements.productSaveAction).contains('Speichern').click();

        // change theme to display wishlist
        cy.intercept({
            url: 'api/search/theme',
            method: 'POST',
            body: {}
        }).as('saveData');
        cy.visit(`${Cypress.env('admin')}#/sw/sales/channel/detail/e15dac9d0d53401987b4024b32ec1c5c/theme`);
        cy.get('.sw-theme-list-item__image').click();
        cy.get('.sw-container > :nth-child(1) > .sw-theme-list-item > .sw-theme-list-item__image').click();
        cy.get('.sw-modal__footer > .sw-button--primary > .sw-button__content').click();
        cy.get('.sw-button--primary.sw-button--small .sw-button__content').click();

        cy.wait('@saveData').its('response.statusCode').should('equal', 200);
        cy.get('.sw-loader').should('not.exist');

        // login
        cy.visit('/account/login').reload();
        cy.get('.login-card').should('be.visible');
        cy.get('#loginMail').typeAndCheckStorefront('markus.stein@test.com');
        cy.get('#loginPassword').typeAndCheckStorefront('shopware');
        cy.get('.login-submit [type="submit"]').click();

        // wait for product added to wishlist
        cy.intercept({
            url: 'wishlist/add/6dfd9dc216ab4ac99598b837ac600369',
            method: 'POST',
            body: {}
        }).as('dataRequest');

        // search product and add to wishlist
        let productName = 'Wishlist Test'
        cy.get('.header-search-input').should('be.visible').type(productName);
        cy.contains('.search-suggest-product-name', productName).click();
        cy.get('.text-wishlist-not-added').contains('Zum Merkzettel hinzufügen').should('be.visible');
        cy.get('.text-wishlist-not-added').click();
        cy.get('.text-wishlist-remove').contains('Vom Merkzettel entfernen').should('be.visible');

        // confirm wishlist request
        cy.wait('@dataRequest').its('response.statusCode').should('equal', 200);
        cy.get('[data-wishlist-storage]').contains('1').should('be.visible')

        // confirm product in wishlist
        cy.visit('/wishlist');
        cy.get('.card-body-wishlist').contains(productName).should('be.visible');
    });
});
