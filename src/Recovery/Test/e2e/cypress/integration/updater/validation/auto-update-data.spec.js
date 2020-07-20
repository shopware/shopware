// / <reference types="Cypress" />

describe('Minimal auto update', () => {
    /**
     * Test requirements:
     * - db `Cypress.env('dbName')` must exist
     * - install.lock must exist
     * - update-service-mock.js must be running
     */

    it('@update: Check product', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/search/product',
            method: 'POST'
        }).as('dataRequest');

        cy.visit('/admin#/sw/product/index');
        cy.login();
        cy.get('.sw-loader').should('not.exist');

        cy.contains('.sw-data-grid__cell--name', 'Travel Pack | Proof Black').should('be.visible');
        cy.contains('.sw-data-grid__cell--name', 'Travel Pack | Proof Black').click();
        cy.get('.smart-bar__header').contains('Travel Pack | Proof Black');

        cy.wait('@dataRequest').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
    });

    it('@update: Check category', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/search/category',
            method: 'POST'
        }).as('dataRequest');

        cy.visit('/admin#/sw/category/index');
        cy.login();

        cy.contains('.sw-tree-item__label', 'Startseite').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-category-detail-base').should('be.visible');

        cy.get('.smart-bar__header').contains('Startseite');

        cy.wait('@dataRequest').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
    });

    it('@update: Check layout', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/search/cms-page',
            method: 'POST'
        }).as('dataRequest');

        cy.visit('/admin#/sw/cms/index');
        cy.login();

        cy.contains('.sw-cms-list-item', 'Beste Produkte Landingpage').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-cms-detail').should('be.visible');

        cy.get('.sw-cms-detail__page-name').contains('Beste Produkte Landingpage');

        cy.wait('@dataRequest').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
    });

    it('@update: Check customer', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/search/customer',
            method: 'POST'
        }).as('dataRequest');

        cy.visit('/admin#/sw/customer/index');
        cy.login();

        cy.contains('.sw-data-grid__cell--firstName', 'Heino Knopf').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-customer-detail').should('be.visible');

        cy.get('.smart-bar__header').contains('Heino Knopf');

        cy.wait('@dataRequest').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
    });

    it('@update: Check image', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/search/media',
            method: 'POST'
        }).as('dataRequest');

        cy.visit('/admin#/sw/media/index');
        cy.login();

        cy.get('.sw-media-library').should('be.visible');
        cy.get('.sw-media-media-item').scrollIntoView();
        cy.get('.sw-media-media-item .sw-media-preview-v2__item')
            .should('have.attr', 'src')
            .and('match', /de-pp-logo/);

        cy.wait('@dataRequest').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
    });
});
