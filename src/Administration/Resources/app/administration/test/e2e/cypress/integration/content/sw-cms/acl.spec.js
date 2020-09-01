// / <reference types="Cypress" />

describe('Category: Test ACL privileges', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createCmsFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@base @catalogue: can view shopping experiences listing', () => {
        cy.window().then((win) => {
            if (!win.Shopware.Feature.isActive('FEATURE_NEXT_3722')) {
                return;
            }

            cy.loginAsUserWithPermissions([
                {
                    key: 'cms',
                    role: 'viewer'
                }
            ]);

            cy.viewport(1920, 1080);
            cy.visit(`${Cypress.env('admin')}#/sw/cms/index`);

            cy.get('.sw-cms-list-item--0 > .sw-cms-list-item__info > .sw-cms-list-item__title')
                .contains('Vierte Wand');
        });
    });
});
