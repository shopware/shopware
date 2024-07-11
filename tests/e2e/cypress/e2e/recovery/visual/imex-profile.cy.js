describe('Import/Export - Profiles:  Visual tests', () => {
    beforeEach(() => {
        cy.createDefaultFixture('import-export-profile')
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/import-export/index/profiles`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@visual: check appearance of basic im/ex profile workflow', { tags: ['pa-services-settings'] }, () => {
        cy.intercept({
            url: '/api/import-export-profile',
            method: 'post',
        }).as('saveData');

        // Take snapshot for visual testing
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.get('.sw-admin-menu__sales-channel-item').should('be.visible');
        const profiles = Cypress.env('locale') === 'en-GB' ? 'Profiles' : 'Profile';
        cy.contains('.sw-tabs-item', profiles);
        const profileType = Cypress.env('locale') === 'en-GB' ? 'Default' : 'Standard';
        cy.get('.sw-data-grid__cell--systemDefault').should('contain', profileType);
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot(`${Cypress.env('testDataUsage') ? '[Update]' : '[Install]'} Import export - Profiles overview`,
            '.sw-import-export-view-profiles__listing',
        );

        // Perform create new profile action
        cy.get('.sw-import-export-view-profiles__create-action').click();

        // Take snapshot for visual testing
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot(` ${Cypress.env('testDataUsage') ? '[Update]' : '[Install]'} Import export - Profile creation`, '.sw-modal');
    });
});
