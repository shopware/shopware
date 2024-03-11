/**
 * @package inventory
 */
import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

const page = new SettingsPageObject();

describe('Number Range: Test acl privileges', () => {
    beforeEach(() => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
    });

    it('@settings: read number range with ACL, but without rights', { tags: ['pa-inventory', 'VUE3'] }, () => {
        cy.loginAsUserWithPermissions([]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/number/range/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        cy.location('hash').should('eq', '#/sw/privilege/error/index');

        cy.visit(`${Cypress.env('admin')}#/sw/settings/number/range/detail/2096ac17bc724461b87f7850fc149b4b`);
        cy.location('hash').should('eq', '#/sw/privilege/error/index');
    });

    it('@settings: read number range with ACL', { tags: ['pa-inventory', 'VUE3'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'number_ranges',
                role: 'viewer',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/number/range/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        cy.contains(`${page.elements.smartBarHeader} > h2`, 'Number ranges');
        cy.contains(page.elements.primaryButton, 'Add number range');

        cy.get('.sw-number-range-list__add-number-range').should('have.class', 'sw-button--disabled');

        cy.get(`${page.elements.dataGridRow}--0 a`).click();
        cy.get(page.elements.numberRangeSaveAction).should('be.disabled');
        cy.get('input').should('be.disabled');
    });

    it('@settings: create and read number range with ACL', { tags: ['pa-inventory', 'VUE3'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'number_ranges',
                role: 'viewer',
            },
            {
                key: 'number_ranges',
                role: 'editor',
            },
            {
                key: 'number_ranges',
                role: 'creator',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/number/range/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });


        cy.intercept({
            url: '/api/search/number-range',
            method: 'POST',
        }).as('saveData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/number-range`,
            method: 'POST',
        }).as('searchData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/number-range-type`,
            method: 'POST',
        }).as('searchNumberRangeType');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/sales-channel`,
            method: 'POST',
        }).as('searchSalesChannel');

        cy.get('a[href="#/sw/settings/number/range/create"]').click();

        cy.get('input[name=sw-field--numberRange-name]').type('Name e2e');
        cy.get('input[name=sw-field--numberRange-description]').type('description e2e');

        cy.get('#numberRangeTypes')
            .typeSingleSelectAndCheck(
                'Cancellation',
                '#numberRangeTypes',
            );

        cy.wait('@searchNumberRangeType')
            .its('response.statusCode').should('equal', 200);
        cy.wait('@searchSalesChannel').then(({ response }) => {
            const { attributes } = response.body.data[0];
            cy.get('.sw-multi-select').typeMultiSelectAndCheck(attributes.name);
        });

        cy.get(page.elements.numberRangeSaveAction).click();

        // Verify creation
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        cy.get(page.elements.smartBarBack).click();
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/**`,
            method: 'post',
        }).as('searchResultCall');

        cy.get('input.sw-search-bar__input').type('Name e2e').should('have.value', 'Name e2e');

        cy.wait('@searchResultCall')
            .its('response.statusCode').should('equal', 200);

        cy.get('.sw-settings-number-range-list-grid').should('be.visible');
        cy.contains(`${page.elements.dataGridRow}--0`, 'Name e2e').should('be.visible');
    });

    it('@settings: can edit number range with ACL', { tags: ['pa-inventory', 'VUE3'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'number_ranges',
                role: 'viewer',
            },
            {
                key: 'number_ranges',
                role: 'editor',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/number/range/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // Request we want to wait for later
        cy.intercept({
            url: '/api/number-range/*',
            method: 'PATCH',
        }).as('saveData');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/number-range`,
            method: 'POST',
        }).as('searchData');

        cy.get(`${page.elements.dataGridRow}--1 a`).click();

        // edit name
        cy.get('input[name=sw-field--numberRange-name]').clear();
        cy.get('input[name=sw-field--numberRange-name]').clearTypeAndCheck('Cancellations update');

        cy.get(page.elements.numberRangeSaveAction).click();

        // Verify creation
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.smartBarBack).click();
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/**`,
            method: 'post',
        }).as('searchResultCall');

        cy.get('input.sw-search-bar__input').type('Cancellations update').should('have.value', 'Cancellations update');

        cy.wait('@searchResultCall')
            .its('response.statusCode').should('equal', 200);

        cy.wait('@searchData').its('response.statusCode').should('equal', 200); cy.get('.sw-settings-number-range-list-grid').should('be.visible');
        cy.contains(`${page.elements.dataGridRow}--0`, 'Cancellations update').should('be.visible');
    });

    it('@settings: can delete number range with ACL', { tags: ['pa-inventory', 'VUE3'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'number_ranges',
                role: 'viewer',
            },
            {
                key: 'number_ranges',
                role: 'deleter',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/number/range/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // Request we want to wait for later
        cy.intercept({
            url: '/api/number-range/*',
            method: 'delete',
        }).as('deleteData');

        // Delete number range
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );
        cy.get('.sw-modal__body').should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.numberRangeColumnName}`).then(row => {
            cy.contains('.sw-modal__body',
                `Are you sure you want to delete the number range "${row.text().trim()}"?`);
        });
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();

        // Verify deletion
        cy.wait('@deleteData').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.modal).should('not.exist');
    });
});
