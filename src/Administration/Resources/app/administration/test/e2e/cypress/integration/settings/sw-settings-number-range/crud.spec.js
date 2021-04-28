import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

const page = new SettingsPageObject();

describe('Number Range: Test crud number range', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                return cy.loginViaApi();
            })
            .then(() => {
                return cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/number/range/index`);
            });
    });

    it('@settings: create and read number range', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/number-range`,
            method: 'post'
        }).as('saveData');
        cy.route({
            url: `${Cypress.env('apiPath')}/search/number-range-type`,
            method: 'post'
        }).as('searchNumberRangeType');
        cy.route({
            url: `${Cypress.env('apiPath')}/search/sales-channel`,
            method: 'post'
        }).as('searchSalesChannel');

        cy.get('a[href="#/sw/settings/number/range/create"]').click();

        // Create number range
        cy.get('input[name=sw-field--numberRange-name]').typeAndCheck('Name e2e');
        cy.get('input[name=sw-field--numberRange-description]').type('Description e2e');

        cy.get('#numberRangeTypes')
            .typeSingleSelectAndCheck(
                'Cancellation',
                '#numberRangeTypes'
            );

        cy.wait('@searchNumberRangeType').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.wait('@searchSalesChannel').then(({ response }) => {
            const { attributes } = response.body.data[0];
            cy.get('.sw-multi-select').typeMultiSelectAndCheck(attributes.name);
        });
        cy.get(page.elements.numberRangeSaveAction).click();

        // Verify creation
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Name e2e');
        cy.get('.sw-settings-number-range-list-grid').should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible')
            .contains('Name e2e');
    });

    it('@settings: update and read number range', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/number-range/*`,
            method: 'patch'
        }).as('saveData');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--1`
        );

        cy.get('input[name=sw-field--numberRange-name]').clear();
        cy.get('input[name=sw-field--numberRange-name]').clearTypeAndCheck('Cancellations update');
        cy.get(page.elements.numberRangeSaveAction).click();

        // Verify creation
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Cancellations update');
        cy.get('.sw-settings-number-range-list-grid').should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible')
            .contains('Cancellations update');
    });

    it('@settings: delete number range', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/number-range/*`,
            method: 'delete'
        }).as('deleteData');

        // Delete number range
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get('.sw-modal__body').should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.numberRangeColumnName}`).then(row => {
            cy.get('.sw-modal__body')
                .contains(`Are you sure you want to delete the number range "${row.text().trim()}"?`);
        });
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();

        // Verify deletion
        cy.wait('@deleteData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.modal).should('not.exist');
    });
});
