import elements from '../sw-general.page-object';

export default class ProductPageObject {
    constructor() {
        this.elements = {
            ...elements,
            ...{
                productSaveAction: '.sw-product-detail__save-action',
                productListName: `${elements.dataGridColumn}--name`,
            },
        };
    }

    createTag(value) {
        cy.get('.sw-product-category-form__tag-field input')
            .type(value);
        cy.get('.sw-select-result-list-popover-wrapper').contains(`Add "${value}"`);
        cy.get('.sw-product-category-form__tag-field input')
            .type('{enter}');
        cy.get('.sw-select-result-list-popover-wrapper').contains(value);
        cy.get('.sw-product-category-form__tag-field input').type('{esc}');
    }

    changeTranslation(language, position) {
        cy.get('.sw-language-switch .sw-loader').should('not.exist');
        cy.get('.sw-language-switch').click();
        cy.get(`.sw-select-result-list__item-list .sw-select-option--${position}`)
            .contains(language)
            .click();
        cy.get('.sw-language-switch .sw-loader').should('not.exist');
    }


    generateVariants(propertyName, optionPosition, totalCount) {
        const optionsIndicator = '.sw-property-search__tree-selection__column-items-selected.sw-grid-column--right span';
        const optionString = totalCount === 1 ? 'value' : 'values';

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'post',
        }).as('productCall');
        cy.route({
            url: `${Cypress.env('apiPath')}/search/product`,
            method: 'post',
        }).as('searchCall');

        cy.contains(propertyName).click();

        for (const entry in Object.values(optionPosition)) { // eslint-disable-line
            if (optionPosition.hasOwnProperty(entry)) {
                cy.get(
                    `.sw-property-search__tree-selection__option_grid .sw-grid__row--${entry} .sw-field__checkbox input`,
                ).click();
            }
        }

        cy.get(`.sw-grid ${optionsIndicator}`)
            .contains(`${optionPosition.length} ${optionString} selected`);
        cy.get('.sw-product-variant-generation__generate-action').click();
        cy.get('.sw-product-modal-variant-generation__notification-modal').should('be.visible');

        if (totalCount !== 1) {
            cy.get('.sw-product-modal-variant-generation__notification-modal .sw-modal__body')
                .contains(`${totalCount} variants will be added`);
        }

        cy.get('.sw-product-modal-variant-generation__notification-modal .sw-button--primary')
            .click();

        cy.wait('@productCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get('.sw-product-modal-variant-generation__notification-modal').should('not.exist');
        cy.get('.generate-variant-progress-bar__description').contains(`0 of ${totalCount} variations generated`);

        cy.wait('@searchCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-product-modal-variant-generation').should('not.exist');
    }
}
