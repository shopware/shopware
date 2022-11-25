/**
 * @package content
 */
// / <reference types="Cypress" />
import variantProduct from '../../../../fixtures/variant-product';

function uploadImageUsingFileUpload(path, name) {
    cy.get('.sw-cms-slot__config-modal .sw-media-upload-v2__file-input')
        .attachFile({
            filePath: path,
            fileName: name,
            mimeType: 'image/png'
        });

    const altValue = name.substr(0, name.lastIndexOf('.'));

    cy.get('.sw-media-preview-v2__item')
        .should('exist')
        .should('be.visible')
        .should('have.attr', 'alt', altValue);
}

describe('CMS: Check usage and editing of buy box elements', () => {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                return cy.createCmsFixture();
            })
            .then(() => {
                return cy.createProductFixture(variantProduct);
            })
            .then(() => {
                cy.viewport(1920, 1080);
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/cms/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@base @content: use simple buy box element', { tags: ['pa-content-management'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/cms-page/*`,
            method: 'PATCH'
        }).as('saveData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/category/*`,
            method: 'PATCH'
        }).as('saveCategory');

        cy.intercept('GET', '/widgets/cms/buybox/**').as('loadData');

        cy.get('.sw-cms-list-item--0').click();
        cy.get('.sw-cms-section__empty-stage').should('be.visible');

        // Add a text block
        cy.get('.sw-cms-section__empty-stage').click();
        cy.get('#sw-field--currentBlockCategory').select('Text');
        cy.get('.sw-cms-preview-text').should('be.visible');
        cy.get('.sw-cms-preview-text').dragTo('.sw-cms-section__empty-stage');
        cy.get('.sw-cms-block__config-overlay').invoke('show');
        cy.get('.sw-cms-block__config-overlay').should('be.visible');
        cy.get('.sw-cms-block__config-overlay').click();
        cy.get('.sw-cms-block__config-overlay.is--active').should('be.visible');
        cy.get('.sw-cms-slot .sw-cms-slot__overlay').invoke('show');

        // Replace current element with buy box element
        cy.get('.sw-cms-slot .sw-cms-slot__element-action').first().click();
        cy.get('.sw-cms-slot__element-selection').should('be.visible');
        cy.get('.sw-cms-el-preview-buy-box').click();

        // Configure element product
        cy.get('.sw-cms-slot .sw-cms-slot__overlay').invoke('show');
        cy.get('.sw-cms-slot .sw-cms-slot__settings-action').click();
        cy.get('.sw-cms-slot__config-modal').should('be.visible');
        cy.get('.sw-cms-el-config-buy-box .sw-entity-single-select').type('Variant product');
        cy.contains('.sw-product-variant-info__specification', 'green').click();
        cy.get('.sw-cms-slot__config-modal .sw-button--primary').click();
        cy.get('.sw-cms-el-buy-box__price').first().contains('€111.00');

        // Save new page layout
        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);
        cy.get('.sw-cms-detail__back-btn').click();

        // Assign layout to root category
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-category-tree__inner .sw-tree-item__element', 'Home').click();
        cy.get('.sw-category-detail__tab-cms').scrollIntoView().click();
        cy.get('.sw-card.sw-category-layout-card').scrollIntoView();
        cy.get('.sw-category-detail-layout__change-layout-action').click();
        cy.get('.sw-modal__dialog').should('be.visible');
        cy.get('.sw-cms-layout-modal__content-item--0 .sw-field--checkbox').click();
        cy.get('.sw-modal .sw-button--primary').click();
        cy.contains('.sw-card.sw-category-layout-card .sw-category-layout-card__desc-headline', 'Vierte Wand');
        cy.get('.sw-category-detail__save-action').click();

        cy.wait('@saveCategory').its('response.statusCode').should('equal', 204);

        // Verify layout in Storefront
        cy.visit('/');

        cy.window().then((win) => {
            /** @deprecated tag:v6.5.0 - Use `CheckoutPageObject.elements.lineItem` instead */
            const lineItemSelector = win.features['v6.5.0.0'] ? '.line-item' : '.cart-item';

            /** @deprecated tag:v6.5.0 - Use `${CheckoutPageObject.elements.lineItem}-details-characteristics` instead */
            const variantCharacteristicsSelector = win.features['v6.5.0.0'] ? '.line-item-details-characteristics' : '.cart-item-characteristics';

            cy.contains('.product-detail-price', '€111');
            cy.contains('.product-detail-ordernumber', 'TEST.2');
            cy.get('.product-detail-configurator-option-label[title="red"]').click();

            // Wait for reloading product variant
            cy.wait('@loadData').its('response.statusCode').should('equal', 200);
            cy.contains('.product-detail-ordernumber', 'TEST.1');

            // Off canvas
            cy.get('.btn-buy').click();
            cy.get('.offcanvas').should('be.visible');
            cy.contains(`${lineItemSelector}-price`, '€111');
            cy.contains(`${variantCharacteristicsSelector}`, 'color');
            cy.contains(`${variantCharacteristicsSelector}-option`, 'red');
            cy.get(`${lineItemSelector}-label[title="Variant product"]`).should('be.visible');
        });
    });

    it('@base @content: use simple gallery buy box block', { tags: ['pa-content-management'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/cms-page/*`,
            method: 'PATCH'
        }).as('saveData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/category/*`,
            method: 'PATCH'
        }).as('saveCategory');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/media/**/upload?extension=png&fileName=sw-login-background`,
            method: 'POST'
        }).as('saveDataFileUpload');

        cy.get('.sw-cms-list-item--0').click();
        cy.get('.sw-cms-section__empty-stage').should('be.visible');

        // Add gallery buybox block
        cy.get('.sw-cms-section__empty-stage').click();
        cy.get('#sw-field--currentBlockCategory').select('Commerce');
        cy.get('.sw-cms-preview-gallery-buybox').should('be.visible');
        cy.get('.sw-cms-preview-gallery-buybox').dragTo('.sw-cms-section__empty-stage');
        cy.get('.sw-cms-block__config-overlay').invoke('show');
        cy.get('.sw-cms-block__config-overlay').should('be.visible');
        cy.get('.sw-cms-block__config-overlay').click();
        cy.get('.sw-cms-block__config-overlay.is--active').should('be.visible');
        cy.get('.sw-cms-slot .sw-cms-slot__overlay').invoke('show');

        // Configure element image gallery
        cy.get('.sw-cms-slot .sw-cms-slot__settings-action').first().click();
        cy.get('.sw-cms-slot__config-modal').should('be.visible');

        // Upload image
        uploadImageUsingFileUpload('img/sw-login-background.png', 'sw-login-background.png');

        cy.wait('@saveDataFileUpload').its('response.statusCode').should('equal', 204);
        cy.awaitAndCheckNotification('File has been saved.');

        cy.get('.sw-cms-slot__config-modal .sw-modal__footer .sw-button--primary').click();

        // Configure element product
        cy.get('.sw-cms-slot:nth-of-type(2) .sw-cms-slot__settings-action').click();
        cy.get('.sw-cms-slot__config-modal').should('be.visible');
        cy.get('.sw-cms-el-config-buy-box .sw-entity-single-select').type('Variant product');
        cy.contains('.sw-product-variant-info__specification', 'blue').click();
        cy.get('.sw-cms-slot__config-modal .sw-button--primary').click();
        cy.get('.sw-cms-el-buy-box__price').first().contains('€111.00');

        // Save new page layout
        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);
        cy.get('.sw-cms-detail__back-btn').click();

        // Assign layout to root category
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-category-tree__inner .sw-tree-item__element', 'Home').click();
        cy.get('.sw-category-detail__tab-cms').scrollIntoView().click();
        cy.get('.sw-card.sw-category-layout-card').scrollIntoView();
        cy.get('.sw-category-detail-layout__change-layout-action').click();
        cy.get('.sw-modal__dialog').should('be.visible');
        cy.get('.sw-cms-layout-modal__content-item--0 .sw-field--checkbox').click();
        cy.get('.sw-modal .sw-button--primary').click();
        cy.contains('.sw-card.sw-category-layout-card .sw-category-layout-card__desc-headline', 'Vierte Wand');
        cy.get('.sw-category-detail__save-action').click();

        cy.wait('@saveCategory').its('response.statusCode').should('equal', 204);

        // Verify layout in Storefront
        cy.visit('/');
        cy.get('.gallery-slider-image')
            .should('have.attr', 'src')
            .and('match', /sw-login-background/);
        cy.contains('.product-detail-price', '€111');
        cy.contains('.product-detail-ordernumber', 'TEST.3');
    });
});
