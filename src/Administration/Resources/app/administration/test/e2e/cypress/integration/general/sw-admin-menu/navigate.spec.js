// / <reference types="Cypress" />

describe('Administration: Check module navigation', () => {
    beforeEach(() => {
        // Clean previous state and prepare Administration
        cy.loginViaApi()
            .then(() => {
                cy.setLocaleToEnGb();
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@package @general: navigate to category module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/category',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/category/index',
            mainMenuId: 'sw-catalogue',
            subMenuId: 'sw-category'
        });
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-category-tree').should('be.visible');
    });

    it('@package @general: navigate to product module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/product',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/product/index',
            mainMenuId: 'sw-catalogue',
            subMenuId: 'sw-product'
        });
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-product-list__content').should('be.visible');
    });

    it('@package @general: navigate to manufacturer module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/product-manufacturer',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/manufacturer/index',
            mainMenuId: 'sw-catalogue',
            subMenuId: 'sw-manufacturer'
        });
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-manufacturer-list__content').should('exist');
    });

    it('@package @general: navigate to property module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/property-group',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/property/index',
            mainMenuId: 'sw-catalogue',
            subMenuId: 'sw-property'
        });
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-property-list__content').should('exist');
    });

    it('@package @general: navigate to customer module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/customer',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/customer/index',
            mainMenuId: 'sw-customer'
        });
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-customer-list__content').should('be.visible');
    });

    it('@package @general: navigate to order module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/order',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/order/index',
            mainMenuId: 'sw-order'
        });
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-order-list').should('be.visible');
    });

    it('@package @general: navigate to media module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/media',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/media/index',
            mainMenuId: 'sw-content',
            subMenuId: 'sw-media'
        });
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-media-index__page-content').should('be.visible');
    });

    it('@package @general: navigate to cms module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/cms-page',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/cms/index',
            mainMenuId: 'sw-content',
            subMenuId: 'sw-cms'
        });
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-cms-list').should('be.visible');
    });

    it('@package @general: navigate to theme module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/theme',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/theme/manager/index',
            mainMenuId: 'sw-content',
            subMenuId: 'sw-theme-manager'
        });
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-theme-list__list').should('be.visible');
    });

    it('@package @general: navigate to promotion module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/promotion',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/promotion/index',
            mainMenuId: 'sw-marketing',
            subMenuId: 'sw-promotion'
        });
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-promotion-list').should('be.visible');
    });

    it('@package @general: navigate to newsletter recipients module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/newsletter-recipient',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/newsletter/recipient/index',
            mainMenuId: 'sw-marketing',
            subMenuId: 'sw-newsletter-recipient'
        });
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-newsletter-recipient-list').should('be.visible');
    });
});
