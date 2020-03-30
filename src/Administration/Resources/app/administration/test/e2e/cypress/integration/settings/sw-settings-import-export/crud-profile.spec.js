describe('Import/Export - Profiles: Test crud operations', () => {
    beforeEach(() => {
        cy.setToInitialState().then(() => {
            cy.loginViaApi();
        }).then(() => {
            return cy.createDefaultFixture('import-export-profile');
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/import/export/index/profiles`);
        });
    });

    it('@settings: Create and read profile', () => {
        cy.get('.sw-settings-import-export-view-profiles__toolbar').should('be.visible');
    });
});
