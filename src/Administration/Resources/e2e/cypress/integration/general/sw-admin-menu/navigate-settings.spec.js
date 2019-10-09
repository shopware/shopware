// / <reference types="Cypress" />

describe('Administration: Check module navigation in settings', () => {
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

    it('@general: navigate to scale unit module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/unit',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-units').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-settings-units-card-empty').should('be.visible');
    });

    it('@general: navigate to tax module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/tax',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-tax').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-page__main-content').should('be.visible');
    });

    it('@general: navigate to snippet module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/snippet-set',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-snippet').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-settings-snippet-set-list__actions').should('be.visible');
    });

    it('@general: navigate to shipping module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/shipping-method',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-shipping').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-settings-shipping-list__content').should('exist');
    });

    it('@general: navigate to salutation module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/salutation',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-salutation').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-settings-salutation-list-grid').should('be.visible');
    });

    it('@general: navigate to rule builder module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/rule',
            method: 'post'
        }).as('getData');


        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-rule').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-settings-rule-list__content').should('exist');
    });

    it('@general: navigate to payment module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/payment-method',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-payment').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-settings-payment-list').should('be.visible');
    });

    it('@general: navigate to number ranges module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/number-range',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-number-range').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-settings-number-range-list-grid').should('be.visible');
    });

    it('@general: navigate to login registration module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/_action/system-config/schema?domain=core.loginRegistration',
            method: 'get'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('a[href="#/sw/settings/login/registration/index"]').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-card__title').contains('Login / registration');
    });

    it('@general: navigate to logging module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/log-entry',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });

        cy.get('.sw-settings__tab-system').should('be.visible');
        cy.get('.sw-settings__tab-system').click();

        cy.get('#sw-settings-logging').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-data-grid').should('be.visible');
    });

    it('@general: navigate to listing setting module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/_action/system-config/schema?domain=core.listing',
            method: 'get'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('a[href="#/sw/settings/listing/index"]').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-card__title').contains('Listing');
    });

    it('@general: navigate to language module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/language',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-language').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-settings-language-list').should('be.visible');
    });

    it('@general: navigate to document module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/document-base-config',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('a[href="#/sw/settings/document/index').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-settings-document-list-grid').should('be.visible');
    });

    it('@general: navigate to customer group module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/customer-group',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-customer-group').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-settings-customer-group-list-grid').should('be.visible');
    });

    it('@general: navigate to currency module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/currency',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-currency').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-settings-currency-list-grid').should('be.visible');
    });

    it('@general: navigate to country module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/country',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-country').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-settings-country-list-grid').should('be.visible');
    });

    it('@general: navigate to cart settings module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/_action/system-config/schema?domain=core.cart',
            method: 'get'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('a[href="#/sw/settings/cart/index"]').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-card__title').contains('Cart');
    });

    it('@general: navigate to basic information module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/_action/system-config/schema?domain=core.basicInformation',
            method: 'get'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('a[href="#/sw/settings/basic/information/index"]').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-card__title').contains('Basic information');
    });

    it('@general: navigate to address settings module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/_action/system-config/schema?domain=core.address',
            method: 'get'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('a[href="#/sw/settings/address/index"]').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-card__title').contains('Address');
    });

    it('@general: navigate to email templates module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/mail-template',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-mail-template').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-mail-templates-list-grid').should('be.visible');
    });
});
