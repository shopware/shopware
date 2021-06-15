// / <reference types="Cypress" />
import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

function uploadImageUsingFileUpload(path, name, index = 1) {
    cy.fixture(path).then(fileContent => {
        cy.get('.sw-product-variants-media-upload .sw-media-upload-v2__file-input').upload(
            {
                fileContent,
                fileName: name,
                mimeType: 'image/png'
            }, {
                subjectType: 'input'
            }
        );
    });

    const altValue = name.substr(0, name.lastIndexOf('.'));
    const regex = new RegExp(name);

    cy.get(`.sw-product-variants-media-upload__image:nth-of-type(${index}) img`)
        .should('exist')
        .should('be.visible')
        .should('have.attr', 'alt', altValue)
        .should('have.attr', 'src')
        .and('match', regex);
}

function createVariant(page) {
    // Navigate to variant generator listing and start
    cy.clickContextMenuItem(
        '.sw-entity-listing__context-menu-edit-action',
        page.elements.contextMenuButton,
        `${page.elements.dataGridRow}--0`
    );

    cy.get('.sw-product-detail__tab-variants').click();
    cy.get(page.elements.loader).should('not.exist');
    cy.get(`.sw-product-detail-variants__generated-variants__empty-state ${page.elements.ghostButton}`)
        .should('be.visible')
        .click();
    cy.get('.sw-product-modal-variant-generation').should('be.visible');

    // Create and verify one-dimensional variant
    page.generateVariants('Color', [0, 1, 2], 3);
    cy.get('.sw-product-variants-overview').should('be.visible');

    cy.get('.sw-data-grid__body').contains('Red');
    cy.get('.sw-data-grid__body').contains('Yellow');
    cy.get('.sw-data-grid__body').contains('Green');
    cy.get('.sw-data-grid__body').contains('.1');
    cy.get('.sw-data-grid__body').contains('.2');
    cy.get('.sw-data-grid__body').contains('.3');

    cy.get('.sw-loader').should('not.exist');
}

describe('Product: Test variants', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createPropertyFixture({
                    options: [{ name: 'Red' }, { name: 'Yellow' }, { name: 'Green' }]
                });
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it('@catalogue: inline edit variant image', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'patch'
        }).as('saveData');

        cy.route({
            url: `${Cypress.env('apiPath')}/search/property-group`,
            method: 'post'
        }).as('searchVariantGroup');

        cy.route({
            url: `${Cypress.config('baseUrl')}/detail/**/switch?options=*`,
            method: 'get'
        }).as('changeVariant');

        createVariant(page);

        // Get green variant
        cy.get('.sw-simple-search-field--form input').should('be.visible');

        cy.wait('@searchVariantGroup').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            cy.get('.sw-simple-search-field--form input').type('Green');
        });

        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.get('.sw-data-grid__row--1').should('not.exist');
        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--name').contains('Green');

        // Set surcharge
        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--name').should('be.visible');
        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--name').dblclick({ force: true });
        cy.get('.is--inline-edit .sw-data-grid__cell--media .sw-inheritance-switch').should('be.visible');
        cy.get('.is--inline-edit .sw-data-grid__cell--media .sw-inheritance-switch').click();

        // Upload image
        uploadImageUsingFileUpload('img/sw-login-background.png', 'sw-login-background.png');
        cy.awaitAndCheckNotification('File has been saved.');

        cy.get('.icon--custom-uninherited').should('be.visible');
        cy.get('.sw-data-grid__inline-edit-save').click();

        // Validate product
        cy.wait('@productCall').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
            cy.awaitAndCheckNotification('Product "Green" has been saved.');
        });

        cy.get('.sw-data-grid__row--0 .sw-product-variants-overview__variation-link').click();
        cy.get('.sw-product-media-form__previews').scrollIntoView().then(() => {
            cy.get('.sw-product-media-form__previews .sw-media-preview-v2__item')
                .should('exist')
                .should('be.visible')
                .should('have.attr', 'src')
                .and('match', /sw-login-background/);
        });

        // Validate in Storefront
        cy.visit('/');
        cy.get('.product-name').click();

        // Ensure that variant "Green" is checked at the moment the test runs
        cy.get('.product-detail-configurator-option-label[title="Green"]').then(($btn) => {
            const inputId = $btn.attr('for');

            cy.get(`#${inputId}`).then(($input) => {
                if (!$input.attr('checked')) {
                    cy.contains('Green').click();

                    cy.wait('@changeVariant').then((xhr) => {
                        expect(xhr).to.have.property('status', 200);
                        cy.get('.gallery-slider-single-image img')
                            .should('have.attr', 'src')
                            .and('match', /sw-login-background/);
                    });
                } else {
                    cy.log('Variant "Green" is already open.');
                    cy.get('.gallery-slider-single-image img')
                        .should('have.attr', 'src')
                        .and('match', /sw-login-background/);
                }
            });
        });

        cy.contains('Red').click();

        cy.wait('@changeVariant').then((xhr) => {
            expect(xhr).to.have.property('status', 200);

            cy.get('.gallery-slider-single-image img').should('not.exist');
            cy.get('.icon-placeholder').should('be.visible');
        });
    });

    it('@catalogue: set cover for variant image', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'patch'
        }).as('saveData');

        cy.route({
            url: `${Cypress.env('apiPath')}/search/property-group`,
            method: 'post'
        }).as('searchVariantGroup');

        cy.route({
            url: `${Cypress.config('baseUrl')}/detail/**/switch?options=*`,
            method: 'get'
        }).as('changeVariant');

        createVariant(page);

        // Set green variant at main variant
        cy.get('.sw-product-variants__configure-storefront-action').click();

        cy.get('.sw-modal__body').should('be.visible');
        cy.get('.sw-product-modal-delivery__sidebar .sw-tabs-item:nth-of-type(3)').click();
        cy.get('.sw-product-variants-delivery-listing-mode .sw-field__radio-option:nth-of-type(1)').click();
        cy.get('.sw-product-variants-delivery-listing_entity-select').click();
        cy.get('.sw-product-variant-info__specification').contains('Green').click();

        cy.get('.sw-product-modal-delivery__save-button').click();
        cy.get('.sw-modal__body').should('not.exist');

        // Get green variant
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-simple-search-field--form input').should('be.visible');

        cy.wait('@searchVariantGroup').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            cy.get('.sw-simple-search-field--form input').type('Green');
        });

        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.get('.sw-data-grid__row--1').should('not.exist');
        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--name').contains('Green');

        // Set surcharge
        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--name').should('be.visible');
        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--name').dblclick({ force: true });
        cy.get('.is--inline-edit .sw-data-grid__cell--media .sw-inheritance-switch').should('be.visible');
        cy.get('.is--inline-edit .sw-data-grid__cell--media .sw-inheritance-switch').click();

        // Upload image
        uploadImageUsingFileUpload('img/sw-login-background.png', 'sw-login-background.png');
        cy.awaitAndCheckNotification('File has been saved.');

        uploadImageUsingFileUpload('img/sw-test-image.png', 'sw-test-image.png', 2);
        cy.awaitAndCheckNotification('File has been saved.');

        cy.get('.sw-product-variants-media-upload__cover-image img')
            .should('exist')
            .should('be.visible')
            .should('have.attr', 'src')
            .and('match', /sw-login-background/);

        cy.get('.sw-product-variants-media-upload__image:nth-of-type(2)').click();
        cy.get('.sw-context-menu__content').should('be.visible');
        cy.get('.sw-context-menu-item').eq(0).click();

        cy.get('.sw-context-menu__content').should('not.visible');

        cy.get('.sw-product-variants-media-upload__cover-image img')
            .should('exist')
            .should('be.visible')
            .should('have.attr', 'src')
            .and('match', /sw-test-image/);

        cy.get('.icon--custom-uninherited').should('be.visible');
        cy.get('.sw-data-grid__inline-edit-save').click();

        // Validate product
        cy.wait('@productCall').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
            cy.awaitAndCheckNotification('Product "Green" has been saved.');
        });

        cy.get('.sw-data-grid__row--0 .sw-product-variants-overview__variation-link').click();
        cy.get('.sw-product-media-form__previews').scrollIntoView().then(() => {
            cy.get('.sw-product-image:nth-of-type(1) img')
                .should('be.visible')
                .should('have.attr', 'src')
                .and('match', /sw-login-background/);

            cy.get('.sw-product-image:nth-of-type(2) img')
                .should('be.visible')
                .should('have.attr', 'src')
                .and('match', /sw-test-image/);

            cy.get('.sw-product-media-form__cover-image img')
                .should('be.visible')
                .should('have.attr', 'src')
                .and('match', /sw-test-image/);
        });

        // Validate in Storefront
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

        cy.contains('Red').click();

        cy.wait('@changeVariant').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            cy.get('.icon-placeholder').should('be.visible');
        });
    });


    it('@catalogue: view preview image modal', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'patch'
        }).as('saveData');

        cy.route({
            url: `${Cypress.env('apiPath')}/search/property-group`,
            method: 'post'
        }).as('searchVariantGroup');

        createVariant(page);

        // Get green variant
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-simple-search-field--form input').should('be.visible');

        cy.wait('@searchVariantGroup').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            cy.get('.sw-simple-search-field--form input').type('Green');
        });

        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.get('.sw-data-grid__row--1').should('not.exist');
        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--name').contains('Green');

        // Set surcharge
        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--name').should('be.visible');
        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--name').dblclick({ force: true });
        cy.get('.is--inline-edit .sw-data-grid__cell--media .sw-inheritance-switch').should('be.visible');
        cy.get('.is--inline-edit .sw-data-grid__cell--media .sw-inheritance-switch').click();

        // Upload image
        uploadImageUsingFileUpload('img/sw-login-background.png', 'sw-login-background.png');
        cy.awaitAndCheckNotification('File has been saved.');

        // Upload image from URL
        cy.get('.sw-product-variants-media-upload__browse-button').click();
        cy.get('.sw-modal__body').should('be.visible');
        cy.get('.sw-media-modal-v2__tabs .sw-tabs-item').eq(1).click();
        cy.get('.sw-media-upload-v2__switch-mode').click();
        cy.contains('Upload file from URL').click();
        cy.get('input[name=sw-field--url]').should('be.visible')
            .type('http://assets.shopware.com/media/website/pages/frontpage/growth_customerloyalty_en-2x.png');
        cy.get('.sw-media-url-form__submit-button').click();

        // Check if image uploaded successfully
        cy.awaitAndCheckNotification('File has been saved.');

        // Add image to variant
        cy.get('.sw-modal__footer .sw-button--primary').click();
        cy.get('.sw-modal__body').should('not.exist');

        cy.get('.sw-product-variants-media-upload__image:nth-of-type(2) img')
            .should('have.attr', 'src')
            .and('match', /growth_customerloyalty_en/);
        cy.get('.icon--custom-uninherited').should('be.visible');
        cy.get('.sw-data-grid__row--0 .sw-data-grid__inline-edit-save').click();

        // Validate product
        cy.wait('@productCall').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
            cy.awaitAndCheckNotification('Product "Green" has been saved.');
            cy.get('.sw-data-grid-skeleton').should('not.exist');
        });

        // Open preview modal
        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--name').dblclick({ force: true });
        cy.get('.sw-media-upload-v2__preview.is--cover').siblings('.sw-context-button').click();
        cy.get('.sw-context-menu__content').should('be.visible');
        cy.contains('Preview image').click();

        // Open oom and slider function
        cy.get('.sw-image-preview-modal').should('be.visible');

        cy.get('.sw-image-preview-modal__image-slider .sw-image-slider__element-image.is--active')
            .should('be.visible')
            .should('have.attr', 'src')
            .and('match', /sw-login-background/);

        // Slide to another image
        cy.get('.sw-image-preview-modal__button-action').eq(0).should('be.disabled');
        cy.get('.sw-image-preview-modal__button-action').eq(1).should('be.disabled');
        cy.get('.sw-image-preview-modal__button-action').eq(2).should('be.disabled');

        cy.get('.sw-image-preview-modal__image-slider .arrow-left').click();
        cy.get('.sw-image-preview-modal__image-slider .sw-image-slider__element-image.is--active')
            .should('have.attr', 'src')
            .and('match', /growth_customerloyalty_en/);

        // Check states of zoom buttons
        cy.get('.sw-image-preview-modal__button-action').eq(0).should('be.disabled');
        cy.get('.sw-image-preview-modal__button-action').eq(1).should('be.disabled');
        cy.get('.sw-image-preview-modal__button-action').eq(2).should('not.disabled');

        // Check states of zoom buttons when clicking on button zoom in
        cy.get('.sw-image-preview-modal__button-action').eq(2).click();
        cy.get('.sw-image-preview-modal__button-action').eq(0).should('not.disabled');
        cy.get('.sw-image-preview-modal__button-action').eq(1).should('not.disabled');
        cy.get('.sw-image-preview-modal__button-action').eq(2).should('not.disabled');

        // Check states of zoom buttons when clicking on button zoom in
        cy.get('.sw-image-preview-modal__button-action').eq(2).click();
        cy.get('.sw-image-preview-modal__button-action').eq(0).should('not.disabled');
        cy.get('.sw-image-preview-modal__button-action').eq(1).should('not.disabled');
        cy.get('.sw-image-preview-modal__button-action').eq(2).should('be.disabled');

        // Check states of zoom buttons when clicking on button zoom out
        cy.get('.sw-image-preview-modal__button-action').eq(0).click();
        cy.get('.sw-image-preview-modal__button-action').eq(0).should('not.disabled');
        cy.get('.sw-image-preview-modal__button-action').eq(1).should('not.disabled');
        cy.get('.sw-image-preview-modal__button-action').eq(2).should('not.disabled');

        // Check states of zoom buttons when clicking on button reset
        cy.get('.sw-image-preview-modal__button-action').eq(1).click();
        cy.get('.sw-image-preview-modal__button-action').eq(0).should('be.disabled');
        cy.get('.sw-image-preview-modal__button-action').eq(1).should('be.disabled');
        cy.get('.sw-image-preview-modal__button-action').eq(2).should('not.disabled');
    });
});
