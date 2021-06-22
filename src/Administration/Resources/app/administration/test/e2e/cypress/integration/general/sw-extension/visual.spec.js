// / <reference types="Cypress" />

describe('Dashboard:  Visual tests', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        cy.setToInitialState()
            .then(() => {
                // freezes the system time to Jan 1, 2018
                const now = new Date(2018, 1, 1);
                cy.clock(now);
            })
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    // skipped because it has a dependency to the sbp, see NEXT-15818
    it.skip('@visual: check appearance of my extension overview', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/extension/installed`,
            method: 'get'
        }).as('getInstalled');
        cy.route({
            url: `${Cypress.env('apiPath')}/search/**`,
            method: 'post'
        }).as('searchResultCall');
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/extension-store/list`,
            method: 'post'
        }).as('extensionList');

        cy.clickMainMenuItem({
            targetPath: '#/sw/extension/store',
            mainMenuId: 'sw-extension',
            subMenuId: 'sw-extension-store'
        });

        // Check extension store
        cy.get('.sw-extension-store-landing-page').should('be.visible');
        cy.get('.sw-button').click();
        cy.get('.sw-extension-store-landing-page__wrapper-loading').should('be.visible');
        cy.contains('Activating the Shopware Store...');
        cy.get('.sw-extension-store-landing-page__wrapper-loading').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.wait('@extensionList').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // Prepare and do snapshot
        cy.get('.sw-extension-store-listing').should('be.visible');
        cy.get('.sw-extension-listing-card__info-name')
            .invoke('prop', 'innerText', 'My plugin');
        cy.get('.sw-extension-listing-card__info-description')
            .invoke('prop', 'innerText', 'If it wasn\'t for you… This message would never happened.');
        cy.get('.sw-extension-listing-card__info-price')
            .invoke('prop', 'innerText', 'Free');

        // Change background-image of the element to ensure consistent snapshots
        cy.changeElementStyling(
            '.sw-extension-listing-card__preview',
            `background-image: url("${Cypress.config('baseUrl')}/bundles/administration/static/img/sw-login-background.png")`
        );

        // Change visibility of the element to ensure consistent snapshots
        cy.changeElementStyling(
            '.sw-extension-listing-card__extension-type-label',
            'visibility: hidden'
        );

        // Change visibility of the element to ensure consistent snapshots
        cy.changeElementStyling(
            '.sw-extension-listing-card__info-rating',
            'visibility: hidden'
        );
        cy.takeSnapshot('[My extensions] Store', '.sw-extension-store-listing');

        cy.visit(Cypress.env('admin'));
        cy.get('.sw-dashboard-index__card--bg-checklist').should('be.visible');

        // Check my extensions listing
        cy.clickMainMenuItem({
            targetPath: '#/sw/extension/my-extensions',
            mainMenuId: 'sw-extension',
            subMenuId: 'sw-extension-my-extensions'
        });

        cy.wait('@getInstalled').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get('.sw-loader').should('not.exist');

        // Change color of the element to ensure consistent snapshots
        cy.changeElementStyling(
            '.sw-extension-card-base__meta-info',
            'color: #fff'
        );

        cy.takeSnapshot('[My extensions] List', '.sw-extension-my-extensions-listing');
    });
});
