describe('Seo: Test crud operations on templates', () => {
    const routeNames = {
        'Product detail page': 'product',
        'Landing page': 'landingPage',
        'Category page': 'category'
    };

    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                return cy.loginViaApi();
            })
            .then(() => {
                return cy.createCategoryFixture({
                    parent: {
                        name: 'ParentCategory',
                        active: true
                    }
                });
            })
            .then((data) => {
                let salesChannel;
                return cy.searchViaAdminApi({
                    endpoint: 'sales-channel',
                    data: {
                        field: 'name',
                        type: 'equals',
                        value: 'Storefront'
                    }
                }).then((data) => {
                    salesChannel = data.id;
                    cy.createDefaultFixture('cms-page', {}, 'cms-landing-page')
                }).then((data) => {
                    cy.createDefaultFixture('landing-page', {
                        cmsPage: data,
                        name: 'Some landing page',
                        url: 'some-landing-page',
                        salesChannels: [
                            {
                                id: salesChannel
                            }
                        ]
                    }, 'landing-page');
                });
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                return cy.createProductFixture({
                    name: 'Awesome product',
                    productNumber: 'RS-1337',
                    description: 'l33t',
                    price: [
                        {
                            currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                            net: 24,
                            linked: false,
                            gross: 128
                        }
                    ]
                });
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/seo/index`);
            });
    });

    it('@settings: update template', () => {
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'post'
        }).as('templateSaveCall');

        cy.get('.sw-seo-url-template-card__seo-url').should('have.length', 3);

        // for each card ...
        Object.keys(routeNames).forEach((routeName) => {
            cy.get('.sw-seo-url-template-card__seo-url').within(() => {
                cy.contains(routeName)
                    .parentsUntil('.sw-seo-url-template-card__seo-url')
                    .parent().within(() => {
                    // /... assert that the preview works correctly
                        cy.get('.icon--default-basic-checkmark-line');
                        // Seo Urls cannot contain spaces (as opposed to error messages)
                        cy.get('.sw-seo-url-template-card__preview-item').contains(/[^\s]+/).should('have.length', 1);

                        // Type the most simple url template, which prints the id
                        cy.get('#sw-field--seo-url-template-undefined').clear().type(`{{${routeNames[routeName]}.id}}`, { parseSpecialCharSequences: false });
                        // ids are 16 hex chars
                        cy.get('.sw-seo-url-template-card__preview-item').contains(/[a-z0-9]{16}/);
                    });
            });
        });

        // check that the templates can be saved
        cy.get('.smart-bar__actions').contains('Save').click();
        cy.wait('@templateSaveCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
    });

    it('@base @settings: update template for a sales channel', () => {
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'post'
        }).as('templateCreateCall');

        // check inherited saleschannel templates
        cy.get('.sw-sales-channel-switch')
            .typeSingleSelectAndCheck('Storefront', '.sw-entity-single-select');

        // assert that all inputs are disabled
        cy.get('.sw-seo-url-template-card').get('.sw-card__content').within(() => {
            cy.get('input').should('be.disabled');
        });

        // foreach card ...
        Object.keys(routeNames).forEach((routeName) => {
            cy.get('.sw-seo-url-template-card__seo-url').within(() => {
                cy.contains(routeName)
                    .parentsUntil('.sw-seo-url-template-card__seo-url')
                    .parent().within(() => {
                    // ... check that the inheritance can be removed
                        cy.get('.sw-inheritance-switch').click();
                        cy.get('input').should('not.be.disabled');
                        // ... and that the preview works
                        cy.get('.icon--default-basic-checkmark-line');
                        // Seo Urls cannot contain spaces (as opposed to error messages)
                        cy.get('.sw-seo-url-template-card__preview-item').contains(/[^\s]+/).should('have.length', 1);
                    });
            });
        });

        //
        cy.get('.smart-bar__actions').contains('Save').click();
        cy.wait('@templateCreateCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.awaitAndCheckNotification('SEO URL templates have been saved.');
    });

    it('@base @settings: cannot edit templates for headless sales channels', () => {
        cy.get('.sw-sales-channel-switch')
            .typeSingleSelectAndCheck('Headless', '.sw-entity-single-select');

        cy.get('.sw-card__content').contains('SEO URLs cannot be assigned to a headless Sales Channel.');
    });

    it('@base @settings: can save when the first template is empty', () => {
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'post'
        }).as('templateSaveCall');

        cy.get('.sw-block-field__block #sw-field--seo-url-template-undefined')
            .eq(0)
            .should('be.visible')
            .clear()
            .should('be.empty');

        // check that no error is thrown
        cy.get('.smart-bar__actions').contains('Save').click();
        cy.wait('@templateSaveCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            cy.get('.sw-field__error').should('not.contain', 'This value should not be blank');
        });

        // ensure the template is still visible
        cy.get('.sw-block-field__block #sw-field--seo-url-template-undefined')
            .eq(0)
            .should('be.visible');
    });

    it('@base @settings: can save when the second template is empty', () => {
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'post'
        }).as('templateSaveCall');

        cy.get('.sw-block-field__block #sw-field--seo-url-template-undefined')
            .eq(1)
            .should('be.visible')
            .clear()
            .should('be.empty');

        // check that no error is thrown
        cy.get('.smart-bar__actions').contains('Save').click();
        cy.wait('@templateSaveCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            cy.get('.sw-field__error').should('not.contain', 'This value should not be blank');
        });

        // ensure the template is still visible
        cy.get('.sw-block-field__block #sw-field--seo-url-template-undefined')
            .eq(1)
            .should('be.visible');
    });

    it('@base @settings: can save when the third template is empty', () => {
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'post'
        }).as('templateSaveCall');

        cy.get('.sw-block-field__block #sw-field--seo-url-template-undefined')
            .eq(2)
            .should('be.visible')
            .clear()
            .should('be.empty');

        // check that no error is thrown
        cy.get('.smart-bar__actions').contains('Save').click();
        cy.wait('@templateSaveCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            cy.get('.sw-field__error').should('not.contain', 'This value should not be blank');
        });

        // ensure the template is still visible
        cy.get('.sw-block-field__block #sw-field--seo-url-template-undefined')
            .eq(2)
            .should('be.visible');
    });
});
