import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Import/Export - Export:', () => {
    let page = null;

    beforeEach(() => {
        cy.setToInitialState().then(() => {
            cy.loginViaApi();
        }).then(() => {
            return cy.createDefaultFixture('import-export-profile');
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/import-export/index/export`);
        });

        page = new SettingsPageObject();
    });

    afterEach(() => {
        page = null;
    });

    it('@settings: Create export with default profile', () => {
        cy.get('.sw-import-export-view-export').should('be.visible');

        cy.get('.sw-import-export-exporter__profile-select')
            .typeSingleSelectAndCheck('E2E', '.sw-import-export-exporter__profile-select');
        cy.get('.sw-import-export-progress__start-process-action').click();

        cy.get('.sw-import-export-progress__progress-bar-bar').should('be.visible');
        cy.get('.sw-import-export-progress__stats').should('be.visible');
        cy.get('.sw-import-export-progress__download-action').should('be.visible');

        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0`).should('be.visible');
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0 .sw-data-grid__cell--profileName`)
            .should('contain', 'E2E');

        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0 .sw-data-grid__cell--state`)
            .should('contain', 'succeeded');
    });
});
