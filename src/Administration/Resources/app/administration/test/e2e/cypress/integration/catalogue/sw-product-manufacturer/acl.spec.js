// / <reference types="Cypress" />

import ManufacturerPageObject from '../../../support/pages/module/sw-manufacturer.page-object';

describe('Manufacturer: Test crud operations with ACL', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('product-manufacturer');
            }).then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@base @general: read manufacturer with ACL, but without rights', () => {
        cy.loginAsUserWithPermissions([]);

        cy.visit(`${Cypress.env('admin')}#/sw/manufacturer/index`);
        cy.location('hash').should('eq', '#/sw/privilege/error/index');

        cy.visit(`${Cypress.env('admin')}#/sw/manufacturer/detail/d1cecedaaf734f4e934b13293e41b075`);
        cy.location('hash').should('eq', '#/sw/privilege/error/index');
    });

    it('@base @general: read manufacturer with ACL', () => {
        const page = new ManufacturerPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'product_manufacturer',
                role: 'viewer'
            }
        ]);

        cy.visit(`${Cypress.env('admin')}#/sw/manufacturer/index`);

        cy.get(`${page.elements.smartBarHeader} > h2`).contains('Manufacturer');
        cy.get(page.elements.primaryButton).contains('Add manufacturer');

        cy.get('.sw-manufacturer-list__add-manufacturer').should('have.class', 'sw-button--disabled');

        cy.get(`${page.elements.dataGridRow}--0 a`).click();
        cy.get('input[name=name]').should('be.disabled');
        cy.get('input[name=link]').should('be.disabled');
        cy.get('div[name=description]').should('have.class', 'is--disabled');
        cy.get(page.elements.manufacturerSave).should('be.disabled');
    });

    it('@catalogue: create and read manufacturer with ACL', () => {
        const page = new ManufacturerPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'product_manufacturer',
                role: 'viewer'
            },
            {
                key: 'product_manufacturer',
                role: 'editor'
            },
            {
                key: 'product_manufacturer',
                role: 'creator'
            }
        ]);

        cy.visit(`${Cypress.env('admin')}#/sw/manufacturer/index`);

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product-manufacturer`,
            method: 'post'
        }).as('saveData');

        cy.get(`${page.elements.smartBarHeader} > h2`).contains('Manufacturer');
        cy.get(page.elements.primaryButton).contains('Add manufacturer').click();
        cy.url().should('contain', '#/sw/manufacturer/create');

        cy.get('input[name=name]').clearTypeAndCheck('MAN-U-FACTURE');
        cy.get('input[name=link]').clearTypeAndCheck('https://google.com/doodles');
        cy.get(page.elements.manufacturerSave).click();

        // Verify updated manufacturer
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get(page.elements.smartBarBack).click();

        cy.get('.sw-manufacturer-list__content').contains('MAN-U-FACTURE');
    });

    it('@catalogue: edit and read manufacturer with ACL', () => {
        const page = new ManufacturerPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'product_manufacturer',
                role: 'viewer'
            },
            {
                key: 'product_manufacturer',
                role: 'editor'
            }
        ]);

        cy.visit(`${Cypress.env('admin')}#/sw/manufacturer/index`);


        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product-manufacturer/**`,
            method: 'patch'
        }).as('saveData');

        // Edit base data
        cy.get(`${page.elements.dataGridRow}--0 a`).click();
        cy.get('input[name=name]').clearTypeAndCheck('be.visible');
        cy.get('input[name=name]').clear().type('What does it means?(TM)');
        cy.get('input[name=link]').clear().type('https://google.com/doodles');
        cy.get('.sw-property-detail__save-action').should('not.be.disabled');
        cy.get(page.elements.manufacturerSave).click();

        // Verify updated manufacturer
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get(page.elements.successIcon).should('be.visible');
    });

    it('@catalogue: delete manufacturer with ACL', () => {
        const page = new ManufacturerPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'product_manufacturer',
                role: 'viewer'
            },
            {
                key: 'product_manufacturer',
                role: 'editor'
            },
            {
                key: 'product_manufacturer',
                role: 'deleter'
            }
        ]);
        cy.visit(`${Cypress.env('admin')}#/sw/manufacturer/index`);


        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product-manufacturer/**`,
            method: 'delete'
        }).as('saveData');

        // Delete manufacturer
        cy.clickContextMenuItem(
            '.sw-context-menu-item--danger',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get(`${page.elements.modal} ${page.elements.modal}__body p`).contains(
            'Are you sure you want to delete this item?'
        );
        cy.get(`${page.elements.modal}__footer ${page.elements.dangerButton}`).click();
        cy.get(page.elements.modal).should('not.exist');

        // Verify updated manufacturer
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.contains('MAN-U-FACTURE').should('not.exist');
    });
});
