// / <reference types="Cypress" />

describe('Sales Channel: Test list', () => {
    beforeEach(() => {
        cy.createDefaultSalesChannel({
            'id': '00000000000000000000000000000001',
            'name': 'SalesChannel #1',
            'accessKey': 'SWSCWLRRZJR2ZE05VMYYVGT1W1',
            'maintenance': true,
        }).then(() => {
            return cy.createDefaultSalesChannel({
                'id': '00000000000000000000000000000002',
                'name': 'SalesChannel #2',
                'accessKey': 'SWSCWLRRZJR2ZE05VMYYVGT1W2',
                'maintenance': true,
            });
        }).then(() => {
            return cy.createDefaultSalesChannel({
                'id': '00000000000000000000000000000003',
                'name': 'SalesChannel #3',
                'accessKey': 'SWSCWLRRZJR2ZE05VMYYVGT1W3',
                'active': false,
            });
        }).then(() => {
            return cy.createDefaultSalesChannel({
                'id': '00000000000000000000000000000004',
                'name': 'SalesChannel #4',
                'accessKey': 'SWSCWLRRZJR2ZE05VMYYVGT1W4',
                'active': false,
            });
        }).then(() => {
            return cy.createProductFixture({
                'name': 'Product #1',
                'productNumber': 'SW001',
            });
        }).then(() => {
            cy.createProductFixture({
                'name': 'Product #2',
                'productNumber': 'SW002',
            });
        }).then(() => {
            return cy.createProductFixture({
                'name': 'Product #3',
                'productNumber': 'SW003',
            });
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/sales/channel/list`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@base @general: open listing page', { tags: ['pa-sales-channels'] }, () => {
        // check if listing works correctly
        cy.contains('.sw-page__smart-bar-amount', '6');

        // open sales channel
        cy.get('.sw-data-grid__row--0')
            .find('.sw-context-button__button')
            .click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.contains('.sw-context-menu-item', 'Edit')
            .click();
        cy.get('.sw-skeleton').should('not.exist');

        // check if sales channel was opened correctly
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');
        cy.contains('.smart-bar__header', 'Headless');
    });

    it('@general: Should show six sales-channels and 8 columns', { tags: ['pa-sales-channels'] }, () => {
        cy.get('.sw-data-grid__body .sw-data-grid__row').should('have.length', 6);
        cy.get('.sw-data-grid__header .sw-data-grid__cell').should('have.length', 8);
    });

    it('@general: Name should be sortable', { tags: ['pa-sales-channels'] }, () => {
        cy.onlyOnFeature('FEATURE_NEXT_17421');

        cy.log('change Sorting direction from None to ASC');
        cy.get('.sw-data-grid__cell--0 > .sw-data-grid__cell-content').click('right');

        cy.contains('.sw-data-grid__header .sw-data-grid__cell', 'Sales Channel').click('left');
        cy.get('.sw-skeleton').should('not.exist');
        cy.contains('.sw-data-grid__body .sw-data-grid__row--0 .sw-data-grid__cell--name', 'Headless');
        cy.contains('.sw-data-grid__body .sw-data-grid__row--3 .sw-data-grid__cell--name', 'SalesChannel #3');
        cy.contains('.sw-data-grid__body .sw-data-grid__row--5 .sw-data-grid__cell--name', 'Storefront');
    });

    it('@general: Type should be sortable', { tags: ['pa-sales-channels', 'jest'] }, () => {
        cy.contains('.sw-data-grid__header .sw-data-grid__cell', 'Type').click('left');
        cy.get('.sw-skeleton').should('not.exist');

        cy.contains('.sw-data-grid__body .sw-data-grid__row--0 .sw-data-grid__cell--type-name', 'Headless');
        cy.contains('.sw-data-grid__body .sw-data-grid__row--3 .sw-data-grid__cell--type-name', 'Storefront');
        cy.contains('.sw-data-grid__body .sw-data-grid__row--5 .sw-data-grid__cell--type-name', 'Storefront');
    });

    it('@general: Column should be hidable', { tags: ['pa-sales-channels', 'jest'] }, () => {
        cy.contains('.sw-data-grid__header .sw-data-grid__cell', 'Status').find('.sw-context-button__button').click();
        cy.get('.sw-skeleton').should('not.exist');

        cy.contains('.sw-context-menu-item', 'Hide column').click();
        cy.contains('.sw-data-grid__header .sw-data-grid__cell', 'Status').should('not.be.visible');
    });
});

