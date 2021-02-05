import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Import/Export - Profiles:  Visual tests', () => {
    let page = null;

    beforeEach(() => {
        cy.setToInitialState().then(() => {
            cy.loginViaApi();
        }).then(() => {
            return cy.createDefaultFixture('import-export-profile');
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/import-export/index/profiles`);
        });

        page = new SettingsPageObject();
    });

    afterEach(() => {
        page = null;
    });

    it('@visual: check appearance of basic im/ex profile workflow', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/import-export-profile`,
            method: 'post'
        }).as('saveData');

        // Take snapshot for visual testing
        cy.get('.sw-data-grid__skeleton').should('not.exist');
        cy.takeSnapshot('Import export - Profiles overview',
            '.sw-import-export-view-profiles__listing');

        // Perform create new profile action
        cy.get('.sw-import-export-view-profiles__create-action').click();

        // Take snapshot for visual testing
        cy.takeSnapshot('Import export - Profile creation', '.sw-modal');
    });
});
