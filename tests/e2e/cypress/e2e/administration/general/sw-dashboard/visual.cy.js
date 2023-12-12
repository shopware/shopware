// / <reference types="Cypress" />

describe('Dashboard:  Visual tests', () => {
    // eslint-disable-next-line no-undef
    beforeEach(() => {
        const now = new Date(2018, 1, 1);
        cy.clock(now, ['Date'])
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                return cy.searchViaAdminApi({
                    endpoint: 'product',
                    data: {
                        field: 'name',
                        value: 'Product name',
                    },
                });
            })
            .then((result) => {
                return cy.createGuestOrder(result.id);
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@visual: check appearance of basic dashboard workflow', { tags: ['pa-services-settings'] }, () => {
        // Change color of the element to ensure consistent snapshots
        cy.get('.sw-dashboard-index__welcome-title')
            .invoke('prop', 'innerText', 'Oh, hello Cypress.');
        cy.get('.sw-dashboard-index__welcome-message')
            .invoke('prop', 'innerText', 'If it wasn\'t for you… This message would never happened.');

        cy.get('.sw-dashboard-statistics .sw-card__title').each((item) => {
            cy.wrap(item).contains(/Orders|Turnover/g);
        });

        cy.get('.sw-dashboard-statistics__statistics-count #sw-field--selectedRange').scrollIntoView();
        cy.get('.sw-dashboard-statistics__statistics-count #sw-field--selectedRange').select('14Days');
        //select command again to reload data within the card
        cy.get('.sw-dashboard-statistics__statistics-count #sw-field--selectedRange').select('14Days');
        cy.get('.sw-dashboard-statistics__statistics-count .apexcharts-series-markers').should('be.visible');

        cy.get('.sw-skeleton__detail').should('not.exist');
        cy.changeElementStyling(
            '.apexcharts-xaxis-label',
            'display: none;',
        );
        // Take snapshot for visual testing
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Dashboard] overview', '.sw-dashboard-index__content', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
