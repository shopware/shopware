// / <reference types="Cypress" />

describe('CMS: Check usage and editing of cross selling element', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createCmsFixture();
            })
            .then(() => {
                return cy.createProductFixture({
                    name: 'First product',
                    productNumber: 'RS-11111',
                    description: 'Pudding wafer apple pie fruitcake cupcake. Biscuit cotton candy gingerbread liquorice tootsie roll caramels soufflé. Wafer gummies chocolate cake soufflé.',
                    crossSellings: [
                        {
                            name: 'You may like it',
                            active: true
                        }
                    ]
                });
            })
            .then(() => {
                return cy.createProductFixture({
                    name: 'Second product',
                    productNumber: 'RS-22222',
                    description: 'Jelly beans jelly-o toffee I love jelly pie tart cupcake topping. Cotton candy jelly beans tootsie roll pie tootsie roll chocolate cake brownie. I love pudding brownie I love.'
                });
            })
            .then(() => {
                return cy.createProductFixture({
                    name: 'Third product',
                    productNumber: 'RS-33333',
                    description: 'Cookie bonbon tootsie roll lemon drops soufflé powder gummies bonbon. Jelly-o lemon drops cheesecake. I love carrot cake I love toffee jelly beans I love jelly.'
                });
            })
            .then(() => {
                cy.viewport(1920, 1080);
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it('@content: use cross selling element in another block', () => {
        cy.window().then((win) => {
            if (!win.Shopware.Feature.isActive('FEATURE_NEXT_10078')) {
                cy.log('Skipping test of deactivated feature \'FEATURE_NEXT_10078\' flag');
                return;
            }

            cy.server();
            cy.route({
                url: `${Cypress.env('apiPath')}/cms-page/*`,
                method: 'patch'
            }).as('saveData');

            cy.route({
                url: `${Cypress.env('apiPath')}/category/*`,
                method: 'patch'
            }).as('saveCategory');

            cy.route({
                url: `${Cypress.env('apiPath')}/product/*`,
                method: 'patch'
            }).as('saveProductData');

            // Open product and add cross selling
            cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
            cy.contains('First product').click();

            cy.get('.sw-product-detail__tab-cross-selling').click();
            cy.get('input[name="sw-field--crossSelling-active"]').click();

            // Fill in cross selling form
            cy.get('#sw-field--crossSelling-type').select('Manual selection');
            cy.get('input[name="sw-field--crossSelling-active"]').click();

            // Save and verify cross selling
            cy.get('.sw-button-process').click();
            cy.wait('@saveProductData').then((xhr) => {
                expect(xhr).to.have.property('status', 204);
            });

            // Add products to cross selling
            cy.get('.sw-product-cross-selling-assignment__select-container .sw-entity-single-select__selection').type('Second');
            cy.get('.sw-select-result').should('be.visible');
            cy.get('.sw-select-option--1').should('not.exist');
            cy.contains('.sw-select-option--0', 'Second product').click();
            cy.get('.sw-card__title').click();
            cy.get('.sw-data-grid__cell--product-translated-name').contains('Second product');

            // Add more products to cross selling
            cy.get('.sw-product-cross-selling-assignment__select-container .sw-entity-single-select__selection').type('Third');
            cy.get('.sw-select-result').should('be.visible');
            cy.get('.sw-select-option--1').should('not.exist');
            cy.contains('.sw-select-option--0', 'Third product').click();
            cy.get('.sw-card__title').click();
            cy.get('.sw-data-grid__cell--product-translated-name').contains('Third product');

            // Save and verify cross selling
            cy.get('.sw-button-process').click();
            cy.wait('@saveProductData').then((xhr) => {
                expect(xhr).to.have.property('status', 204);
            });

            cy.visit(`${Cypress.env('admin')}#/sw/cms/index`);

            cy.get('.sw-cms-list-item--0').click();

            // Add text block
            cy.get('.sw-cms-section__empty-stage').click();
            cy.get('#sw-field--currentBlockCategory').select('Text');
            cy.get('.sw-cms-preview-text').should('be.visible');
            cy.get('.sw-cms-preview-text').dragTo('.sw-cms-section__empty-stage');

            cy.get('.sw-cms-block__config-overlay').invoke('show');
            cy.get('.sw-cms-block__config-overlay').should('be.visible');
            cy.get('.sw-cms-block__config-overlay').click();
            cy.get('.sw-cms-block__config-overlay.is--active').should('be.visible');
            cy.get('.sw-cms-slot .sw-cms-slot__overlay').invoke('show');

            // Replace text element with cross selling element
            cy.get('.sw-cms-slot .sw-cms-slot__element-action').click();
            cy.get('.sw-cms-slot__element-selection').should('be.visible');

            cy.get('.sw-cms-el-preview-cross-selling').click();

            // Select a product with cross selling data
            cy.get('.sw-cms-slot .sw-cms-slot__settings-action').first().click();
            cy.get('.sw-cms-el-config-cross-selling .sw-entity-single-select')
                .typeSingleSelectAndCheck('First product', '.sw-cms-el-config-cross-selling .sw-entity-single-select');
            cy.get('.sw-cms-slot__config-modal .sw-button--primary').click();

            // Save new page layout
            cy.get('.sw-cms-detail__save-action').click();
            cy.wait('@saveData').then(() => {
                cy.get('.sw-cms-detail__back-btn').click();
            });

            // Assign layout to root category
            cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
            cy.get('.sw-tree-item__element').contains('Home').click();
            cy.get('.sw-card.sw-category-layout-card').scrollIntoView();
            cy.get('.sw-category-detail-layout__change-layout-action').click();
            cy.get('.sw-modal__dialog').should('be.visible');
            cy.get('.sw-cms-layout-modal__content-item--0 .sw-field--checkbox').click();
            cy.get('.sw-modal .sw-button--primary').click();
            cy.get('.sw-card.sw-category-layout-card .sw-category-layout-card__desc-headline').contains('Vierte Wand');
            cy.get('.sw-category-detail__save-action').click();

            cy.wait('@saveCategory').then((response) => {
                expect(response).to.have.property('status', 204);
            });

            // Verify layout in Storefront
            cy.visit('/');
            cy.get('.product-cross-selling-tab-navigation')
                .scrollIntoView()
                .should('be.visible');
            cy.get('.product-detail-tab-navigation-link.active').contains('You may like it');
            cy.get('.product-slider-item .product-name[title="Second product"]')
                .should('be.visible');
            cy.get('.product-slider-item .product-name[title="Third product"]')
                .should('be.visible');
        });
    });

    it('@content: use cross selling block in landing page', () => {
        cy.window().then((win) => {
            if (!win.Shopware.Feature.isActive('FEATURE_NEXT_10078')) {
                cy.log('Skipping test of deactivated feature \'FEATURE_NEXT_10078\' flag');
                return;
            }

            cy.server();
            cy.route({
                url: `${Cypress.env('apiPath')}/cms-page/*`,
                method: 'patch'
            }).as('saveData');

            cy.route({
                url: `${Cypress.env('apiPath')}/category/*`,
                method: 'patch'
            }).as('saveCategory');

            cy.route({
                url: `${Cypress.env('apiPath')}/product/*`,
                method: 'patch'
            }).as('saveProductData');

            // Open product and add cross selling
            cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
            cy.contains('First product').click();

            cy.get('.sw-product-detail__tab-cross-selling').click();
            cy.get('input[name="sw-field--crossSelling-active"]').click();

            // Fill in cross selling form
            cy.get('#sw-field--crossSelling-type').select('Manual selection');
            cy.get('input[name="sw-field--crossSelling-active"]').click();

            // Save and verify cross selling
            cy.get('.sw-button-process').click();
            cy.wait('@saveProductData').then((xhr) => {
                expect(xhr).to.have.property('status', 204);
            });

            // Add products to cross selling
            cy.get('.sw-product-cross-selling-assignment__select-container .sw-entity-single-select__selection').type('Second');
            cy.get('.sw-select-result').should('be.visible');
            cy.get('.sw-select-option--1').should('not.exist');
            cy.contains('.sw-select-option--0', 'Second product').click();
            cy.get('.sw-card__title').click();
            cy.get('.sw-data-grid__cell--product-translated-name').contains('Second product');

            // Add more products to cross selling
            cy.get('.sw-product-cross-selling-assignment__select-container .sw-entity-single-select__selection').type('Third');
            cy.get('.sw-select-result').should('be.visible');
            cy.get('.sw-select-option--1').should('not.exist');
            cy.contains('.sw-select-option--0', 'Third product').click();
            cy.get('.sw-card__title').click();
            cy.get('.sw-data-grid__cell--product-translated-name').contains('Third product');

            // Save and verify cross selling
            cy.get('.sw-button-process').click();
            cy.wait('@saveProductData').then((xhr) => {
                expect(xhr).to.have.property('status', 204);
            });

            cy.visit(`${Cypress.env('admin')}#/sw/cms/index`);

            cy.get('.sw-cms-list-item--0').click();

            // Add text block
            cy.get('.sw-cms-section__empty-stage').click();
            cy.get('#sw-field--currentBlockCategory').select('Commerce');
            cy.get('.sw-cms-sidebar__block-selection > div:nth-of-type(6)').scrollIntoView();
            cy.get('.sw-cms-block-preview-cross-selling').should('be.visible');
            cy.get('.sw-cms-block-preview-cross-selling').dragTo('.sw-cms-section__empty-stage');

            cy.get('.sw-cms-block__config-overlay').invoke('show');
            cy.get('.sw-cms-block__config-overlay').should('be.visible');
            cy.get('.sw-cms-block__config-overlay').click();
            cy.get('.sw-cms-block__config-overlay.is--active').should('be.visible');
            cy.get('.sw-cms-slot .sw-cms-slot__overlay').invoke('show');

            // Select a product with cross selling data
            cy.get('.sw-cms-slot .sw-cms-slot__settings-action').first().click();
            cy.get('.sw-cms-el-config-cross-selling .sw-entity-single-select')
                .typeSingleSelectAndCheck('First product', '.sw-cms-el-config-cross-selling .sw-entity-single-select');
            cy.get('.sw-cms-slot__config-modal .sw-button--primary').click();

            // Save new page layout
            cy.get('.sw-cms-detail__save-action').click();
            cy.wait('@saveData').then(() => {
                cy.get('.sw-cms-detail__back-btn').click();
            });

            // Assign layout to root category
            cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
            cy.get('.sw-tree-item__element').contains('Home').click();
            cy.get('.sw-card.sw-category-layout-card').scrollIntoView();
            cy.get('.sw-category-detail-layout__change-layout-action').click();
            cy.get('.sw-modal__dialog').should('be.visible');
            cy.get('.sw-cms-layout-modal__content-item--0 .sw-field--checkbox').click();
            cy.get('.sw-modal .sw-button--primary').click();
            cy.get('.sw-card.sw-category-layout-card .sw-category-layout-card__desc-headline').contains('Vierte Wand');
            cy.get('.sw-category-detail__save-action').click();

            cy.wait('@saveCategory').then((response) => {
                expect(response).to.have.property('status', 204);
            });

            // Verify layout in Storefront
            cy.visit('/');
            cy.get('.product-cross-selling-tab-navigation')
                .scrollIntoView()
                .should('be.visible');
            cy.get('.product-detail-tab-navigation-link.active').contains('You may like it');
            cy.get('.product-slider-item .product-name[title="Second product"]')
                .should('be.visible');
            cy.get('.product-slider-item .product-name[title="Third product"]')
                .should('be.visible');
        });
    });
});
