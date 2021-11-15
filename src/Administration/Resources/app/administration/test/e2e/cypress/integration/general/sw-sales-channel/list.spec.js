// / <reference types="Cypress" />

describe('Sales Channel: Test list', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.createDefaultSalesChannel({
                    'id': '00000000000000000000000000000001',
                    'name': 'SalesChannel #1',
                    'accessKey': 'SWSCWLRRZJR2ZE05VMYYVGT1W1',
                    'maintenance': true
                });
                cy.createDefaultSalesChannel({
                    'id': '00000000000000000000000000000002',
                    'name': 'SalesChannel #2',
                    'accessKey': 'SWSCWLRRZJR2ZE05VMYYVGT1W2',
                    'maintenance': true
                });
                cy.createDefaultSalesChannel({
                    'id': '00000000000000000000000000000003',
                    'name': 'SalesChannel #3',
                    'accessKey': 'SWSCWLRRZJR2ZE05VMYYVGT1W3',
                    'active': false
                });
                cy.createDefaultSalesChannel({
                    'id': '00000000000000000000000000000004',
                    'name': 'SalesChannel #4',
                    'accessKey': 'SWSCWLRRZJR2ZE05VMYYVGT1W4',
                    'active': false
                });

                cy.createProductFixture({
                    'name': 'Product #1',
                    'productNumber': 'SW001'
                });
                cy.createProductFixture({
                    'name': 'Product #2',
                    'productNumber': 'SW002'
                });
                cy.createProductFixture({
                    'name': 'Product #3',
                    'productNumber': 'SW003'
                });
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/sales/channel/list`);
            });
    });

    it('@base @general: open listing page', () => {
        cy.skipOnFeature('FEATURE_NEXT_17421');
        // check if listing works correctly
        cy.get('.sw-page__smart-bar-amount').contains('6');

        // open sales channel
        cy.get('.sw-data-grid__row--0')
            .find('.sw-context-button__button')
            .click();
        cy.get('.sw-context-menu-item')
            .contains('Edit')
            .click();

        // check if sales channel was opened correctly
        cy.get('.sw-loader').should('not.exist');
        cy.get('.smart-bar__header').contains('Headless');
    });

    it('@general: Should show six sales-channels and columns', () => {
        cy.onlyOnFeature('FEATURE_NEXT_17421');

        cy.get('.sw-data-grid__body .sw-data-grid__row').should('have.length', 6);
        cy.get('.sw-data-grid__header .sw-data-grid__cell').should('have.length', 6);
    });

    it('@general: Name should be sortable', () => {
        cy.onlyOnFeature('FEATURE_NEXT_17421');

        cy.contains('.sw-data-grid__header .sw-data-grid__cell', 'Sales Channel').click('left');
        cy.get('.sw-data-grid__body .sw-data-grid__row--0 .sw-data-grid__cell--name').contains('Headless');
        cy.get('.sw-data-grid__body .sw-data-grid__row--3 .sw-data-grid__cell--name').contains('SalesChannel #3');
        cy.get('.sw-data-grid__body .sw-data-grid__row--5 .sw-data-grid__cell--name').contains('Storefront');
    });

    it('@general: Type should be sortable', () => {
        cy.onlyOnFeature('FEATURE_NEXT_17421');

        cy.contains('.sw-data-grid__header .sw-data-grid__cell', 'Type').click('left');
        cy.get('.sw-data-grid__body .sw-data-grid__row--0 .sw-data-grid__cell--type-name').contains('Headless');
        cy.get('.sw-data-grid__body .sw-data-grid__row--3 .sw-data-grid__cell--type-name').contains('Storefront');
        cy.get('.sw-data-grid__body .sw-data-grid__row--5 .sw-data-grid__cell--type-name').contains('Storefront');
    });

    it('@general: Product count should be sortable', () => {
        cy.onlyOnFeature('FEATURE_NEXT_17421');

        cy.contains('.sw-data-grid__header .sw-data-grid__cell', 'Products').click('left');
        cy.get('.sw-data-grid__body .sw-data-grid__row--0 .sw-data-grid__cell--product_visibilities').contains('0');
        cy.get('.sw-data-grid__body .sw-data-grid__row--3 .sw-data-grid__cell--product_visibilities').contains('0');
        cy.get('.sw-data-grid__body .sw-data-grid__row--5 .sw-data-grid__cell--product_visibilities').contains('3');
    });

    it('@general: Status should be sortable', () => {
        cy.onlyOnFeature('FEATURE_NEXT_17421');

        cy.contains('.sw-data-grid__header .sw-data-grid__cell', 'Status').click('left');
        cy.get('.sw-data-grid__body .sw-data-grid__row--0 .sw-data-grid__cell--status').contains('Online');
        cy.get('.sw-data-grid__body .sw-data-grid__row--3 .sw-data-grid__cell--status').contains('Maintenance');
        cy.get('.sw-data-grid__body .sw-data-grid__row--5 .sw-data-grid__cell--status').contains('Offline');
    });

    it('@general: Column should be hidable', () => {
        cy.onlyOnFeature('FEATURE_NEXT_17421');

        cy.contains('.sw-data-grid__header .sw-data-grid__cell', 'Status').find('.sw-context-button__button').click();

        cy.contains('.sw-context-menu-item', 'Hide column').click();
        cy.contains('.sw-data-grid__header .sw-data-grid__cell', 'Status').should('not.be.visible');
    });
});

