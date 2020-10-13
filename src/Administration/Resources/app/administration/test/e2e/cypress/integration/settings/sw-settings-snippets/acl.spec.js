// / <reference types="Cypress" />

describe('Snippets: Test acl privileges', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createSnippetFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/snippet/index`);
            });
    });

    it('@base @settings: Read snippets', () => {
        cy.window().then((win) => {
            if (!win.Shopware.Feature.isActive('FEATURE_NEXT_3722')) {
                cy.log('Skipping test because of deactivated feature flag: "FEATURE_NEXT_3722"');

                return;
            }

            cy.loginAsUserWithPermissions([
                {
                    key: 'snippet',
                    role: 'viewer'
                }
            ]);

            // visiting settings page to prove that snippets element is visible
            cy.visit(`${Cypress.env('admin')}#/sw/settings/snippet/index`);

            // go to snippet list
            cy.get('.sw-grid__row--0 > .sw-settings-snippet-set__column-name > .sw-grid__cell-content > a').click();

            cy.get('.sw-data-grid-skeleton').should('not.exist');

            // go to snippet detail page
            cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--id > .sw-data-grid__cell-content > a').click();

            // check that card loading state got removed
            cy.get(':nth-child(1) > .sw-card__content > .sw-loader').should('not.exist');
            cy.get(':nth-child(2) > .sw-card__content > .sw-loader').should('not.exist');

            // check content and disabled state of input fields
            cy.get('#sw-field--translationKey')
                .should('to.have.prop', 'disabled', true)
                .invoke('val')
                .then(content => cy.expect(content).to.contain('aWonderful.customSnip'));

            cy.get(':nth-child(2) > .sw-field > .sw-block-field__block > #sw-field--snippet-value')
                .should('to.have.prop', 'disabled', true)
                .invoke('val')
                .then(content => cy.expect(content).to.contain(''));

            cy.get(':nth-child(1) > .sw-field > .sw-block-field__block > #sw-field--snippet-value')
                .should('to.have.prop', 'disabled', true)
                .invoke('val')
                .then(content => cy.expect(content).to.contain(''));
        });
    });


    it('@base @settings: Edit snippets', () => {
        cy.window().then((win) => {
            if (!win.Shopware.Feature.isActive('FEATURE_NEXT_3722')) {
                cy.log('Skipping test because of deactivated feature flag: "FEATURE_NEXT_3722"');

                return;
            }

            cy.loginAsUserWithPermissions([
                {
                    key: 'snippet',
                    role: 'editor'
                }
            ]);

            cy.server();
            cy.route({
                url: '/api/v*/snippet/*',
                method: 'patch'
            }).as('saveData');

            // visiting settings page to prove that snippets element is visible
            cy.visit(`${Cypress.env('admin')}#/sw/settings/snippet/index`);

            cy.get('.sw-grid__row--0 > .sw-settings-snippet-set__column-name > .sw-grid__cell-content > a').click();

            cy.get('.sw-data-grid-skeleton').should('not.exist');

            // go to snippet detail page
            cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--id > .sw-data-grid__cell-content > a').click();

            // check that card loading state got removed
            cy.get(':nth-child(1) > .sw-card__content > .sw-loader').should('not.exist');
            cy.get(':nth-child(2) > .sw-card__content > .sw-loader').should('not.exist');

            cy.get(':nth-child(1) > .sw-field > .sw-block-field__block > #sw-field--snippet-value').typeAndCheck('Gogoat');

            // save changes
            cy.get('.sw-tooltip--wrapper > .sw-button').click();

            // check request
            cy.wait('@saveData').then((xhr) => {
                expect(xhr).to.have.property('status', 204);
            });
        });
    });

    it('@base @settings: Create snippets', () => {
        cy.window().then((win) => {
            if (!win.Shopware.Feature.isActive('FEATURE_NEXT_3722')) {
                cy.log('Skipping test because of deactivated feature flag: "FEATURE_NEXT_3722"');

                return;
            }

            cy.loginAsUserWithPermissions([
                {
                    key: 'snippet',
                    role: 'creator'
                }
            ]);

            cy.server();
            cy.route({
                url: '/api/v*/snippet',
                method: 'post'
            }).as('saveData');

            cy.visit(`${Cypress.env('admin')}#/sw/settings/snippet/index`);

            cy.get('.sw-grid__row--0 > .sw-settings-snippet-set__column-name > .sw-grid__cell-content > a').click();

            // clicking snippet create button
            cy.get('.sw-tooltip--wrapper > .sw-button')
                .should('be.visible')
                .click();

            // check if elements
            cy.get(':nth-child(1) > .sw-card__content > .sw-loader').should('not.be.exist');
            cy.get(':nth-child(2) > .sw-card__content > .sw-loader').should('not.be.exist');

            // fill out input fields
            cy.get('#sw-field--translationKey').typeAndCheck('random.snippet');

            cy.get(':nth-child(1) > .sw-field > .sw-block-field__block > #sw-field--snippet-value').typeAndCheck('Zufällig');

            cy.get(':nth-child(2) > .sw-field > .sw-block-field__block > #sw-field--snippet-value').typeAndCheck('Random');

            // save new snippet
            cy.get('.sw-tooltip--wrapper > .sw-button')
                .should('be.visible')
                .click();

            cy.wait('@saveData').then((xhr) => {
                expect(xhr).to.have.property('status', 204);
            });
        });
    });

    it('@base @settings: Create snippet set', () => {
        cy.window().then((win) => {
            if (!win.Shopware.Feature.isActive('FEATURE_NEXT_3722')) {
                cy.log('Skipping test because of deactivated feature flag: "FEATURE_NEXT_3722"');

                return;
            }

            cy.loginAsUserWithPermissions([
                {
                    key: 'snippet',
                    role: 'creator'
                }
            ]);

            cy.server();
            cy.route({
                url: '/api/v*/snippet-set',
                method: 'post'
            }).as('saveData');

            cy.visit(`${Cypress.env('admin')}#/sw/settings/snippet/index`);

            cy.get('.sw-settings-snippet-set-list__action-add')
                .should('be.visible')
                .click();

            cy.get('.sw-grid__row--0 > .sw-settings-snippet-set__column-name > .sw-grid__cell-inline-editing > .sw-field > .sw-block-field__block > #sw-field--item-name')
                .typeAndCheck('Custom de-DE');

            cy.get('.sw-grid__row--0 > .sw-grid-row__actions > .sw-grid-row__inline-edit-action')
                .should('be.visible')
                .click();

            cy.wait('@saveData').then((xhr) => {
                expect(xhr).to.have.property('status', 204);
            });
        });
    });
});
