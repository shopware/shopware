// / <reference types="Cypress" />
/**
 * @package inventory
 */
import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

function addFileToVariant(optionName, index, fixture) {
    // eslint-disable-next-line cypress/no-assigning-return-values
    const digitalVariant = cy.get('.sw-data-grid__cell--options')
        .filter(getVariantRowFilter(optionName))
        .eq(index);
    digitalVariant.find('.sw-field--switch__input').click();
    digitalVariant.parents('.sw-data-grid__cell--options').find('.sw-media-upload-v2__button').click();
    // Add file to variant
    digitalVariant.parents('.sw-data-grid__cell--options').find('.sw-media-upload-v2__file-input').attachFile(fixture);
    cy.awaitAndCheckNotification('File has been saved.');
    digitalVariant.parents('.sw-data-grid__cell--options').find('.sw-media-preview-v2__item').should('exist');
}

function getVariantRowFilter(optionName) {
    return (index, elt) => { return elt.innerText.match(new RegExp(optionName)); };
}

function addFilesToAllVariants(fixture) {
    cy.get('.sw-product-modal-variant-generation__upload-all-container .sw-field--switch__input').click();
    cy.get('.sw-product-modal-variant-generation__upload-all-container .sw-media-upload-v2__button').should('be.visible');
    cy.get('.sw-product-modal-variant-generation__upload-all-container .sw-media-upload-v2__button').should('not.be.disabled');
    cy.get('.sw-product-modal-variant-generation__upload-all-container .sw-media-upload-v2__button').click();
    // Add file to all variants
    cy.get('.sw-product-modal-variant-generation__upload-all-container .sw-media-upload-v2__file-input').attachFile(fixture);
    cy.awaitAndCheckNotification('File has been saved.');
    cy.get('.sw-product-modal-variant-generation__upload-all-container .sw-media-preview-v2__item').should('exist');
}

describe('Product: Test digital variants', { tags: ['VUE3']}, () => {
    beforeEach(() => {
        cy.setLocaleToEnGb()
            .then(() => {
                return cy.createPropertyFixture({
                    name: 'Distribution',
                    options: [{ name: 'Hardcover' }, { name: 'E-Book' }],
                });
            })
            .then(() => {
                return cy.createPropertyFixture({
                    name: 'Edition',
                    options: [{ name: 'Special', position: 1 }, { name: 'Extended', position: 2 }, { name: 'Regular', position: 3 }],
                });
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@base @catalogue: add digital variant to product', { tags: ['pa-inventory'] }, () => {
        const page = new ProductPageObject();
        const digitalIndicatorClass = '.sw-product-variants-overview__digital-indicator';

        // Navigate to variant generator listing and start
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.get('.sw-product-detail__tab-variants').click();
        cy.get(page.elements.loader).should('not.exist');

        // Create and verify one-dimensional variant
        cy.get(`.sw-product-detail-variants__generated-variants-empty-state ${page.elements.ghostButton}`)
            .should('be.visible')
            .click();
        cy.get('.sw-product-modal-variant-generation').should('be.visible');

        page.generateVariants('Distribution', [0, 1], 2, undefined, false);
        page.generateVariants('Edition', [2], 2, undefined, false);

        cy.get('.sw-product-variant-generation__next-action').click();
        cy.get('.sw-product-modal-variant-generation__upload_files').should('be.visible');
        cy.get('.sw-data-grid__cell--options').should('have.length', 2);

        addFileToVariant('E-Book', 0, {
            filePath: 'img/sw-test-image.png',
            fileName: 'sw-test-image.png',
            mimeType: 'image/png',
        });

        page.proceedVariantsGeneration(2);

        cy.get('.sw-product-variants-overview').should('be.visible');
        if (Cypress.env('VUE3')) {
            cy.get('.sw-skeleton').should('not.exist');
        }

        cy.get('.sw-data-grid__row').filter(getVariantRowFilter('Hardcover')).find(digitalIndicatorClass).should('not.exist');
        cy.get('.sw-data-grid__row').filter(getVariantRowFilter('E-Book')).find(digitalIndicatorClass).should('exist');
        cy.contains('.sw-data-grid__body', '.1');
        cy.contains('.sw-data-grid__body', '.2');

        // Create and verify multi-dimensional variant
        cy.get(`.sw-product-variants__generate-action${page.elements.ghostButton}`)
            .should('be.visible')
            .click();
        cy.get('.sw-product-modal-variant-generation').should('be.visible');

        page.generateVariants('Edition', [0, 1], 4, undefined, false);

        cy.get('.sw-product-variant-generation__next-action').click();
        cy.get('.sw-product-modal-variant-generation__upload_files').should('be.visible');
        cy.get('.sw-data-grid__cell--options').should('have.length', 4);

        addFileToVariant('E-Book', 0, {
            filePath: 'img/sw-storefront-en.jpg',
            fileName: 'sw-storefront-en.jpg',
            mimeType: 'image/jpg',
        });

        addFileToVariant('E-Book', 1, {
            filePath: 'img/sw-login-background.png',
            fileName: 'sw-login-background.png',
            mimeType: 'image/png',
        });

        page.proceedVariantsGeneration(4);

        cy.get('.sw-product-variants-overview').should('be.visible');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Create zero variants
        cy.get(`.sw-product-variants__generate-action${page.elements.ghostButton}`)
            .should('be.visible')
            .click();
        cy.get('.sw-product-modal-variant-generation').should('be.visible');

        cy.get('.sw-product-variant-generation__next-action').click();
        cy.get('.sw-product-modal-variant-generation__upload-card').should('not.exist');

        page.proceedVariantsGeneration(0);

        if (Cypress.env('VUE3')) {
            cy.get('.sw-product-variants-overview').should('be.visible');
            cy.get('.sw-skeleton').should('not.exist');
        }

        // check final table of variants
        cy.contains('.sw-data-grid__body', 'Regular');
        cy.contains('.sw-data-grid__body', 'Extended');
        cy.contains('.sw-data-grid__body', 'Special');
        cy.get('.sw-data-grid__row').filter(getVariantRowFilter('Hardcover')).eq(0).find(digitalIndicatorClass).should('not.exist');
        cy.get('.sw-data-grid__row').filter(getVariantRowFilter('Hardcover')).eq(1).find(digitalIndicatorClass).should('not.exist');
        cy.get('.sw-data-grid__row').filter(getVariantRowFilter('Hardcover')).eq(2).find(digitalIndicatorClass).should('not.exist');
        cy.get('.sw-data-grid__row').filter(getVariantRowFilter('E-Book')).eq(0).find(digitalIndicatorClass).should('exist');
        cy.get('.sw-data-grid__row').filter(getVariantRowFilter('E-Book')).eq(1).find(digitalIndicatorClass).should('exist');
        cy.get('.sw-data-grid__row').filter(getVariantRowFilter('E-Book')).eq(2).find(digitalIndicatorClass).should('exist');
        cy.contains('.sw-data-grid__body', '.1');
        cy.contains('.sw-data-grid__body', '.2');
        cy.contains('.sw-data-grid__body', '.3');
        cy.contains('.sw-data-grid__body', '.4');
        cy.contains('.sw-data-grid__body', '.5');
        cy.contains('.sw-data-grid__body', '.6');

        // check the state specific variant tabs
        cy.get('.sw-tabs-item').contains('Physical variants').click();
        cy.get('.sw-product-variants-overview').should('be.visible');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-data-grid__body .sw-data-grid__row').should('have.length', 3);
        cy.get('.sw-data-grid__row').filter(getVariantRowFilter('Hardcover')).should('have.length', 3);

        cy.get('.sw-tabs-item').contains('Digital variants').click();
        cy.get('.sw-product-variants-overview').should('be.visible');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-data-grid__body .sw-data-grid__row').should('have.length', 3);
        cy.get('.sw-data-grid__row').filter(getVariantRowFilter('E-Book')).should('have.length', 3);

        // Navigate to digital variant detail and check that it has a file
        cy.clickContextMenuItem(
            '.sw-context-menu-item:not(.sw-context-menu-item--danger)',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0 .sw-data-grid__cell--actions`,
        );
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-product-download-form__row').scrollIntoView();
        cy.get('.sw-product-download-form__row').should('have.length', 1);
    });

    it('@base @catalogue: make all variants digital and add files', { tags: ['pa-inventory'] }, () => {
        const page = new ProductPageObject();
        const digitalIndicatorClass = '.sw-product-variants-overview__digital-indicator';

        // Navigate to variant generator listing and start
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.get('.sw-product-detail__tab-variants').click();
        cy.get(page.elements.loader).should('not.exist');

        // Create and verify one-dimensional variant
        cy.get(`.sw-product-detail-variants__generated-variants-empty-state ${page.elements.ghostButton}`)
            .should('be.visible')
            .click();
        cy.get('.sw-product-modal-variant-generation').should('be.visible');

        page.generateVariants('Edition', [0, 1, 2], 3, undefined, false);

        cy.get('.sw-product-variant-generation__next-action').click();
        cy.get('.sw-product-modal-variant-generation__upload_files').should('be.visible');
        cy.get('.sw-data-grid__cell--options').should('have.length', 3);

        addFilesToAllVariants({
            filePath: 'img/sw-test-image.png',
            fileName: 'sw-test-image.png',
            mimeType: 'image/png',
        });

        // deactivate digital file switch for variant
        cy.get('.sw-data-grid__cell--options').filter(getVariantRowFilter('Regular')).find('.sw-field--switch__input').click();

        // add additional file to the variant
        cy.get('.sw-data-grid__cell--options').filter(getVariantRowFilter('Special')).find('.sw-media-upload-v2__file-input')
            .attachFile({
                filePath: 'img/sw-login-background.png',
                fileName: 'sw-login-background.png',
                mimeType: 'image/png',
            });
        cy.awaitAndCheckNotification('File has been saved.');

        page.proceedVariantsGeneration(3);

        cy.get('.sw-product-variants-overview').should('be.visible');

        cy.get('.sw-data-grid__body .sw-data-grid__row').should('have.length', 3);
        cy.get(`.sw-data-grid__body .sw-data-grid__row ${digitalIndicatorClass}`).should('have.length', 2);

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/product`,
            method: 'post',
        }).as('searchCall');

        // Navigate to the variant with 2 files detail and check that it has both files
        cy.get('.sw-simple-search-field--form input').clearTypeCheckAndEnter('Special');
        cy.wait('@searchCall').its('response.statusCode').should('equal', 200);
        cy.get('.sw-data-grid__body .sw-data-grid__row').should('have.length', 1);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.clickContextMenuItem(
            '.sw-context-menu-item:not(.sw-context-menu-item--danger)',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0 .sw-data-grid__cell--actions`,
        );

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-product-download-form__row').eq(0).scrollIntoView();
        cy.get('.sw-product-download-form__row').should('have.length', 2);
    });
});
