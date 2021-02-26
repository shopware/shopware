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

    it('@base @navigation: navigate to category module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/category`,
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

    it('@base @navigation: navigate to product module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/product`,
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

    it('@base @navigation: navigate to review module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/product-review`,
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/review/index',
            mainMenuId: 'sw-catalogue',
            subMenuId: 'sw-review'
        });
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-review-list').should('be.visible');
    });

    it('@base @navigation: navigate to manufacturer module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/product-manufacturer`,
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

    it('@base @navigation: navigate to property module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/property-group`,
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

    it('@base @navigation: navigate to customer module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/customer`,
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/customer/index',
            mainMenuId: 'sw-customer',
            subMenuId: 'sw-customer-index'
        });
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-customer-list__content').should('be.visible');
    });

    it('@base @navigation: navigate to order module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/order`,
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/order/index',
            mainMenuId: 'sw-order',
            subMenuId: 'sw-order-index'
        });
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-order-list').should('be.visible');
    });

    it('@base @navigation: navigate to media module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/media`,
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

    it('@base @navigation: navigate to cms module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/cms-page`,
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

    it('@base @navigation: navigate to theme module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/theme`,
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

    it('@base @navigation: navigate to promotion module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/promotion`,
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/promotion/v2/index',
            mainMenuId: 'sw-marketing',
            subMenuId: 'sw-promotion-v2'
        });
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-promotion-v2-list').should('be.visible');
    });

    it('@navigation: navigate to newsletter recipients module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/newsletter-recipient`,
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
