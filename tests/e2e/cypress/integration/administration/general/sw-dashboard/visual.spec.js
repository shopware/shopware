// / <reference types="Cypress" />

describe('Dashboard:  Visual tests', () => {
    // eslint-disable-next-line no-undef
    beforeEach(() => {
        const now = new Date(2018, 1, 1);
        cy.clock(now, ['Date'])
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                return cy.searchViaAdminApi({
                    endpoint: 'product',
                    data: {
                        field: 'name',
                        value: 'Product name'
                    }
                });
            })
            .then((result) => {
                return cy.createGuestOrder(result.id);
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@visual: check appearance of basic dashboard workflow', () => {
        // Change color of the element to ensure consistent snapshots
        cy.get('.sw-dashboard-index__welcome-title')
            .invoke('prop', 'innerText', 'Oh, hello Cypress.');
        cy.get('.sw-dashboard-index__welcome-message')
            .invoke('prop', 'innerText', 'If it wasn\'t for you… This message would never happened.');

        // Wait for Dashboard stats to be visible
        cy.skipOnFeature('FEATURE_NEXT_18187', () => {
            cy.get('.sw-dashboard-index__card-headline').should('be.visible');
        });
        cy.onlyOnFeature('FEATURE_NEXT_18187', () => {
            cy.get('.sw-dashboard-statistics__card-headline').should('be.visible');
        });

        cy.get('#sw-field--statisticDateRanges-value').select('14Days');
        cy.get('.apexcharts-series-markers-wrap').should('be.visible');
        cy.get('.sw-skeleton__detail').should('not.exist');

        // Take snapshot for visual testing
        cy.takeSnapshot('[Dashboard] overview', '.sw-dashboard-index__content');
    });
});
