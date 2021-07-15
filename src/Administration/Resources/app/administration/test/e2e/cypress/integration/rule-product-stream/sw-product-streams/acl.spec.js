// / <reference types="Cypress" />

describe('Dynamic product group: Test ACL privileges', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('product-stream');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/stream/index`);
            });
    });

    it('@catalogue: can view product stream ', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'product_stream',
                role: 'viewer'
            }
        ]).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/stream/index`);
        });

        cy.get('.smart-bar__actions .sw-button.sw-button--primary')
            .should('to.have.class', 'sw-button--disabled', true);

        // open context menu
        cy.get('.sw-data-grid__row--0 .sw-context-button__button').click();

        // check if delete button inside context menu is disabled
        cy.get('.sw-entity-listing__context-menu-edit-delete')
            .should('to.have.class', 'is--disabled');

        // go to detail page
        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--name a').click();

        // check if save button is disabled
        cy.get('.smart-bar__actions .sw-button--primary')
            .should('to.have.prop', 'disabled', true);

        // check if input fields are disabled
        cy.get('#sw-field--productStream-name')
            .should('to.have.prop', 'disabled', true)
            .invoke('val')
            .then(content => cy.expect(content).to.contain('1st Productstream'));

        cy.get('#sw-field--productStream-description')
            .should('to.have.prop', 'disabled', true)
            .invoke('val')
            .then(content => cy.expect(content).to.contain('My first product stream'));

        cy.get('.sw-product-stream-field-select.sw-arrow-field .sw-field')
            .should('to.have.class', 'is--disabled', true);

        cy.get('.sw-product-stream-value.sw-product-stream-value--grow-2 :first-child')
            .should('to.have.class', 'is--disabled', true);

        cy.get('.sw-product-stream-value.sw-product-stream-value--grow-2 :last-child')
            .should('to.have.class', 'is--disabled', true);

        cy.get('.sw-condition-and-container__actions > :nth-child(1) > .sw-button')
            .should('to.have.prop', 'disabled', true);

        cy.get('.sw-condition-and-container__actions > :nth-child(2) > .sw-button')
            .should('to.have.prop', 'disabled', true);

        cy.get('.sw-condition-and-container__actions > :nth-child(3) > .sw-button')
            .should('to.have.prop', 'disabled', true);

        cy.get('.sw-condition-or-container__actions > :nth-child(1) > .sw-button')
            .should('to.have.prop', 'disabled', true);

        cy.get('.sw-condition-or-container__actions > :nth-child(2) > .sw-button')
            .should('to.have.prop', 'disabled', true);

        cy.get('.sw-product-stream-detail__open_modal_preview')
            .should('be.visible')
            .click();

        cy.get('.sw-modal').should('be.visible');
    });

    it('@catalogue: can edit product streams', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'product_stream',
                role: 'editor'
            }
        ]).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/stream/index`);
        });

        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product-stream/*`,
            method: 'patch'
        }).as('updateData');

        cy.createProductFixture().then(() => {
            // go to detail page of product stream
            cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--name a').click();

            cy.get('#sw-field--productStream-name').clearTypeAndCheck('Bobby Tarantino');

            cy.get('#sw-field--productStream-description').clearTypeAndCheck('lorem ipsum dolor sit amet.');

            cy.get('.sw-product-stream-value__operator-select .sw-single-select__selection').click();

            cy.get('.sw-select-option--3').click();

            cy.get('.sw-product-stream-value .sw-entity-multi-select').click();

            cy.get('.sw-select-result')
                .should('be.visible')
                .click();

            // save changes
            cy.get('.sw-button-process')
                .should('be.visible')
                .click();

            cy.wait('@updateData').then(xhr => {
                expect(xhr).to.have.property('status', 204);
            });

            // reload page to check if changes have been applied
            cy.reload();

            cy.get('#sw-field--productStream-name')
                .invoke('val')
                .then(content => cy.expect(content).to.contain('Bobby Tarantino'));

            cy.get('#sw-field--productStream-description')
                .invoke('val')
                .then(content => cy.expect(content).to.contain('lorem ipsum dolor sit amet.'));

            cy.get('.sw-product-stream-value__operator-select .sw-single-select__selection')
                .contains('Is not equal to any of');

            cy.get('.sw-product-variant-info__product-name').contains('Product name');
        });
    });

    it('@catalogue: can create product streams', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product-stream`,
            method: 'post'
        }).as('saveData');

        cy.createProductFixture().then(() => {
            cy.loginAsUserWithPermissions([
                {
                    key: 'product_stream',
                    role: 'creator'
                }
            ]).then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/stream/index`);
            });

            cy.get('.smart-bar__actions .sw-button--primary').click();

            cy.get('#sw-field--productStream-name').typeAndCheck('Custom product stream');

            cy.get('#sw-field--productStream-description').typeAndCheck('S.A.V');

            cy.get('.sw-product-stream-value .sw-entity-single-select__selection').click();

            cy.get('.sw-select-result').click();

            // save product sorting
            cy.get('.sw-button-process').click();

            // check if save request got send
            cy.wait('@saveData').then(xhr => {
                expect(xhr).to.have.property('status', 204);
            });

            // reload page and check if data got saved
            cy.reload();

            cy.get('#sw-field--productStream-name')
                .invoke('val')
                .then(content => cy.expect(content).to.contain('Custom product stream'));

            cy.get('#sw-field--productStream-description')
                .invoke('val')
                .then(content => cy.expect(content).to.contain('S.A.V'));

            cy.get('.sw-product-variant-info__product-name').contains('Product name');
        });
    });

    it('@catalogue: can delete product streams', () => {
        cy.createDefaultFixture('product-stream', {
            name: '2nd Productstream'
        }).then(() => {
            cy.loginAsUserWithPermissions([
                {
                    key: 'product_stream',
                    role: 'deleter'
                }
            ]).then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/stream/index`);
            });

            cy.server();
            cy.route({
                url: `${Cypress.env('apiPath')}/product-stream/*`,
                method: 'delete'
            }).as('deleteData');

            cy.route({
                url: '/api/_action/sync',
                method: 'post'
            }).as('deleteMultipleData');

            // open context menu
            cy.get('.sw-data-grid__row--0 .sw-context-button__button').click();

            // delete product stream via context menu
            cy.get('.sw-entity-listing__context-menu-edit-delete').click();

            cy.get('.sw-modal').should('be.visible');

            // confirm delete
            cy.get('.sw-modal .sw-button--danger').click();

            cy.wait('@deleteData').then((xhr) => {
                expect(xhr).to.have.property('status', 204);
            });

            // select all entities
            cy.get('.sw-data-grid-skeleton').should('not.exist');
            cy.get('.sw-data-grid__cell--header.sw-data-grid__cell--selection input').check();

            // delete product stream via bulk
            cy.get('.sw-data-grid__row.is--selected').should('be.visible');
            cy.get('.sw-data-grid__bulk a.link.link-danger').should('be.visible');
            cy.get('.sw-data-grid__bulk a.link.link-danger').click();

            cy.get('.sw-modal').should('be.visible');

            // confirm delete
            cy.get('.sw-modal .sw-button--danger')
                .should('be.visible')
                .click();

            cy.wait('@deleteMultipleData').then((xhr) => {
                expect(xhr).to.have.property('status', 200);
            });
        });
    });
});
