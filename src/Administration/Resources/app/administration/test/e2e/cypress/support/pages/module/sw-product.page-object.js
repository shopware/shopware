const GeneralPageObject = require('../sw-general.page-object');

export default class ProductPageObject extends GeneralPageObject {
    constructor() {
        super();

        this.elements = {
            ...this.elements,
            ...{
                productSaveAction: '.sw-product-detail__save-action',
                productListName: `${this.elements.dataGridColumn}--name`
            }
        };
    }

    createTag(value) {
        cy.get('.sw-product-category-form__tag-field input')
            .type(value);
        cy.get('.sw-select-result-list').contains(`Add "${value}"`);
        cy.get('.sw-product-category-form__tag-field input')
            .type('{enter}');
        cy.get('.sw-select-result-list').contains(value);
        cy.get('.sw-product-category-form__tag-field input').type('{esc}');
    }

    changeTranslation(language, position) {
        cy.get('.sw-language-switch').click();
        cy.get('.sw-field__select-load-placeholder').should('not.exist');
        cy.get(`.sw-select-option:nth-of-type(${position})`).contains(language).click();
        cy.get('.sw-field__select-load-placeholder').should('not.exist');
    }


    generateVariants(propertyName, optionPosition) {
        const optionsIndicator = '.sw-property-search__tree-selection__column-items-selected.sw-grid-column--right span';
        const optionString = optionPosition.length < 1 ? 'option' : 'options';

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: 'api/v1/product/*',
            method: 'patch'
        }).as('productCall');
        cy.route({
            url: 'api/v1/search/product',
            method: 'post'
        }).as('searchCall');

        cy.get('.group_grid__column-name').click();

        for (const entry in Object.values(optionPosition)) { // eslint-disable-line
            if (optionPosition.hasOwnProperty(entry)) {
                cy.get(
                    `.sw-property-search__tree-selection__option_grid .sw-grid__row--${entry} .sw-field__checkbox input`
                ).click();
            }
        }

        cy.get(`.sw-grid__row--0 ${optionsIndicator}`)
            .contains(`${optionPosition.length} ${optionString} selected`);
        cy.get('.sw-product-variant-generation__generate-action').click();
        cy.get('.sw-product-modal-variant-generation__notification-modal').should('be.visible');
        cy.get('.sw-product-modal-variant-generation__notification-modal .sw-modal__body')
            .contains('3 variants will be added');
        cy.get('.sw-product-modal-variant-generation__notification-modal .sw-button--primary')
            .click();

        cy.wait('@productCall').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-product-modal-variant-generation__notification-modal').should('not.exist');
        cy.get('.generate-variant-progress-bar__description').contains('0 of 3 variations generated');

        cy.wait('@searchCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-product-modal-variant-generation').should('not.exist');
    }
}
