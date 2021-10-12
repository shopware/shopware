describe('Import/Export - Profiles:  Visual tests', () => {
    beforeEach(() => {
        cy.setLocaleToEnGb().then(() => {
            return cy.loginViaApi();
        }).then(() => {
            return cy.createDefaultFixture('import-export-profile');
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/import-export/index/profiles`);
        });
    });

    it('@visual: check appearance of basic im/ex profile workflow', () => {
        cy.intercept({
            url: '/api/import-export-profile',
            method: 'post'
        }).as('saveData');

        // Take snapshot for visual testing
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('Import export - Profiles overview',
            '.sw-import-export-view-profiles__listing'
        );

        // Perform create new profile action
        cy.get('.sw-import-export-view-profiles__create-action').click();

        // Take snapshot for visual testing
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('Import export - Profile creation', '.sw-modal');
    });
});
