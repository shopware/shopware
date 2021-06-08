/// <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Product: Edit product media', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it('@catalogue: change media sorting', () => {
        const page = new ProductPageObject();

            // Request we want to wait for later
            cy.server();
            cy.route({
                url: `${Cypress.env('apiPath')}/product/*`,
                method: 'patch'
            }).as('saveProduct');

        // Open product
        cy.get('.sw-product-list-grid').should('be.visible');
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Add first image to product
        cy.get('.sw-product-media-form__previews').scrollIntoView();
        cy.fixture('img/sw-login-background.png').then(fileContent => {
            cy.get('#files').upload(
                {
                    fileContent,
                    fileName: 'sw-login-background.png',
                    mimeType: 'image/png'
                }, {
                    subjectType: 'input'
                }
            );
        });
        cy.get('.sw-product-image__image img')
            .should('have.attr', 'src')
            .and('match', /sw-login-background/);
        cy.awaitAndCheckNotification('File has been saved.');

        // Add second image to product
        cy.fixture('img/sw-test-image.png').then(fileContent => {
            cy.get('#files').upload(
                {
                    fileContent,
                    fileName: 'sw-test-image.png',
                    mimeType: 'image/png'
                }, {
                    subjectType: 'input'
                }
            );
        });
        cy.get('.sw-product-image:nth-of-type(2) img')
            .first()
            .should('have.attr', 'src')
            .and('match', /sw-test-image/);
        cy.awaitAndCheckNotification('File has been saved.');

        cy.get('.sw-product-image:nth-of-type(2)').dragTo('.sw-product-image:nth-of-type(1)');
        cy.get('.sw-product-image img')
            .first()
            .should('have.attr', 'src')
            .and('match', /sw-test-image/);

        // Save product
        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveProduct').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Verify in storefront
        cy.visit('/');
        cy.get('.product-name').click();
        cy.get('.gallery-slider-item').should('be.visible');
        cy.get('#tns2-item0 img')
            .should('have.attr', 'src')
            .and('match', /sw-test-image/);
    });

    it('@base @catalogue: set another cover image', () => {
        const page = new ProductPageObject();

            // Request we want to wait for later
            cy.server();
            cy.route({
                url: `${Cypress.env('apiPath')}/product/*`,
                method: 'patch'
            }).as('saveProduct');

        // Open product
        cy.get('.sw-product-list-grid').should('be.visible');
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Add first image to product
        cy.get('.sw-product-media-form__previews').scrollIntoView();
        cy.fixture('img/sw-login-background.png').then(fileContent => {
            cy.get('#files').upload(
                {
                    fileContent,
                    fileName: 'sw-login-background.png',
                    mimeType: 'image/png'
                }, {
                    subjectType: 'input'
                }
            );
        });
        cy.get('.sw-product-image__image img')
            .should('have.attr', 'src')
            .and('match', /sw-login-background/);
        cy.awaitAndCheckNotification('File has been saved.');

        // Add second image to product
        cy.fixture('img/sw-test-image.png').then(fileContent => {
            cy.get('#files').upload(
                {
                    fileContent,
                    fileName: 'sw-test-image.png',
                    mimeType: 'image/png'
                }, {
                    subjectType: 'input'
                }
            );
        });
        cy.get('.sw-product-image:nth-of-type(2) img')
            .first()
            .should('have.attr', 'src')
            .and('match', /sw-test-image/);
        cy.awaitAndCheckNotification('File has been saved.');

        // Change cover image
        cy.get(`.sw-product-image:nth-of-type(2) ${page.elements.contextMenuButton}`)
            .click({ force: true });
        cy.contains('Use as cover').click();
        cy.get('.sw-product-image:nth-of-type(2) .sw-label--primary').should('be.visible');
        cy.get('.sw-product-media-form__cover-image img')
            .first()
            .should('have.attr', 'src')
            .and('match', /sw-test-image/);

        // Save product
        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveProduct').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Verify in storefront
        cy.visit('/');
        cy.get('.product-image-wrapper img')
            .should('have.attr', 'src')
            .and('match', /sw-test-image/);
        cy.get('.product-name').click();
        cy.get('.gallery-slider-item').should('be.visible');
        cy.get('#tns2-item1.tns-nav-active').should('be.visible');
        cy.get('#tns1-item1 img')
            .should('have.attr', 'src')
            .and('match', /sw-test-image/);
    });

    it('@catalogue: remove a product\'s image', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'patch'
        }).as('saveProduct');
        cy.route({
            url: `${Cypress.env('apiPath')}/product/**/media/*`,
            method: 'delete'
        }).as('removeProductMedia');

        // Open product
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Add first image to product
        cy.get('.sw-product-media-form__previews').scrollIntoView();

        if (Cypress.isBrowser({ family: 'chromium' })) {
            cy.fixture('img/sw-login-background.png').then(fileContent => {
                cy.get('#files').upload(
                    {
                        fileContent,
                        fileName: 'sw-login-background.png',
                        mimeType: 'image/png'
                    }, {
                        subjectType: 'input'
                    }
                );
            });

            cy.get('.sw-product-image__image img')
                .should('have.attr', 'src')
                .and('match', /sw-login-background/);
        }

        if (Cypress.isBrowser('firefox')) {
            // Upload medium
            cy.get('.sw-media-upload-v2__content .sw-context-button__button').click();
            cy.contains('Upload file from URL').click();
            cy.get('input[name=sw-field--url]').should('be.visible')
                .type('http://assets.shopware.com/sw_logo_white.png');
            cy.get('.sw-media-url-form__submit-button').click();

            cy.awaitAndCheckNotification('File has been saved.');
            cy.get('.sw-media-preview__placeholder').should('not.exist');

            cy.get('.sw-media-preview-v2').should('be.visible');
        }

        cy.get('.sw-product-image__image img')
            .should('have.attr', 'src')
            .and('match', /sw_logo_white|sw-login-background/);

        // Save product
        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveProduct').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Remove image
        cy.get(`.sw-product-image:nth-of-type(1) ${page.elements.contextMenuButton}`)
            .click({ force: true });
        cy.contains('Remove').click();
        cy.get('.sw-product-media-form__cover-image.is--placeholder').should('be.visible');

        // Save product
        cy.get(page.elements.productSaveAction).click();

        // Verify removal
        cy.wait('@removeProductMedia').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`)
            .contains('Product name').click();

        cy.get('.sw-product-media-form__previews').scrollIntoView();
        cy.get('.sw-product-media-form__cover-image.is--placeholder').should('be.visible');
    });
});
