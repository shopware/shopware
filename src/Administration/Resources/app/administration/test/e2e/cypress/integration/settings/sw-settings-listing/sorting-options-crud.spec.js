// / <reference types="Cypress" />

describe('Listing: Test crud operations', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/listing/index`);
            });
    });

    it('@settings: create and read product sorting ', () => {
        // change position via inline edit
        cy.get('.sw-loader').should('not.exist');
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

        // check updated data
        cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--label').contains('Price descending');
        cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--criteria').contains('Product listing price');
        cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--priority').contains('5');

        cy.server();

        cy.get('.sw-settings-listing-index__sorting-options-card').should('be.visible');

        // create new product sorting
        cy.get('.sw-container > .sw-button').click();

        cy.get('.smart-bar__header').should('be.visible').contains('Create product sorting');

        // check if save button is disabled
        cy.get('.sw-button')
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
        cy.get('.sw-data-grid__cell--field .sw-data-grid__cell-content').contains('Product name');
        cy.get('.sw-data-grid__cell--order .sw-data-grid__cell-content').contains('Ascending');
        cy.get('.sw-data-grid__cell--priority .sw-data-grid__cell-content').contains('1');

        cy.route({
            url: '/api/v*/product-sorting',
            method: 'post'
        }).as('saveData');

        // save entity
        cy.get('.sw-button')
            .should('not.be.disabled')
            .click();

        // check api request
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.visit(`${Cypress.env('admin')}#/sw/settings/listing/index`);

        // check data on index page
        cy.get('.sw-data-grid__row--4 .sw-data-grid__cell--label .sw-data-grid__cell-value')
            .contains('My own product sorting');
        cy.get('.sw-data-grid__row--4 .sw-data-grid__cell--criteria')
            .contains('Product name');
        cy.get('.sw-data-grid__row--4 .sw-data-grid__cell--priority')
            .contains('1');
    });

    it('@settings: edit an existing product sorting', () => {
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-data-grid').scrollIntoView();
        cy.get('.sw-data-grid__row--1 > .sw-data-grid__cell--actions > .sw-data-grid__cell-content > .sw-context-button > .sw-context-button__button')
            .should('be.visible')
            .click();

        cy.get('.sw-context-menu__content > :nth-child(1)')
            .should('be.visible')
            .click();

        // check smart bar heading
        cy.get('.smart-bar__header').contains('Name Z-A');

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
        cy.get('.sw-button')
            .should('be.visible')
            .click();

        cy.visit(`${Cypress.env('admin')}#/sw/settings/listing/index`);
    });

    it('@settings: delete an existing product sorting', () => {
        cy.server();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-data-grid').scrollIntoView();

        cy.get('.sw-data-grid__row--2 > .sw-data-grid__cell--actions > .sw-data-grid__cell-content > .sw-context-button > .sw-context-button__button')
            .should('be.visible')
            .click();

        cy.get('.sw-context-menu__content > :nth-child(2)')
            .should('be.visible')
            .click();

        cy.route({
            url: '/api/v*/product-sorting/*',
            method: 'delete'
        }).as('deleteRequest');

        cy.get('.sw-button--danger')
            .should('be.visible')
            .click();

        // check delete request
        cy.wait('@deleteRequest').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
    });
});
