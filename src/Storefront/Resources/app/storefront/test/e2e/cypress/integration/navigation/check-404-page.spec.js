describe('Basic Informaion: Edit assignments', () => {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                cy.createDefaultFixture('category',{}, 'footer-category-first');
            })
            .then(() => {
                cy.createDefaultFixture('category',{}, 'footer-category-second');
            })
            .then(() => {
                cy.createDefaultFixture('category',{}, 'footer-category-third');
            })
            .then(() => {
                cy.searchViaAdminApi({
                    endpoint: 'sales-channel',
                    data: {
                        field: 'name',
                        value: 'Storefront'
                    }
                });
            })
            .then((salesChannel) => {
                // This ID of the fixture is set by purpose, thus being predictable
                cy.fixture('footer-category-first').then((category) => {
                    cy.updateViaAdminApi('sales-channel', salesChannel.id, {
                        data: {
                            footerCategoryId: category.id,
                            maintenanceIpWhitelist: []
                        }
                    });
                })
            })
            .then(() => {
                // We want to visit 404 page, so we need to accept that status code
                cy.visit('/non-existent/', {
                    failOnStatusCode: false
                });
            });
    });

    it('@pages: should navigate to 404 page with full layout', () => {
        // Check 404 default site
        cy.get('.w-60-l')
            .contains('We are sorry, the page you\'re looking for could not be found.');
        cy.get('.container-main img')
            .first()
            .should('have.attr', 'src')
            .and('match', /404_error/);
        cy.get('.btn').contains('Back to homepage');
        cy.get('.main-navigation-link-text ').contains('Home');

        // Check footer navigation
        cy.get('#footerColumns .footer-column-headline').contains('Footer navigation');
        cy.get('.footer-link').contains('Imprint');

        // Check Header
        cy.get('#accountWidget').should('be.visible');
    });
});
