// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

const packagingItemClassName = [
    '.sw-product-packaging-form__purchase-unit-field',
    '.sw-select-product__select_unit',
    '.sw-product-packaging-form__pack-unit-field',
    '.sw-product-packaging-form__pack-unit-plural-field',
    '.sw-product-packaging-form__reference-unit-field'
];

describe('Product: Mode advanced settings at product detail', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                return cy.createDefaultFixture('custom-field-set');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it('@catalogue: should not show the cards, fields when unchecking or toggling in advanced mode menu in General tab', () => {
        const page = new ProductPageObject();

        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/user-config/*`,
            method: 'PATCH'
        }).as('saveUserConfig');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-product-basic-form__promotion-switch').should('be.visible');

        const priceFieldsClassName = [
            '.sw-purchase-price-field',
            '.sw-price-field.sw-list-price-field__list-price'
        ];

        cy.get('.sw-product-detail-base__prices').scrollIntoView().then(() => {
            priceFieldsClassName.forEach(item => {
                cy.get(item).should('be.visible');
            });
        });

        const deliveryFieldsClassName = [
            '.product-deliverability-form__delivery-time',
            '.sw-product-deliverability__restock-field',
            '.sw-product-deliverability__shipping-free',
            '.sw-product-deliverability__min-purchase',
            '.sw-product-deliverability__purchase-step',
            '.sw-product-deliverability__max-purchase'
        ];

        cy.get('.sw-product-detail-base__deliverability').scrollIntoView().then(() => {
            deliveryFieldsClassName.forEach(item => {
                cy.get(item).should('be.visible');
            });
        });

        const structureFieldsClassName = [
            '.sw-product-category-form__tag-field-wrapper',
            '.sw-product-category-form__search-keyword-field'
        ];

        cy.get('.sw-product-detail-base__visibility-structure').scrollIntoView().then(() => {
            structureFieldsClassName.forEach(item => {
                cy.get(item).should('be.visible');
            });
        });

        cy.get('.sw-product-detail-base__labelling-card').scrollIntoView().then(() => {
            cy.get('.sw-product-detail-base__labelling-card').should('be.visible');
        });

        // Toggle off advanced mode
        cy.get('.sw-product-settings-mode').click();
        cy.get('.sw-product-settings-mode__advanced-mode').click();

        // Promotion switch should be hidden
        cy.get('.sw-product-basic-form__promotion-switch').should('not.be.visible');

        // Purchase price and list price field should be hidden
        cy.get('.sw-product-detail-base__prices').scrollIntoView().then(() => {
            priceFieldsClassName.forEach(item => {
                cy.get(item).should('not.be.visible');
            });
        });

        // Labelling card should be hidden
        cy.get('.sw-product-detail-base__labelling-card').should('not.be.visible');

        // Delivery fields shoud be hidden
        cy.get('.sw-product-detail-base__deliverability').scrollIntoView().then(() => {
            deliveryFieldsClassName.forEach(item => {
                cy.get(item).should('not.be.visible');
            });
        });

        // Structure fields shoud be hidden
        cy.get('.sw-product-detail-base__visibility-structure').scrollIntoView().then(() => {
            structureFieldsClassName.forEach(item => {
                cy.get(item).should('not.be.visible');
            });
        });

        // Toggle on advanced mode
        cy.get('.sw-product-settings-mode__advanced-mode').click();

        cy.wait('@saveUserConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-product-settings-mode__list .sw-product-settings-mode__item').eq(0).click();
        cy.wait('@saveUserConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
            cy.get('.sw-product-detail-base__info').should('not.be.visible');
        });

        cy.get('.sw-product-settings-mode__list .sw-product-settings-mode__item').eq(1).click();
        cy.wait('@saveUserConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
            cy.get('.sw-product-detail-base__prices').should('not.be.visible');
        });

        cy.get('.sw-product-settings-mode__list .sw-product-settings-mode__item').eq(2).click();
        cy.wait('@saveUserConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
            cy.get('.sw-product-detail-base__deliverability').should('not.be.visible');
        });

        cy.get('.sw-product-settings-mode__list .sw-product-settings-mode__item').eq(3).click();
        cy.wait('@saveUserConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
            cy.get('.sw-product-detail-base__visibility-structure').should('not.be.visible');
        });

        cy.get('.sw-product-settings-mode__list .sw-product-settings-mode__item').eq(4).click();
        cy.wait('@saveUserConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
            cy.get('.sw-product-detail-base__media').should('not.be.visible');
        });

        cy.get('.sw-product-settings-mode__list .sw-product-settings-mode__item').eq(5).click();
        cy.wait('@saveUserConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
            cy.get('.sw-product-detail-base__labelling-card').should('not.be.visible');
        });
    });

    it('@catalogue: should not show the cards, fields when unchecking or toggling in advanced mode menu in Specification tab', () => {
        const page = new ProductPageObject();

        cy.server();
        cy.route({
            method: 'PATCH',
            url: '/api/custom-field-set/*'
        }).as('saveData');

        cy.route({
            url: `${Cypress.env('apiPath')}/user-config/*`,
            method: 'PATCH'
        }).as('saveUserConfig');

        cy.visit(`${Cypress.env('admin')}#/sw/settings/custom/field/index`);

        cy.get('.sw-custom-field-set-list__column-name').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-settings-custom-field-set-detail-base__label-entities').typeMultiSelectAndCheck('Products');
        cy.get('.sw-button-process').click();

        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.awaitAndCheckNotification('has been saved');
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-tabs-item.sw-product-detail__tab-specifications').click();

        cy.get('.sw-product-detail-specification__measures-packaging').should('be.visible');

        cy.onlyOnFeature('FEATURE_NEXT_12437', () => {
            cy.get('.sw-product-properties').should('be.visible');
        });
        cy.skipOnFeature('FEATURE_NEXT_12437', () => {
            cy.get('.sw-product-detail-properties').should('be.visible');
        });

        cy.get('.sw-product-detail-specification__essential-characteristics').should('be.visible');

        packagingItemClassName.forEach(item => {
            cy.get(item).should('be.visible');
        });

        cy.get('.sw-product-detail-specification__essential-characteristics').scrollIntoView()
            .then(() => {
                cy.get('.sw-product-detail-specification__custom-fields').should('be.visible');
            });

        // Toggle off advanced mode
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-product-settings-mode').click();
        cy.get('.sw-product-settings-mode__advanced-mode').click();

        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-product-detail-specification__measures-packaging').should('be.visible');

        cy.onlyOnFeature('FEATURE_NEXT_12437', () => {
            cy.get('.sw-product-properties').should('be.visible');
        });
        cy.skipOnFeature('FEATURE_NEXT_12437', () => {
            cy.get('.sw-product-detail-properties').should('be.visible');
            cy.get('.sw-product-detail-properties .sw-empty-state__element').should('be.visible');
        });

        cy.get('.sw-product-detail-specification__essential-characteristics').should('not.be.visible');

        packagingItemClassName.forEach(item => {
            cy.get(item).should('not.be.visible');
        });

        cy.get('.sw-product-detail-specification__custom-fields').should('not.be.visible');

        // Toggle on advanced mode
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-product-settings-mode__advanced-mode').click();

        cy.wait('@saveUserConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-product-settings-mode__list .sw-product-settings-mode__item').eq(0).click();
        cy.wait('@saveUserConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
            cy.get('sw-product-detail-specification__measures-packaging').should('not.be.visible');
        });

        cy.get('.sw-product-settings-mode__list .sw-product-settings-mode__item').eq(1).click();
        cy.wait('@saveUserConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 204);

            cy.onlyOnFeature('FEATURE_NEXT_12437', () => {
                cy.get('.sw-product-properties').should('not.be.visible');
            });
            cy.skipOnFeature('FEATURE_NEXT_12437', () => {
                cy.get('.sw-product-detail-properties').should('not.be.visible');
            });
        });

        cy.get('.sw-product-settings-mode__list .sw-product-settings-mode__item').eq(2).click();
        cy.wait('@saveUserConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
            cy.get('.sw-product-detail-specification__essential-characteristics').should('not.be.visible');
        });

        cy.get('.sw-product-settings-mode__list .sw-product-settings-mode__item').eq(3).click();
        cy.wait('@saveUserConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
            cy.get('.sw-product-detail-specification__custom-fields').should('not.be.visible');
        });
    });
});
