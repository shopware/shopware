/// <reference types="Cypress" />

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

    it('@navigation: navigate to scale unit module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/unit`,
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

    it('@base @navigation: navigate to tax module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/tax`,
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

    it.skip('@navigation: navigate to snippet module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/snippet-set`,
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-snippet').click();
        cy.wait('@getData', {
            timeout: 30000
        }).then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-grid').should('be.visible');
    });

    it('@navigation: navigate to sitemap module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/system-config/schema?domain=core.sitemap`,
            method: 'get'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.contains('Sitemap').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-system-config').should('be.visible');
    });

    it('@base @navigation: navigate to shipping module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/shipping-method`,
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

    it('@navigation: navigate to seo module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/seo-url-template`,
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-seo').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-seo-url-template-card').should('be.visible');
    });

    it('@navigation: navigate to salutation module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/salutation`,
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

    it('@base @navigation: navigate to rule builder module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/rule`,
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

    it('@base @navigation: navigate to payment module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/payment-method`,
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

    it('@navigation: navigate to number ranges module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/number-range`,
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

    it('@navigation: navigate to listing setting module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/system-config/schema?domain=core.listing`,
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
        cy.get('.sw-card__title').contains('Product');
    });

    it('@base @navigation: navigate to language module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/language`,
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

    it('@navigation: navigate to document module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/document-base-config`,
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

    it('@navigation: navigate to delivery time module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/delivery-time`,
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-delivery-time').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-data-grid').should('be.visible');
    });

    it('@navigation: navigate to customer group module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/customer-group`,
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

    it('@navigation: navigate to currency module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/currency`,
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

    it('@navigation: navigate to country module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/country`,
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

    it('@navigation: navigate to cart settings module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/system-config/schema?domain=core.cart`,
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

    it('@navigation: navigate to basic information module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/system-config/schema?domain=core.basicInformation`,
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

    it('@navigation: navigate to address settings module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/system-config/schema?domain=core.address`,
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

    it('@navigation: navigate to email templates module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/mail-template`,
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
