// / <reference types="Cypress" />

describe('Listing: Test crud operations', () => {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                return cy.createDefaultFixture('custom-field-set');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/listing/index`);
                cy.get('.sw-settings-listing-index').should('exist');
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@settings: create and read product sorting ', { tags: ['pa-system-settings'] }, () => {
        // change position via inline edit
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-data-grid').should('exist');
        cy.get('.sw-data-grid').scrollIntoView();
        cy.get('.sw-data-grid__row--3 > .sw-data-grid__cell--priority > .sw-data-grid__cell-content')
            .dblclick();
        cy.get('.sw-data-grid .is--inline-edit').should('exist');

        cy.get('.sw-data-grid__row--3 > .sw-data-grid__cell--priority > .sw-data-grid__cell-content input')
            .scrollIntoView()
            .click()
            .clearTypeAndCheck('5');

        cy.get('.sw-data-grid__inline-edit-save').should('be.visible');
        cy.get('.sw-data-grid__inline-edit-save').click();

        // save changes
        cy.get('.sw-page__head-area .sw-button')
            .should('be.visible')
            .click();

        cy.reload();
        cy.get('.smart-bar__header').should('be.visible');
        cy.get('.sw-skeleton.sw-skeleton__detail').should('not.exist');

        // check updated data
        cy.contains('.sw-data-grid__row--0 > .sw-data-grid__cell--label', 'Price descending');
        cy.contains('.sw-data-grid__row--0 > .sw-data-grid__cell--criteria', 'Cheapest product price');
        cy.contains('.sw-data-grid__row--0 > .sw-data-grid__cell--priority', '5');

        cy.get('.sw-settings-listing-index__sorting-options-card').scrollIntoView().should('be.visible');

        // create new product sorting
        cy.get('.sw-container > .sw-button').click();

        cy.contains('.smart-bar__header', 'Create product sorting').should('be.visible');

        // check if save button is disabled
        cy.get('.smart-bar__actions .sw-button--primary')
            .should('be.visible')
            .should('be.disabled');

        // add name
        cy.get('#sw-field--sortingOption-label').typeAndCheck('My own product sorting');

        // mark entity as active
        cy.get('.sw-field--switch__input').click();

        // add entity
        cy.get('.sw-single-select')
            .typeSingleSelect('Product name', '.sw-single-select');

        // validate entry
        cy.contains('.sw-data-grid__cell--field .sw-data-grid__cell-content', 'Product name');
        cy.contains('.sw-data-grid__cell--order .sw-data-grid__cell-content', 'Ascending');
        cy.get('.sw-data-grid__cell--priority #sw-field--currentValue').should('have.value', '1');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/product-sorting`,
            method: 'POST'
        }).as('saveData');

        // save entity
        cy.get('.smart-bar__actions .sw-button--primary')
            .should('not.be.disabled')
            .click();

        // check api request
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.visit(`${Cypress.env('admin')}#/sw/settings/listing/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // check data on index page
        cy.contains('.sw-data-grid__row--4 .sw-data-grid__cell--label .sw-data-grid__cell-value', 'My own product sorting');
        cy.contains('.sw-data-grid__row--4 .sw-data-grid__cell--criteria', 'Product name');
        cy.contains('.sw-data-grid__row--4 .sw-data-grid__cell--priority', '1');
    });

    it('@settings: create product sorting with custom field criteria', { tags: ['pa-system-settings'] }, () => {
        cy.intercept({
            url: '/api/search/custom-field-set',
            method: 'POST'
        }).as('saveCustomFieldSet');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/custom-field-set/**/custom-fields`,
            method: 'POST'
        }).as('saveCustomField');

        cy.visit(`${Cypress.env('admin')}#/sw/settings/custom/field/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-grid-row.sw-grid__row--0 a').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-select').click();
        cy.contains('.sw-select-result', 'Products').click({ force: true });
        cy.get('h2').click();

        // saving custom field
        cy.get('.sw-settings-set-detail__save-action').click();

        // Verify creation
        cy.wait('@saveCustomFieldSet')
            .its('response.statusCode').should('equal', 200);

        cy.get('.sw-custom-field-list__add-button').click();

        cy.get('#sw-field--currentCustomField-name').clearTypeAndCheck('my_custom_field_first');
        cy.get('#sw-field--currentCustomField-config-customFieldType').select('Entity select');

        cy.get('.sw-custom-field-type-base .sw-single-select .sw-block-field__block').click();
        cy.get('.sw-select-result-list-popover-wrapper .sw-select-option--product').click();

        cy.get('.sw-modal__footer > .sw-button--primary').click();

        // Verify creation
        cy.wait('@saveCustomField')
            .its('response.statusCode').should('equal', 204);

        cy.visit(`${Cypress.env('admin')}#/sw/settings/listing/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // create new product sorting
        cy.get('.sw-container > .sw-button').click();
        cy.contains('.sw-card__title', 'General').should('be.visible');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.contains('.smart-bar__header', 'Create product sorting').should('be.visible');

        // check if save button is disabled
        cy.get('.smart-bar__actions .sw-button--primary')
            .should('be.visible')
            .should('be.disabled');

        // add name
        cy.get('#sw-field--sortingOption-label').typeAndCheck('My own product sorting');

        // mark entity as active
        cy.get('.sw-field--switch__input').click();

        // add entity
        cy.get('.sw-single-select')
            .typeSingleSelect('Custom field', '.sw-single-select');

        // validate entry
        // custom field selection should visible
        cy.get('.sw-data-grid__cell--field .sw-data-grid__cell-content .sw-entity-single-select').should('be.visible');
        cy.contains('.sw-data-grid__cell--order .sw-data-grid__cell-content', 'Ascending');
        cy.get('.sw-data-grid__cell--priority #sw-field--currentValue').should('have.value', '1');

        // check if save button is still disabled because no custom field is selected
        cy.get('.smart-bar__actions .sw-button--primary')
            .should('be.visible')
            .should('be.disabled');

        const customFieldSelection = '.sw-data-grid__cell--field .sw-data-grid__cell-content .sw-entity-single-select';

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/product-sorting`,
            method: 'POST'
        }).as('saveData');
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/product-sorting/*`,
            method: 'PATCH'
        }).as('updateData');

        cy.get(customFieldSelection).typeSingleSelect('my_custom_field_first', customFieldSelection);

        // check api request
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);

        // edit name
        cy.get('#sw-field--sortingOption-label').clear().typeAndCheck('My own product sorting with Custom Field');

        // save entity
        cy.get('.smart-bar__actions .sw-button--primary')
            .should('not.be.disabled')
            .click();

        // check api request
        cy.wait('@updateData')
            .its('response.statusCode').should('equal', 204);

        // custom field selection should be visible on inlineEdit
        cy.get(customFieldSelection).should('not.exist');
        cy.get('.sw-data-grid__table .sw-data-grid__body .sw-data-grid__cell-content').first().dblclick({ force: true });
        cy.get(customFieldSelection).should('be.visible');

        cy.visit(`${Cypress.env('admin')}#/sw/settings/listing/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // check data on index page
        cy.contains('.sw-data-grid__body .sw-data-grid__cell--label .sw-data-grid__cell-value',
            'My own product sorting with Custom Field');
        cy.contains('.sw-data-grid__body .sw-data-grid__cell--criteria', 'my_custom_field_first');
        cy.contains('.sw-data-grid__body .sw-data-grid__cell--priority', '1');
    });

    it('@settings: edit an existing product sorting', { tags: ['pa-system-settings'] }, () => {
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-data-grid').should('exist');
        cy.get('.sw-data-grid').scrollIntoView();

        // eslint-disable-next-line max-len
        cy.get('.sw-data-grid__row--1 > .sw-data-grid__cell--actions > .sw-data-grid__cell-content > .sw-context-button > .sw-context-button__button')
            .should('be.visible')
            .click();

        cy.get('.sw-context-menu__content > :nth-child(1)')
            .should('be.visible')
            .click();

        cy.contains('.sw-card__title', 'General').should('be.visible');

        // check smart bar heading
        cy.contains('.smart-bar__header', 'Name Z-A');

        // check name input field
        cy.get('#sw-field--sortingOption-label').clearTypeAndCheck('Price descending and rating');

        // add rating as criteria
        cy.get('.sw-single-select')
            .typeSingleSelect('Product rating', '.sw-single-select');

        // save inline editing
        cy.get('.sw-data-grid__inline-edit-save')
            .should('be.visible')
            .click();

        // save changes
        cy.get('.smart-bar__actions .sw-button--primary')
            .should('be.visible')
            .click();

        cy.visit(`${Cypress.env('admin')}#/sw/settings/listing/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
    });

    it('@settings: delete an existing product sorting', { tags: ['pa-system-settings'] }, () => {
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-data-grid').should('exist');
        cy.get('.sw-data-grid').scrollIntoView();

        // eslint-disable-next-line max-len
        cy.get('.sw-data-grid__row--2 > .sw-data-grid__cell--actions > .sw-data-grid__cell-content > .sw-context-button > .sw-context-button__button')
            .should('be.visible')
            .click();

        cy.get('.sw-context-menu__content > :nth-child(2)')
            .should('be.visible')
            .click();

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/product-sorting/*`,
            method: 'delete'
        }).as('deleteRequest');

        cy.get('.sw-button--danger')
            .should('be.visible')
            .click();

        // check delete request
        cy.wait('@deleteRequest').its('response.statusCode').should('equal', 204);
    });
});
