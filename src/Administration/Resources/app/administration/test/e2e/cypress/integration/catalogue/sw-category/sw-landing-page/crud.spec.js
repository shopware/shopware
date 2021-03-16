// / <reference types="Cypress" />

describe('Category: Landing pages', () => {
    beforeEach(() => {
        // Clean previous state and prepare Administration
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
                cy.createDefaultFixture('cms-page', {}, 'cms-landing-page');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/category/index`);
            });
    });

    it('@catalogue: create a landing page and check storefront behavior', () => {
        cy.server();
        cy.route('POST', `${Cypress.env('apiPath')}/search/landing-page`).as('loadLandingPages');
        cy.route('POST', `${Cypress.env('apiPath')}/landing-page`).as('saveLandingPage');

        cy.get('.sw-category-detail__landing-page-collapse .sw-sidebar-collapse__indicator')
            .click();

        cy.wait('@loadLandingPages');
        cy.get('.sw-landing-page-tree__add-button a').click();

        // fill in information
        cy.get('#landingPageName').typeAndCheck('MyLandingPage');
        cy.get('input[name="landingPageActive"]').check();
        cy.get('.sw-landing-page-detail-base__sales_channel').typeMultiSelectAndCheck('Storefront');
        cy.get('#sw-field--landingPage-metaTitle').typeAndCheck('MyLandingPage-MetaTitle');
        cy.get('#sw-field--landingPage-metaDescription').typeAndCheck('MyLandingPage-MetaDescription');
        cy.get('#sw-field--landingPage-keywords').typeAndCheck('MyLandingPage-SeoKeyword MyLandingPage-AnotherSeoKeyword');
        cy.get('#sw-field--landingPage-url').typeAndCheck('my-landing-page');

        // assign layout
        cy.get('.sw-landing-page-detail__tab-cms').click();
        cy.get('.sw-category-detail-layout__change-layout-action').click();
        cy.get('.sw-cms-layout-modal__content-item--0 input[type="checkbox"]').click();
        cy.contains('.sw-modal__footer .sw-button', 'Save').click();

        // save
        cy.get('.sw-category-detail__save-landing-page-action').click();
        cy.wait('@saveLandingPage');

        // verify changes
        cy.route('POST', `${Cypress.env('apiPath')}/search/landing-page`).as('loadLandingPage');
        cy.reload();
        cy.wait('@loadLandingPage');
        cy.get('#landingPageName').should('have.value', 'MyLandingPage');
        cy.get('input[name="landingPageActive"]').should('be.checked');
        cy.get('.sw-landing-page-detail-base__sales_channel').should('contain', 'Storefront');
        cy.get('#sw-field--landingPage-metaTitle').should('have.value', 'MyLandingPage-MetaTitle');
        cy.get('#sw-field--landingPage-metaDescription').should('have.value', 'MyLandingPage-MetaDescription');
        cy.get('#sw-field--landingPage-keywords').should('have.value', 'MyLandingPage-SeoKeyword MyLandingPage-AnotherSeoKeyword');
        cy.get('#sw-field--landingPage-url').should('have.value', 'my-landing-page');

        // find entry in landing page tree
        cy.contains('.sw-landing-page-tree .sw-tree-item__content', 'MyLandingPage').should('be.visible');

        // check storefront
        let editPage = '';
        cy.url().then(urlString => {
            editPage = urlString;
        });
        cy.visit('/my-landing-page');
        cy.get('head title').should('contain', 'MyLandingPage-MetaTitle');
        cy.get('head meta[name="description"]').should('have.attr', 'content', 'MyLandingPage-MetaDescription');
        cy.get('head meta[name="keywords"]').should('have.attr', 'content', 'MyLandingPage-SeoKeyword MyLandingPage-AnotherSeoKeyword');
        cy.contains('.cms-page', 'Baumhaus landing page').should('be.visible');

        // disable landing page
        cy.route('POST', `${Cypress.env('apiPath')}/search/landing-page`).as('loadLandingPageForEdit');
        cy.then(() => {
            cy.visit(editPage);
        });
        cy.wait('@loadLandingPageForEdit');
        cy.get('input[name="landingPageActive"]').uncheck();
        cy.route('PATCH', `${Cypress.env('apiPath')}/landing-page/*`).as('patchLandingPage');
        cy.get('.sw-category-detail__save-landing-page-action').click();
        cy.wait('@patchLandingPage');

        // check for invalid storefront
        cy.visit('/my-landing-page', { failOnStatusCode: false });
        cy.contains('Page not found').should('be.visible');
    });
});
